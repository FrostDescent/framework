<?php
	class Flow{
		public $name = null;
		public $fromMininetName = null;
		public $toMininetName = null;
		public $pps = null;
		public $switches = array(); //the switches that the flow pass (SSwitch)
		public $samplingSwitch = null; //if the flow is sampled, this is the switch that samples it		
		public $samplingRate = null; //if the flow is sampled, this is the sampling rate		
		public $profitFunction = array(); //a mapping rate -> profit
	}		
	
	
	class IncomingFlow{
		public $name = null;
		public $switchesMininetNames = array();
		public $pps = null;
		public $profitFunction = array(); //a mapping rate -> profit
	}
	
	class SSwitch{
		public $mininetName = null;
		public $samplingCapacity = 0;
		public $passingFlows = array();
		public $currentProfit = 0;
		public $currentLoad = 0;
	}

	class State{
		public $flows = array(); //all the flows which are currently in the system
		public $switches = array(); // a mapping mininetName -> SSwitch
	}
?>