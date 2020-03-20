#!/usr/bin/env php
<?php

class SensorData {
    function __construct($temperature = null, $humidity = null, $pressure = null) {
        $this->temperature = $temperature;
        $this->humidity = $humidity;
        $this->pressure = $pressure;
    }
}

class Sensor {
    public $data = null;
    function __construct($name, $read_fnc) {
        $this->name = $name;
        $this->read_fnc = $read_fnc;
    }
    function read() {
        $data = call_user_func($this->read_fnc);
        if($data === false)
            return false;
        return $this->data = $data;
    }
    function check_sanity() {
        $sanity = [];
        if($this->data->temperature)
            $sanity[] = check_temperature($this->data->temperature);
        if($this->data->humidity)
            $sanity[] = check_humidity($this->data->humidity);
        if($this->data->pressure)
            $sanity[] = check_pressure($this->data->pressure);
        return !in_array(false, $sanity);
    }
}

// function for http requests
function fetch($url, $method = "GET", $data = null, $content_type = "application/x-www-form-urlencoded") {
    $context = [
        "method" => $method,
        "protocol_version" => "1.1"
    ];
    $headers = [
        "Content-Type: " . $content_type,
        "Connection: close"
    ];
    if($data) {
        $context["content"]
            = $content_type === "application/json"
            ? json_encode($data)
            : http_build_query($data);
        array_push($headers, "Content-Length: " . strlen($context["content"]));
    }
    for($i = 4; $i < func_num_args(); $i++)
        array_push($headers, func_get_arg($i));
    if($headers)
        $context["header"] = $headers;
    return file_get_contents($url, false, stream_context_create(["http" => $context]));
}

// sensor reading functions
function read_ds1820() {
    $ds1820_raw = file_get_contents("/sys/bus/w1/devices/10-000802e481ae/w1_slave");
    if($ds1820_raw !== false && preg_match("/crc=.{3}YES.*t=(\d+)$/s", $ds1820_raw, $matches))
        return new SensorData($matches[1] / 1000);
    return false;
}

function read_am2302() {
    if(preg_match("/(\d{2}\.\d{2}).+(\d{2}\.\d{2})/", `lol_dht22/loldht 0 10 0`, $matches))
        return new SensorData(floatval($matches[2]), floatval($matches[1]));
    return false;
}

function read_bmp180() {
    $bmp180_data = json_decode(`BMP180/read.py`);
    if(!is_null($bmp180_data))
        return new SensorData($bmp180_data->temp, null, $bmp180_data->pressure);
    return false;
}

// sanity checks
function check_temperature($temp) {
    return $temp > -20 && $temp < 60;
}

function check_humidity($humidty) {
    return true;
}

function check_pressure($pressure) {
    return $pressure > 800 && $pressure < 1200;
}

function read_sensor($sensor, $tries, $delay) {
    for($i = 0; true; $i++) {
        if($sensor->read() !== false)
            if($sensor->check_sanity())
                return true;
            else
                fwrite(STDERR, "sanity check");
        else
            fwrite(STDERR, "reading");
        fwrite(STDERR, " failed for " . $sensor->name . PHP_EOL . "data: " . json_encode($sensor->data) . PHP_EOL);
        if($i >= $tries - 1) {
            fwrite(STDERR, "$tries readings failed for $sensor->name! ");
            if($sensor->data !== null) {
                fwrite(STDERR, "using last data that failed the sanity check." . PHP_EOL);
                return true;
            }
            else {
                fwrite(STDERR, "aborting." . PHP_EOL);
                return false;
            }
        }
        fwrite(STDERR, "retrying in $delay seconds..." . PHP_EOL);
        sleep($delay);
    }
}


/* --------------------- */
/* --- program start --- */
/* --------------------- */

const API_KEY = "";
const SENSOR_READ_DELAY = 5;
const SENSOR_READ_TRIES = 3;
const DEVIATION_CHECK_TRIES = 2;
const HIGH_DEVIATION = 5;
const COMPLETE_REREAD_TRIES = 1;
$SENSORS = [
    new Sensor("DS1820", 'read_ds1820'),
    new Sensor("AM2302", 'read_am2302'),
    new Sensor("BMP180", 'read_bmp180')
];

// read sensors and do simple sanity checks
$reread = true;
for($retry = 0; $retry <= COMPLETE_REREAD_TRIES && $reread; $retry++) {
    $reread = false;
    foreach($SENSORS as $sensor) {
        if(!read_sensor($sensor, SENSOR_READ_TRIES, SENSOR_READ_DELAY))
            exit(1);
    }

    // temperature sanity check by checking the deviation from median
    $repeat = true;
    for($i = 0; $i <= DEVIATION_CHECK_TRIES && $repeat; $i++) {
        $repeat = false;
        $temperatures = array_map(function($s) { return $s->data->temperature; }, $SENSORS);
        sort($temperatures);
        $median = $temperatures[ceil(count($temperatures) / 2 - 1)];
        foreach($SENSORS as $sensor) {
            // if deviation is too high, re-read
            if(abs($sensor->data->temperature - $median) > HIGH_DEVIATION) {
                fwrite(STDERR, "$sensor->name temperature has a high deviation from median" . PHP_EOL);
                fwrite(STDERR, "data: " . json_encode($sensor->data) . " median: $median" . PHP_EOL);
                if($i >= DEVIATION_CHECK_TRIES) {
                    if($retry >= COMPLETE_REREAD_TRIES)
                        fwrite(STDERR, "ignoring..." . PHP_EOL);
                    else
                        fwrite(STDERR, "re-reading all sensors..." . PHP_EOL);
                    $reread = true;
                    break;
                }
                fwrite(STDERR, "re-reading in " . SENSOR_READ_DELAY . " seconds..." . PHP_EOL);
                sleep(SENSOR_READ_DELAY);
                // it's impossible for this to return false since it already has data cached
                read_sensor($sensor, SENSOR_READ_TRIES, SENSOR_READ_DELAY);
                $repeat = true;
                break;
            }
        }
    }
}

[$ds1820, $am2302, $bmp180] = array_map(function($s) { return $s->data; }, $SENSORS);

$response = fetch("https://weather.totally.rip/api/v2/insert.php", "POST", [
    "secret" => API_KEY,
    "ds1820_temp" => $ds1820->temperature,
    "am2302_temp" => $am2302->temperature,
    "am2302_humidity" => $am2302->humidity,
    "bmp180_temp" => $bmp180->temperature,
    "bmp180_pressure" => $bmp180->pressure
]);

if($response === false) {
    fwrite(STDERR, "Failed to insert data!" . PHP_EOL);
    exit(1);
}

$response = json_decode($response);
if($response === null) {
    fwrite(STDERR, "Failed to decode api response!" . PHP_EOL);
    exit(1);
}

if($response->error !== null) {
    fwrite(STDERR, "API error: " . json_encode($response->error) . PHP_EOL);
    exit(1);
}
