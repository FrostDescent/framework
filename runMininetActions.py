#!/usr/bin/python

from mininet.topo import Topo
from mininet.net import Mininet
from mininet.node import UserSwitch
from mininet.node import RemoteController
from mininet.cli import CLI
import subprocess
import os

class MyTopo2( Topo ):
	def __init__( self ):
		# Initialize topology
		Topo.__init__( self )

		# Switches
		s1 = self.addSwitch( 's1' )
		s2 = self.addSwitch( 's2' )
		s3 = self.addSwitch( 's3' )
		s4 = self.addSwitch( 's4' )
		s5 = self.addSwitch( 's5' )
		s6 = self.addSwitch( 's6' )
		s7 = self.addSwitch( 's7' )
		s8 = self.addSwitch( 's8' )
		s9 = self.addSwitch( 's9' )
		s10 = self.addSwitch( 's10' )
		s11 = self.addSwitch( 's11' )
		s12 = self.addSwitch( 's12' )
		s13 = self.addSwitch( 's13' )
		s14 = self.addSwitch( 's14' )
		s15 = self.addSwitch( 's15' )
		s16 = self.addSwitch( 's16' )
		s17 = self.addSwitch( 's17' )

		# Switches links
		self.addLink(s1, s4)
		self.addLink(s2, s4)
		self.addLink(s3, s5)
		self.addLink(s4, s5)
		self.addLink(s4, s6)
		self.addLink(s6, s7)
		self.addLink(s6, s9)
		self.addLink(s7, s8)
		self.addLink(s8, s10)
		self.addLink(s9, s13)
		self.addLink(s10, s11)
		self.addLink(s10, s12)
		self.addLink(s13, s14)
		self.addLink(s13, s15)
		self.addLink(s14, s17)
		self.addLink(s15, s16)
		
		# Hosts
		h1 = self.addHost( 'h1' )
		h2 = self.addHost( 'h2' )
		h3 = self.addHost( 'h3' )
		h4 = self.addHost( 'h4' )
		h5 = self.addHost( 'h5' )
		h6 = self.addHost( 'h6' )
		h7 = self.addHost( 'h7' )
		h8 = self.addHost( 'h8' )
		h9 = self.addHost( 'h9' )
		h10 = self.addHost( 'h10' )
		h11 = self.addHost( 'h11' )
		h12 = self.addHost( 'h12' )
		h13 = self.addHost( 'h13' )
		h14 = self.addHost( 'h14' )
		h15 = self.addHost( 'h15' )
		h16 = self.addHost( 'h16' )
		h17 = self.addHost( 'h17' )
		h18 = self.addHost( 'h18' )
		h19 = self.addHost( 'h19' )
		h20 = self.addHost( 'h20' )

		# Host links
		self.addLink(h1, s1)
		self.addLink(h2, s1)
		self.addLink(h3, s2)
		self.addLink(h4, s2)
		self.addLink(h5, s3)
		self.addLink(h6, s3)
		self.addLink(h7, s12)
		self.addLink(h8, s12)
		self.addLink(h9, s12)
		self.addLink(h10, s11)
		self.addLink(h11, s11)
		self.addLink(h12, s11)
		self.addLink(h13, s15)
		self.addLink(h14, s16)
		self.addLink(h15, s16)
		self.addLink(h16, s16)
		self.addLink(h17, s17)
		self.addLink(h18, s17)
		self.addLink(h19, s17)
		self.addLink(h20, s14)

def startTest():
	#sudo stop network-manager
	
	topo = MyTopo2()
	net = Mininet( topo=topo, switch=UserSwitch,
				   controller=RemoteController, 
				   autoSetMacs=True)
		
	print "*** Cleaning sent_packets Directory ***"
	filelist = [ f for f in os.listdir("./output/sent_packets/") if f.endswith(".txt") ]
	for f in filelist:
		os.remove("./output/sent_packets/"+f)			
	
	print "*** Initializing Sampling Engine.. ***"
	switches = [s.name for s in net.switches]
	switchesCapacities = [str(50) for x in xrange(len(switches))]
	subprocess.call(['sudo', 'php', 'samplingEngine.php', 'INIT', ','.join(switches), ','.join(switchesCapacities)]);
		
	print "*** Starting Network.. ***"
	net.start()
	print "*** Network Started ***"
	
	raw_input("Press any key to configure switch table entries")
	print "*** Creating Switch Configuration Script ***"
	subprocess.call(['sudo', 'php', 'createSwitchConfig.php']);
	print "*** Configuring Switch ***"
	subprocess.call(['sudo', './phpConfigSwitch.sh']);
	
	#raw_input("Press any key to set sampling rates")
	#print "*** Setting Sampling Rates ***"
	#subprocess.call(['sudo', 'php', 'setSamplingRates.php']);

	raw_input("Press any key to start monitoring on h6")
	print "*** Starting monitoring on h6 ***"
	net.getNodeByName("h6").sendCmd("python sniff.py > output/sniffing.txt 2> output/errors/sniff_error.txt");
	
	raw_input("Press any key to start traffic")
	print "*** Starting traffic..***"		
	
	with open("flows.txt") as f:
		flows = f.readlines()
	flows = [line.strip() for line in flows]
	flows = [line.split(',') for line in flows]
	
	for flow in flows:
		trafficFrom = flow[0]
		trafficTo = flow[-1]
		trafficToNum = int(trafficTo[1:])
		trafficToHex = "%0.2X" % trafficToNum
		
		print "Running on " + trafficFrom + ": " + "sudo ./sendPackets.py 00:00:00:00:00:" + trafficToHex + " 10.0.0." + str(trafficToNum) + " 0"
		net.getNodeByName(trafficFrom).popen("sudo ./sendPackets.py 00:00:00:00:00:" + trafficToHex + " 10.0.0." + str(trafficToNum) + " 0");
	
		testerr = open("testerr.txt", 'wb')		
		subprocess.call(['sudo', 'php', 'samplingEngine.php', 'ADD_FLOW', trafficFrom, trafficTo, '50'], stderr=testerr);
	
	
	raw_input("Press any key to print sampling state")
	subprocess.call(['sudo', 'php', 'samplingEngine.php', 'PRINT_STATE']);
	
	raw_input("Press any key to stop network")
	print "*** Cleaning Up.. ***"
	net.getNodeByName("h6").sendInt()
	net.stop()
	
if __name__ == '__main__':
	try:
		startTest()
	except AssertionError as ex:
		pass	
	finally:	
		FNULL = open(os.devnull, 'w')
		subprocess.call(['sudo', 'mn', '-c'], stdout=FNULL, stderr=subprocess.STDOUT);
	
	