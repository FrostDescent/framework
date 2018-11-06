<?php
	$netData = <<<DATA
h1 h1-eth0:s1-eth2
h2 h2-eth0:s1-eth3
h3 h3-eth0:s2-eth2
h4 h4-eth0:s2-eth3
h5 h5-eth0:s3-eth2
h6 h6-eth0:s3-eth3
h7 h7-eth0:s12-eth2
h8 h8-eth0:s12-eth3
h9 h9-eth0:s12-eth4
h10 h10-eth0:s11-eth2
h11 h11-eth0:s11-eth3
h12 h12-eth0:s11-eth4
h13 h13-eth0:s15-eth3
h14 h14-eth0:s16-eth2
h15 h15-eth0:s16-eth3
h16 h16-eth0:s16-eth4
h17 h17-eth0:s17-eth2
h18 h18-eth0:s17-eth3
h19 h19-eth0:s17-eth4
h20 h20-eth0:s14-eth3
s1 lo:  s1-eth1:s4-eth1 s1-eth2:h1-eth0 s1-eth3:h2-eth0
s2 lo:  s2-eth1:s4-eth2 s2-eth2:h3-eth0 s2-eth3:h4-eth0
s3 lo:  s3-eth1:s5-eth1 s3-eth2:h5-eth0 s3-eth3:h6-eth0
s4 lo:  s4-eth1:s1-eth1 s4-eth2:s2-eth1 s4-eth3:s5-eth2 s4-eth4:s6-eth1
s5 lo:  s5-eth1:s3-eth1 s5-eth2:s4-eth3
s6 lo:  s6-eth1:s4-eth4 s6-eth2:s7-eth1 s6-eth3:s9-eth1
s7 lo:  s7-eth1:s6-eth2 s7-eth2:s8-eth1
s8 lo:  s8-eth1:s7-eth2 s8-eth2:s10-eth1
s9 lo:  s9-eth1:s6-eth3 s9-eth2:s13-eth1
s10 lo:  s10-eth1:s8-eth2 s10-eth2:s11-eth1 s10-eth3:s12-eth1
s11 lo:  s11-eth1:s10-eth2 s11-eth2:h10-eth0 s11-eth3:h11-eth0 s11-eth4:h12-eth0
s12 lo:  s12-eth1:s10-eth3 s12-eth2:h7-eth0 s12-eth3:h8-eth0 s12-eth4:h9-eth0
s13 lo:  s13-eth1:s9-eth2 s13-eth2:s14-eth1 s13-eth3:s15-eth1
s14 lo:  s14-eth1:s13-eth2 s14-eth2:s17-eth1 s14-eth3:h20-eth0
s15 lo:  s15-eth1:s13-eth3 s15-eth2:s16-eth1 s15-eth3:h13-eth0
s16 lo:  s16-eth1:s15-eth2 s16-eth2:h14-eth0 s16-eth3:h15-eth0 s16-eth4:h16-eth0
s17 lo:  s17-eth1:s14-eth2 s17-eth2:h17-eth0 s17-eth3:h18-eth0 s17-eth4:h19-eth0
c0
DATA;

	$outData = array("#!/bin/bash");
	
	//###################### Functions #########################
	
	function findPortNum($switch, $otherDev){
		global $netData;
		
		preg_match("/$switch-eth([0-9]+):$otherDev/", $netData, $matches);
		return $matches[1];
	}
	
	function addFlowsRules($flows){
		global $outData;
		
		foreach($flows as $flow){
			$fromMac = "00:00:00:00:00:".str_pad(dechex(str_replace("h", "", $flow[0])), 2, "0", STR_PAD_LEFT);
			$toMac = "00:00:00:00:00:".str_pad(dechex(str_replace("h", "", $flow[count($flow)-1])), 2, "0", STR_PAD_LEFT);
				
			for($i = 1 ; $i < count($flow)-1 ; $i++){
				$currentSwitch = $flow[$i];
				
				$outPort = findPortNum($currentSwitch, $flow[$i+1]);
				
				$outData[] = "sudo dpctl unix:/tmp/".$currentSwitch." flow-mod table=0,cmd=add eth_src=".$fromMac.",eth_dst=".$toMac." apply:output=$outPort";				
			}
		}
	}
	
	function addCollectorRules($collectorRoute, $collectorMac){
		global $outData;
		
		foreach($collectorRoute as $route){
			$routeArr = explode("-", $route);
			$from = $routeArr[0];
			$to = $routeArr[1];
			
			$outPort = findPortNum($from, $to);
			
			$outData[] = "sudo dpctl unix:/tmp/".$from." flow-mod table=0,cmd=add eth_dst=".$collectorMac." apply:output=$outPort";				
		}
	}
	
	//###################### Main #########################
	
	//find all the switches
	preg_match_all('/(s[0-9]+) lo/', $netData, $matches);
	$switches = $matches[1];
	
	//configure broadcast rules
	foreach($switches as $switch){
		$outData[] = "sudo dpctl unix:/tmp/$switch flow-mod table=0,cmd=add eth_dst=FF:FF:FF:FF:FF:FF apply:output=flood";
	}
	
	//configure collector rules
	$collectorRoute = array("s1-s4", "s2-s4", "s3-h6", "s4-s5", "s5-s3", "s6-s4", "s7-s6", "s8-s7", "s9-s6", "s10-s8", "s11-s10", "s12-s10", "s13-s9", "s14-s13", "s15-s13", "s16-s15", "s17-s14");
	addCollectorRules($collectorRoute, "00:00:00:00:00:06");
	
	//configure flows rules
	$flows = file("flows.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$flows = array_map(function($x){ return explode(",", $x); }, $flows);
	
	$reversedFlows = array_map(function($arr) { return array_reverse($arr); }, $flows);
	$flows = array_merge($flows, $reversedFlows);	

	addFlowsRules($flows);

	//write output
	file_put_contents("phpConfigSwitch.sh", implode(PHP_EOL, $outData));
	chmod("phpConfigSwitch.sh", 0777);
?>
