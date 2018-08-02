#!/usr/bin/python
# -*- coding: utf-8 -*-

from Adafruit_BMP085 import BMP085
import json

bmp = BMP085(0x77)

temp = bmp.readTemperature()
pressure = bmp.readPressure()

print json.dumps({'temp': temp, 'pressure': pressure / 100.0})