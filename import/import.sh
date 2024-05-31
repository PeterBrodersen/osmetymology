#!/bin/sh
wget https://download.geofabrik.de/europe/denmark-latest.osm.pbf -O denmark-latest.osm.pbf
#ogr2ogr denmark.fgb denmark-latest.osm.pbf lines
# Remember to create schema osmetymology
# set $PGDATABASE to database name
osm2pgsql -d "$PGDATABASE" -O flex -S jsonb.lua -s denmark-latest.osm.pbf
