#!/bin/sh
# Remember to set $PGDATABASE to database name
# Remember to create schema osmetymology - should be done automatically?

if [ -z "${PGDATABASE:-}" ]; then
    echo "Error: Set variable PGDATABASE in environment" 1>&2
    exit 1
fi

wget https://download.geofabrik.de/europe/denmark-latest.osm.pbf -O denmark-latest.osm.pbf

# Main import. Takes about 10-30 minutes
osm2pgsql -d "$PGDATABASE" -O flex -S jsonb.lua -s denmark-latest.osm.pbf

# Import municipalities. Takes a couple of seconds. 
# TODO: Create the "kommuner.fgb" for distribution - or even better, import municipalities with geometry from authoritative source!
ogr2ogr PG:dbname="$PGDATABASE" kommuner.fgb -lco SCHEMA=osmetymology -nln 'osmetymology.municipalities' -overwrite

# Aggregate, split by municipality boundaries. Takes about 5-10 minutes. Perhaps the geometry should be simplified.
psql -f aggregate.sql

# Create aggregated FlatGeobuf file for web usage
FGBFILE="../www/data/aggregate.fgb"
if [ -f "$FGBFILE" ] ; then
    rm -- "$FGBFILE"
fi
ogr2ogr "$FGBFILE" PG:dbname="$PGDATABASE" -oo TABLES=osmetymology.ways_agg -select "name, name:etymology, name:etymology:wikipedia, name:etymology:wikidata, geom"
