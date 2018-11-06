<?php
	require_once('classes.php');

	define("STATE_FILE", "sampling_engine_files/state.txt");	
	
	define("MCKP_PROFIT_FILE", "sampling_engine_files/mckp_input/profit.txt");
	define("MCKP_WEIGHT_FILE", "sampling_engine_files/mckp_input/weight.txt");
	define("MCKP_SIZE_FILE", "sampling_engine_files/mckp_input/size.txt");
	define("MCKP_NUM_CLASSES_FILE", "sampling_engine_files/mckp_input/num_classes.txt");
	define("MCKP_NUM_ITEMS_PER_CLASS_FILE", "sampling_engine_files/mckp_input/num_items_per_class.txt");
	
	define("DEBUG_PRINTS", 0);
	
	function DEBUG($str){
		if(DEBUG_PRINTS){
			echo $str.PHP_EOL;
		}
	}	
	
	function dpctlSetSamplingRate($fromMininetName, $toMininetName, $switchMininetName, $rate){
		$fromMac = "00:00:00:00:00:".str_pad(dechex(str_replace("h", "", $fromMininetName)), 2, "0", STR_PAD_LEFT);
		$toMac = "00:00:00:00:00:".str_pad(dechex(str_replace("h", "", $toMininetName)), 2, "0", STR_PAD_LEFT);
		
		exec("sudo dpctl unix:/tmp/$switchMininetName flow-rate-mod table=0,rate=$rate eth_src=$fromMac,eth_dst=$toMac", $output);		
		echo implode(PHP_EOL, $output);		
	}
	
	function addFlow($state, $incomingFlow){
		DEBUG("#######################################################");
		DEBUG("New flow # pps: ".$incomingFlow->pps." switches: ".implode(",", $incomingFlow->switchesMininetNames));
		
		//create a new flow
		$flow = new Flow();
		$flow->name = $incomingFlow->name;
		$flow->fromMininetName = $incomingFlow->fromMininetName;
		$flow->toMininetName = $incomingFlow->toMininetName;
		$flow->pps = $incomingFlow->pps;
		foreach($incomingFlow->switchesMininetNames as $mininetName){
			$flow->switches[] = $state->switches[$mininetName];
		}		
		$flow->profitFunction = $incomingFlow->profitFunction;		
		
		//add the flow to the list of state flows
		$state->flows[] = $flow;
		
		//add the flow to the list of passing flows of all its switches
		foreach($flow->switches as $switch){
			if(in_array($flow, $switch->passingFlows, true) === FALSE){
				array_push($switch->passingFlows, $flow);
			}						
		}				
		
		//create num_items_per_class file
		DEBUG("MCKP_NUM_ITEMS_PER_CLASS_FILE = ".count(array_keys($flow->profitFunction)));
		file_put_contents(MCKP_NUM_ITEMS_PER_CLASS_FILE, count(array_keys($flow->profitFunction)));		
		
		//loop through all the switches the new flow traverses
		$bestProfitDelta = -1;
		$bestProfitDeltaSol = array();
		$bestSwitch = -1;
		$bestSwitchFlows = array();
		$bestSwitchLoad = -1;
		
		foreach($flow->switches as $switch){
			DEBUG("------------------------------------------------------------");
			DEBUG("Checking profit of sampling in switch ".$switch->mininetName);
			//create knapsack size file
			DEBUG("MCKP_SIZE_FILE = ". $switch->samplingCapacity);
			file_put_contents(MCKP_SIZE_FILE, $switch->samplingCapacity);
		
			//get all the flows which pass the current switch
			$currentSwitchFlows = $switch->passingFlows;
			
			//remove all the flows which are sampled by other switches			
			$currentSwitchFlows = array_filter($currentSwitchFlows, function($flow) use ($switch) {
																		return $flow->samplingSwitch === $switch || $flow->samplingSwitch === null;																		
																	});			
																	
			//after filtering some of the indices may be missing so we resinset the items to the array
			$temp = array();
			foreach($currentSwitchFlows as $currentSwitchFlow){
				$temp[] = $currentSwitchFlow;
			}
			$currentSwitchFlows = $temp;
			
			//create num_classes file
			DEBUG("MCKP_NUM_CLASSES_FILE = ".count($currentSwitchFlows));
			file_put_contents(MCKP_NUM_CLASSES_FILE, count($currentSwitchFlows));

			//create profit and weight files
			$fprofit = fopen(MCKP_PROFIT_FILE, "w");
			$fweight = fopen(MCKP_WEIGHT_FILE, "w");
			
			for($j = 0 ; $j < count($currentSwitchFlows) ; ++$j){
				set_time_limit(30);
			
				$flowPPS = $currentSwitchFlows[$j]->pps;
				$flowProfitFunction = $currentSwitchFlows[$j]->profitFunction;
				
				foreach($flowProfitFunction as $rate=>$profit){
					fwrite($fweight, ceil((($rate == 0)?0:(1.0/$rate)) * $flowPPS).",");
					fwrite($fprofit, $profit.",");
				}
			}

			fclose($fprofit);
			fclose($fweight);
			
			//run MCKP
			chdir('sampling_engine_files');
			$MCKP = `./RunMCKP`;
			chdir('..');
			
			//calculate the profit delta			
			$oldProfit = $switch->currentProfit;
			$MCKPArr = explode(" ", $MCKP);

			$profit = 0;
			$load = 0;
			
			DEBUG("MCKP output: ".$MCKP);
			DEBUG("Old profit: ".$oldProfit);			

			for($j = 0 ; $j < count($MCKPArr) ; ++$j){
				if($MCKPArr[$j] > 0){
					$rates = array_keys($currentSwitchFlows[$j]->profitFunction);
					$profits = array_values($currentSwitchFlows[$j]->profitFunction);
					
					$profit += $profits[$MCKPArr[$j]];
					$load += $currentSwitchFlows[$j]->pps * (1.0/$rates[$MCKPArr[$j]]);
				}				
			}
			$profiDelta = $profit - $oldProfit;
			
			DEBUG("Profit delta: ".$profiDelta);

			if($profiDelta > $bestProfitDelta){
				$bestProfitDelta = $profiDelta;
				$bestProfitDeltaSol = $MCKPArr;
				$bestSwitch = $switch;
				$bestSwitchFlows = $currentSwitchFlows;
				$bestSwitchLoad = $load;
			}

		}	

		//update where each flow is sampled	
		DEBUG("Flow ".$flow->name." will be sampled in switch ".$bestSwitch->mininetName);
		for($j = 0 ; $j < count($bestProfitDeltaSol) ; ++$j){
			if($bestProfitDeltaSol[$j] > 0){				
				$bestSwitchFlows[$j]->samplingSwitch = $bestSwitch;
				$samplingRates = array_keys($bestSwitchFlows[$j]->profitFunction);
				$bestSwitchFlows[$j]->samplingRate = $samplingRates[$bestProfitDeltaSol[$j]];								
				
				dpctlSetSamplingRate($bestSwitchFlows[$j]->fromMininetName, $bestSwitchFlows[$j]->toMininetName, $bestSwitchFlows[$j]->samplingSwitch->mininetName, $bestSwitchFlows[$j]->samplingRate);
				
				DEBUG("Flow ".$bestSwitchFlows[$j]->name. " will be sampled with rate=1/".$bestSwitchFlows[$j]->samplingRate);
			}else{
				//*** We assume here that if a flow rate changed to 0, we can sample it in another switch ***
				$bestSwitchFlows[$j]->samplingSwitch = null;
				$bestSwitchFlows[$j]->samplingRate = null;				
				
				dpctlSetSamplingRate($bestSwitchFlows[$j]->fromMininetName, $bestSwitchFlows[$j]->toMininetName, $bestSwitch->mininetName, 0);
				
				DEBUG("Flow ".$bestSwitchFlows[$j]->name. " will not be sampled.");
			}
			
		}
		//update the current profit and load in the chosen switch
		$bestSwitch->currentProfit += $bestProfitDelta;		
		$bestSwitch->currentLoad = $bestSwitchLoad;		

		return $state;
	}
	
	function getFlowSwitches($fromMininetName, $toMininetName){
		$flows = file("flows.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		$flows = array_map(function($x){ return explode(",", $x); }, $flows);
		
		foreach($flows as $flow){
			if(($flow[0] == $fromMininetName) && ($flow[count($flow)-1] == $toMininetName)){
				array_pop($flow);
				array_shift($flow);
				return $flow;
			}
		}
		
		die("Unable to find flow $fromMininetName -> $toMininetName");
	}

	function CLI_ADD_FLOW($state, $fromMininetName, $toMininetName, $pps){
		$incomingFlow = new IncomingFlow();
		$incomingFlow->name = "$fromMininetName->$toMininetName";
		$incomingFlow->fromMininetName = $fromMininetName;
		$incomingFlow->toMininetName = $toMininetName;		
		$incomingFlow->switchesMininetNames = getFlowSwitches($fromMininetName, $toMininetName);
		$incomingFlow->pps = $pps;
		$incomingFlow->profitFunction = array(0=>0, 2=>100, 4=>80, 6=>60, 8=>40, 10=>20);
		
		return addFlow($state, $incomingFlow);	
	}	
	
	function CLI_INIT_ENGINE($mininetSwitchesNames, $switchesCapacities){
		$state = new State();
		
		for($i = 0; $i < count($mininetSwitchesNames) ; ++$i){
			$switch = new SSwitch();			
			$switch->mininetName = $mininetSwitchesNames[$i];
			$switch->samplingCapacity = $switchesCapacities[$i];
			
			$state->switches[$mininetSwitchesNames[$i]] = $switch;	
		}

		return $state;
	}
	
	function CLI_PRINT_STATE($state){
		foreach($state->flows as $flow){
			if($flow->samplingSwitch){
				echo $flow->name." is sampled in switch ".($flow->samplingSwitch->mininetName)." with rate 1/".($flow->samplingRate).PHP_EOL;
			}else{
				echo $flow->name." is NOT sampled.".PHP_EOL;
			}			
		}
		echo "------------".PHP_EOL;
		foreach($state->switches as $mininetName=>$switch){
			echo "$mininetName: ";
			foreach($switch->passingFlows as $flow){
				if($flow->samplingSwitch && $flow->samplingSwitch->mininetName == $mininetName){
					echo $flow->name;
					echo ",";
				}
			}
			echo PHP_EOL;
		}
	}
		
	//##################### Main #####################	
	
	if(count($argv) == 1){
		die("Not Enought Arguments!");
	}
	$op = $argv[1];
	
	$fpstate = fopen(STATE_FILE, 'r+');
	if(!flock($fpstate, LOCK_EX)){
			die("---- Unable to lock".PHP_EOL);
	}
	
	switch($op){
		case "INIT":				
			if(count($argv) != 4){
				die("Wrong number of arguments for INIT!");
			}
			$switches = explode(",", $argv[2]);
			$switchesCapacities = explode(",", $argv[3]);
			
			if(count($switches) != count($switchesCapacities)){
				die("Switches and Switches Capacities are different length!");
			}
			
			$newstate = CLI_INIT_ENGINE($switches, $switchesCapacities);
			ftruncate($fpstate, 0);
			fwrite($fpstate, serialize($newstate));
			fflush($fpstate);
			
			break;
			
		case "ADD_FLOW":
			if(count($argv) != 5){
				die("Wrong number of arguments for ADD_FLOW!");
			}
			$from = $argv[2];
			$to = $argv[3];
			$pps = $argv[4];
			
			$state = unserialize(fread($fpstate,filesize(STATE_FILE)));
			
			rewind($fpstate);

			$newstate = CLI_ADD_FLOW($state, $from, $to, $pps);

			ftruncate($fpstate, 0);
			fwrite($fpstate, serialize($newstate));
			fflush($fpstate);
			
			break;
		case "PRINT_STATE":
			$state = unserialize(fread($fpstate,filesize(STATE_FILE)));
			CLI_PRINT_STATE($state);
			
			break;
		default:
			die("Unknown Operation!");
	}

	fclose($fpstate);
	
	exit();
	
	
?>