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

DROP FUNCTION weather_quantiles(integer,timestamp with time zone,timestamp with time zone);

CREATE FUNCTION weather_quantiles
  ( quantiles  INTEGER
  , start_time TIMESTAMPTZ DEFAULT NULL
  , end_time   TIMESTAMPTZ DEFAULT NULL
  )
  RETURNS TABLE
  ( "time"          TIMESTAMPTZ
  , temp_avg        NUMERIC(6,3)
  , temp_stddev     NUMERIC(6,3)
  , humidity_avg    NUMERIC(3,1)
  , humidity_stddev NUMERIC(3,1)
  , pressure_avg    NUMERIC(6,2)
  , pressure_stddev NUMERIC(6,2)
  )
  AS
  $$
    WITH f AS (
      SELECT
      created_at,
      ds1820_temp as temp,
      am2302_humidity as humidity,
      bmp180_pressure as pressure,
      row_number() OVER (ORDER BY created_at ASC) as n
      FROM weather
      WHERE
        ( $2 is null or created_at > $2)
        and
        ( $3 is null or created_at <= $3)
    )
    SELECT
      percentile_disc(0.5) WITHIN GROUP (ORDER BY created_at) as time,
      AVG(temp) as temp,
      stddev_pop(temp) as temp_stddev,
      AVG(humidity) as humidity,
      stddev_pop(humidity) as humidity_stddev,
      AVG(pressure) as pressure,
      stddev_pop(pressure) as pressure_stddev
    FROM
      f
    GROUP BY
      (n / ((SELECT COUNT(*) FROM f) / GREATEST($1 - 1, 1)))
    ORDER BY
      time ASC
   $$ language SQL
;
