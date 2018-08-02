<?php

//read devices
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

$ds1820_raw = file_get_contents("/sys/bus/w1/devices/10-000802e481ae/w1_slave");
if($ds1820_raw !== false && preg_match("/crc=.{3}YES.*t=(\d+)$/s", $ds1820_raw, $matches))
    $ds1820->temp = $matches[1] / 1000;
else
    fwrite(STDERR, "Couldn't read DS1820!\n");

if(preg_match("/(\d{2}\.\d{2}).+(\d{2}\.\d{2})/", `lol_dht22/loldht 0 10 0`, $matches)) {
    $am2302->humidity = floatval($matches[1]);
    $am2302->temp = floatval($matches[2]);
}
else
    fwrite(STDERR, "Couldn't read AM2302!\n");

$bmp180_json = json_decode(`BMP180/read.py`);
if(!is_null($bmp180_json))
    $bmp180 = $bmp180_json;
else
    fwrite(STDERR, "Couldn't read BMP180!\n");


//insert values
$dbh = new PDO("pgsql:dbname=home;user=home;host=/home/jkhsjdhjs/postgresql-remote");
$dbh->exec("SET search_path = weather_logging");

$query = $dbh->prepare("INSERT INTO weather (ds1820_temp, am2302_temp, am2302_humidity, bmp180_temp, bmp180_pressure) VALUES (?, ?, ?, ?, ?)");

$query->bindValue(1, $ds1820->temp);
$query->bindValue(2, $am2302->temp);
$query->bindValue(3, $am2302->humidity);
$query->bindValue(4, $bmp180->temp);
$query->bindValue(5, $bmp180->pressure);

if(!$query->execute()) {
    fwrite(STDERR, "Insertion failed!\n");
    exit(1);
}
