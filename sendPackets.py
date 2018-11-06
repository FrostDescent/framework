#!/usr/bin/python

import scapy.all as scapy
import time
import os
import sys

#### Config ####

if(len(sys.argv) != 4):
	print "Usage: " + sys.argv[0] + " [dstMac] [dstIP] [PPS]"	
	sys.exit()

dstMAC = sys.argv[1]
dstIP = sys.argv[2]
PPS = float(sys.argv[3])

srcMAC = os.popen('ifconfig | grep HWaddr | cut -dH -f2 | cut -d\  -f2').read().strip()
srcIP = os.popen('ifconfig | grep "inet addr" | cut -d: -f2 | cut -d\  -f1 | head -n1').read().strip()
thisHost = os.popen('ifconfig | sed -n \'s/.*\\(h[0-9]*\\)-eth.*/\\1/p\'').read().strip()

dataPrefix = "hello "

##### Main #####

headers = scapy.Ether(src=srcMAC, dst=dstMAC) / scapy.IP(src=srcIP, dst=dstIP)/scapy.TCP(flags="A", sport=12345, dport=5555)

s = scapy.conf.L2socket()
i = 1
endTime = time.time() + 10
while time.time() <= endTime:
	p = headers / (dataPrefix + str(i))
	i = i+1
	#scapy.sendp(p, verbose=0)
	s.send(p)
	#time.sleep(1.0/PPS)
	#time.sleep(1.0/700)

with open("output/sent_packets/" + thisHost + "-h" + dstIP.split('.')[-1] + ".txt", "w") as text_file:
	text_file.write(str(i))
	

