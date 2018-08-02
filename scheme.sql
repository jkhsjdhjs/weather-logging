CREATE TABLE weather (
    id SERIAL PRIMARY KEY,
    ds1820_temp NUMERIC(6,3),
    am2302_temp NUMERIC(4,1),
    am2302_humidity NUMERIC(3,1),
    bmp180_temp NUMERIC(4,1),
    bmp180_pressure NUMERIC(6,2),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX ON weather (created_at);
