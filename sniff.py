#!/usr/bin/python

import scapy.all as scapy
import binascii

##### Main #####

def myprn(p):
	innerPacket = scapy.Ether(p.getlayer(scapy.Raw).load)
	fromNode = str(int((innerPacket.getlayer(scapy.Ether).src.split(':'))[-1], 16))
	toNode = str(int((innerPacket.getlayer(scapy.Ether).dst.split(':'))[-1], 16))
	return fromNode + ',' + toNode + ',' + str(innerPacket.getlayer(scapy.TCP).flags) + ',' + innerPacket.getlayer(scapy.Raw).load

def myfilter(p):
	return p.haslayer(scapy.UDP) and p.haslayer(scapy.Raw) and p.getlayer(scapy.UDP).dport==5555

scapy.sniff(prn=myprn, lfilter=myfilter)
