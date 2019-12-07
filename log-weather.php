<?php

const API_KEY = "";

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

function sensor_sanity_check($sane, $sensor) {
    static $tries = 0;
    $delay = 5;
    if(!$sane && $tries < 2) {
        $tries++;
        fwrite(STDERR, "Sanity check failed for $sensor!" . PHP_EOL);
        fwrite(STDERR, "Retrying in $delay seconds..." . PHP_EOL);
        sleep($delay);
        return false;
    }
    if($tries >= 2)
        fwrite(STDERR, "Sanity check failed for $sensor for the third time! Ignoring..." . PHP_EOL);
    $tries = 0;
    return true;
}

// read sensors
$ds1820 = (object) [
    "temp" => null
];
$am2302 = (object) [
    "temp" => null,
    "humidity" => null
];
$bmp180 = (object) [
    "temp" => null,
    "pressure" => null
];

do {
    $ds1820_raw = file_get_contents("/sys/bus/w1/devices/10-000802e481ae/w1_slave");
    if($ds1820_raw !== false && preg_match("/crc=.{3}YES.*t=(\d+)$/s", $ds1820_raw, $matches))
        $ds1820->temp = $matches[1] / 1000;
    else
        fwrite(STDERR, "Couldn't read DS1820!" . PHP_EOL);
} while(!sensor_sanity_check(check_temperature($ds1820->temp), "DS1820"));

do {
    if(preg_match("/(\d{2}\.\d{2}).+(\d{2}\.\d{2})/", `lol_dht22/loldht 0 10 0`, $matches)) {
        $am2302->humidity = floatval($matches[1]);
        $am2302->temp = floatval($matches[2]);
    }
    else
        fwrite(STDERR, "Couldn't read AM2302!\n");
} while(!sensor_sanity_check(check_temperature($am2302->temp) && check_humidity($am2302->humidity), "AM2302"));

do {
    $bmp180_json = json_decode(`BMP180/read.py`);
    if(!is_null($bmp180_json))
        $bmp180 = $bmp180_json;
    else
        fwrite(STDERR, "Couldn't read BMP180!\n");
} while(!sensor_sanity_check(check_temperature($bmp180->temp) && check_pressure($bmp180->pressure), "BMP180"));


$response = fetch("https://weather.totally.rip/api/v2/insert.php", "POST", [
    "secret" => API_KEY,
    "ds1820_temp" => $ds1820->temp,
    "am2302_temp" => $am2302->temp,
    "am2302_humidity" => $am2302->humidity,
    "bmp180_temp" => $bmp180->temp,
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
