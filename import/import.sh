#!/bin/sh
wget https://download.geofabrik.de/europe/denmark-latest.osm.pbf -O denmark-latest.osm.pbf
#ogr2ogr denmark.fgb denmark-latest.osm.pbf lines
# Remember to create schema osmetymology
# set $PGDATABASE to database name
osm2pgsql -d "$PGDATABASE" -O flex -S jsonb.lua -s denmark-latest.osm.pbf
psql -f aggregate.sql

# Create aggregated file
FGBFILE="../www/data/aggregate.fgb"
if [ -f "$FGBFILE" ] ; then
    rm -- "$FGBFILE"
fi

ogr2ogr ../www/data/aggregate.fgb PG:dbname=penguin -oo TABLES=osmetymology.ways_agg -select "name, name:etymology, name:etymology:wikipedia, name:etymology:wikidata, geom"
