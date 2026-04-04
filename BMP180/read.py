#!/usr/bin/env python3
# -*- coding: utf-8 -*-

from Adafruit_BMP import BMP085
import json

bmp = BMP085.BMP085()

temp = bmp.read_temperature()
pressure = bmp.read_pressure()

print(json.dumps({'temp': temp, 'pressure': pressure / 100.0}))
