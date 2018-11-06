<?php
	$configuration = array();	
	$configuration[] = array('from' => 'h7', 'to' => 'h4', 'switch' => 's8', 'rate' => 10);
	$configuration[] = array('from' => 'h20', 'to' => 'h1', 'switch' => 's6', 'rate' => 5);
	
	foreach($configuration as $flowConfig){
		
		$fromMac = "00:00:00:00:00:".str_pad(dechex(str_replace("h", "", $flowConfig['from'])), 2, "0", STR_PAD_LEFT);
		$toMac = "00:00:00:00:00:".str_pad(dechex(str_replace("h", "", $flowConfig['to'])), 2, "0", STR_PAD_LEFT);
		
		exec("sudo dpctl unix:/tmp/{$flowConfig['switch']} flow-rate-mod table=0,rate={$flowConfig['rate']} eth_src=$fromMac,eth_dst=$toMac", $output);		
	}
	
	echo implode(PHP_EOL, $output);	
?>