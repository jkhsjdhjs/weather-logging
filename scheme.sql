CREATE SCHEMA IF NOT EXISTS weather_logging;

SET search_path = weather_logging;

CREATE TABLE IF NOT EXISTS weather (
    id SERIAL PRIMARY KEY,
    ds1820_temp NUMERIC(6,3),
    am2302_temp NUMERIC(4,1),
    am2302_humidity NUMERIC(3,1),
    bmp180_temp NUMERIC(4,1),
    bmp180_pressure NUMERIC(6,2),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS weather_created_at ON weather (created_at);

CREATE OR REPLACE VIEW weather_daily AS
  SELECT
    date_trunc('day', created_at) as day,
    avg(ds1820_temp) as temp_avg,
    stddev_pop(ds1820_temp) as temp_dev,
    avg(am2302_humidity) as humidity_avg,
    stddev_pop(am2302_humidity) as humidity_dev,
    avg(bmp180_pressure) as pressure_avg,
    stddev_pop(bmp180_pressure) as pressure_dev
  FROM
    weather
  GROUP BY
    date_trunc('day', created_at)
  ORDER BY
    date_trunc('day', created_at)
;

CREATE OR REPLACE VIEW weather_hourly AS
  SELECT
    date_trunc('hour', created_at) as hour,
    avg(ds1820_temp) as temp_avg,
    stddev_pop(ds1820_temp) as temp_dev,
    avg(am2302_humidity) as humidity_avg,
    stddev_pop(am2302_humidity) as humidity_dev,
    avg(bmp180_pressure) as pressure_avg,
    stddev_pop(bmp180_pressure) as pressure_dev
  FROM
    weather
  GROUP BY
    date_trunc('hour', created_at)
  ORDER BY
    date_trunc('hour', created_at)
;
