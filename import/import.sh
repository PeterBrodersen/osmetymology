#!/bin/sh
if [ -z "${PGDATABASE:-}" ]; then
    echo "Error: Set variable PGDATABASE in environment" 1>&2
    exit 1
fi

wget https://download.geofabrik.de/europe/denmark-latest.osm.pbf -O denmark-latest.osm.pbf
#ogr2ogr denmark.fgb denmark-latest.osm.pbf lines
# Remember to create schema osmetymology
# set $PGDATABASE to database name
osm2pgsql -d "$PGDATABASE" -O flex -S jsonb.lua -s denmark-latest.osm.pbf

# Import municipalities
ogr2ogr PG:dbname="$PGDATABASE" kommuner.fgb -lco SCHEMA=osmetymology -nln 'osmetymology.municipalities' -overwrite

# Aggregate, split by municipality boundaries
psql -f aggregate.sql

# Create aggregated FlatGeobuf file for web usage
FGBFILE="../www/data/aggregate.fgb"
if [ -f "$FGBFILE" ] ; then
    rm -- "$FGBFILE"
fi
ogr2ogr "$FGBFILE" PG:dbname="$PGDATABASE" -oo TABLES=osmetymology.ways_agg -select "name, name:etymology, name:etymology:wikipedia, name:etymology:wikidata, geom"
