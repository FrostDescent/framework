<?php
	class Node{
		public $name;
		public $visited = FALSE;
	}
	class Topology{		
		private $nodes = array(); //array mapping from node name to Node object
		private $adj = array(); //a mapping from node name to adjacence node names
		
		function __construct(){
			# Switches
			$s1 = $this->addSwitch( 's1' );
			$s2 = $this->addSwitch( 's2' );
			$s3 = $this->addSwitch( 's3' );
			$s4 = $this->addSwitch( 's4' );
			$s5 = $this->addSwitch( 's5' );
			$s6 = $this->addSwitch( 's6' );
			$s7 = $this->addSwitch( 's7' );
			$s8 = $this->addSwitch( 's8' );
			$s9 = $this->addSwitch( 's9' );
			$s10 = $this->addSwitch( 's10' );
			$s11 = $this->addSwitch( 's11' );
			$s12 = $this->addSwitch( 's12' );
			$s13 = $this->addSwitch( 's13' );
			$s14 = $this->addSwitch( 's14' );
			$s15 = $this->addSwitch( 's15' );
			$s16 = $this->addSwitch( 's16' );
			$s17 = $this->addSwitch( 's17' );

			# Switches links
			$this->addLink($s1, $s4);
			$this->addLink($s2, $s4);
			$this->addLink($s3, $s5);
			$this->addLink($s4, $s5);
			$this->addLink($s4, $s6);
			$this->addLink($s6, $s7);
			$this->addLink($s6, $s9);
			$this->addLink($s7, $s8);
			$this->addLink($s8, $s10);
			$this->addLink($s9, $s13);
			$this->addLink($s10, $s11);
			$this->addLink($s10, $s12);
			$this->addLink($s13, $s14);
			$this->addLink($s13, $s15);
			$this->addLink($s14, $s17);
			$this->addLink($s15, $s16);
			
			# Hosts
			$h1 = $this->addHost( 'h1' );
			$h2 = $this->addHost( 'h2' );
			$h3 = $this->addHost( 'h3' );
			$h4 = $this->addHost( 'h4' );
			$h5 = $this->addHost( 'h5' );
			$h6 = $this->addHost( 'h6' );
			$h7 = $this->addHost( 'h7' );
			$h8 = $this->addHost( 'h8' );
			$h9 = $this->addHost( 'h9' );
			$h10 = $this->addHost( 'h10' );
			$h11 = $this->addHost( 'h11' );
			$h12 = $this->addHost( 'h12' );
			$h13 = $this->addHost( 'h13' );
			$h14 = $this->addHost( 'h14' );
			$h15 = $this->addHost( 'h15' );
			$h16 = $this->addHost( 'h16' );
			$h17 = $this->addHost( 'h17' );
			$h18 = $this->addHost( 'h18' );
			$h19 = $this->addHost( 'h19' );
			$h20 = $this->addHost( 'h20' );

			# Host links
			$this->addLink($h1, $s1);
			$this->addLink($h2, $s1);
			$this->addLink($h3, $s2);
			$this->addLink($h4, $s2);
			$this->addLink($h5, $s3);
			$this->addLink($h6, $s3);
			$this->addLink($h7, $s12);
			$this->addLink($h8, $s12);
			$this->addLink($h9, $s12);
			$this->addLink($h10, $s11);
			$this->addLink($h11, $s11);
			$this->addLink($h12, $s11);
			$this->addLink($h13, $s15);
			$this->addLink($h14, $s16);
			$this->addLink($h15, $s16);
			$this->addLink($h16, $s16);
			$this->addLink($h17, $s17);
			$this->addLink($h18, $s17);
			$this->addLink($h19, $s17);
			$this->addLink($h20, $s14);
		}
		
		function addLink($name1, $name2){
			$this->adj[$name1][] = $name2;
			$this->adj[$name2][] = $name1;
		}
		
		function addHost($name){
			return $this->addNode($name);
		}
		
		function addSwitch($name){
			return $this->addNode($name);
		}
		
		function addNode($name){
			$node = new Node();
			$node->name = $name;
			
			$this->nodes[$name] = $node;			
			$this->adj[$name] = array();
			
			return $name;
		}
		
		function getPath($fromName, $toName){
			foreach($this->nodes as $name=>$node){
				$node->visited = FALSE;
			}
			$path = array();
			$this->getPathAux($this->nodes[$fromName], $toName, $path);
			
			return $path;
		}
		
		function getPathAux($currentNode, $toName, &$currentPath){
			if($currentNode->name == $toName){
				$currentPath[] = $toName;
				return TRUE;
			}
			
			$currentNode->visited = TRUE;
			
			$found = FALSE;
			$currentPath[] = $currentNode->name;			
			foreach($this->adj[$currentNode->name] as $adjName){
				if($this->nodes[$adjName]->visited == FALSE){
					$found = $found || $this->getPathAux($this->nodes[$adjName], $toName, $currentPath);
				}
			}
			
			if($found == FALSE){
				array_pop($currentPath);
			}
			
			return $found;
		}
	}
	
	$t = new Topology();
	$flows = array('h20-h1',
				   'h19-h2',
				   'h18-h3',
				   'h17-h4',
				   'h16-h5',
				   'h15-h1',
				   'h14-h2',
				   'h13-h3',
				   'h12-h4',
				   'h11-h5',
				   'h10-h1',
				   'h9-h2',
				   'h8-h3',
				   'h7-h4',	
				   
				   'h20-h2',
				   'h19-h3',
				   'h18-h4',
				   'h17-h5',
				   'h16-h1',
				   'h15-h2',
				   'h14-h3',
				   'h13-h4',
				   'h12-h5',
				   'h11-h1',
				   'h10-h2',
				   'h9-h3',
				   'h8-h4',
				   'h7-h5'
				   );
			
				echo count($flows);
	$toFile = array();			   
	foreach($flows as $flow){
		list($from, $to) = explode('-', $flow);
		$path = $t->getPath($from, $to);
		$toFile[] = implode(',', $path);
	}
	file_put_contents("flows.txt", implode(PHP_EOL, $toFile));
?>