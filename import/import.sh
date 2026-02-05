#!/bin/sh
# Remember to set $PGDATABASE to database name

if [ -z "${PGDATABASE:-}" ]; then
    echo "Error: Set variable PGDATABASE in environment" 1>&2
    exit 1
fi

# :TODO: Switch to cURL for conditional requests - no need to fetch large files again
# 
# curl -o 'denmark-latest.osm.pbf' -z 'denmark-latest.osm.pbf' 'https://download.geofabrik.de/europe/denmark-latest.osm.pbf'
# https://download.geofabrik.de/europe/denmark-latest.osm.pbf

# Get Denmark OSM file (~400-450 MB) and Danish municipalities with geometry (~115 MB)
wget 'https://download.geofabrik.de/europe/denmark-updates/state.txt' -O state.txt
wget 'https://download.geofabrik.de/europe/denmark-latest.osm.pbf' -O denmark-latest.osm.pbf
#wget 'https://api.dataforsyningen.dk/kommuner?format=geojson' -O kommuner.geojson

if [ ! -s "denmark-latest.osm.pbf" ]; then
    echo "Error: Couldn't download denmark-latest.osm.pbf"
    exit 1
fi

# Main import. Estimated time: 10-20 minutes
psql -c 'CREATE SCHEMA IF NOT EXISTS osmetymology'
osm2pgsql --schema osmetymology -d "${PGDATABASE:?}" -O flex -S jsonb.lua -s denmark-latest.osm.pbf

# Import municipalities. Takes about a second.
ogr2ogr PG:dbname="${PGDATABASE:?}" kommuner_buffer_merged.fgb -lco SCHEMA=osmetymology -nln 'osmetymology.municipalities' -overwrite

# Aggregate, split by municipality boundaries. Estimated time: 2-4 minutes.
psql -f aggregate.sql

# Download and import Wikidata items. Estimated time: 5-10 minutes for first import, otherwise only fetch missing items.
# For a clean import of all items, use --cleanimport
php wikidataimport.php --auto

# Create aggregated FlatGeobuf file for web usage. Estimated time: 1-2 minutes.
FGBFILE="../www/data/navne.fgb"
CSVFILE="../www/data/navne.csv"
if [ -f "$FGBFILE" ] ; then
    rm -- "$FGBFILE"
fi
if [ -f "$CSVFILE" ] ; then
    rm -- "$CSVFILE"
fi
ogr2ogr -progress "${FGBFILE:?}" PG:dbname="${PGDATABASE:?}" -sql '@tofgb.sql' -nln 'Stednavne'
ogr2ogr -progress "${CSVFILE:?}" PG:dbname="${PGDATABASE:?}" -lco SEPARATOR=SEMICOLON -sql '@tocsv.sql'
php updatestatsfile.php

# Backup stats file with import date
DATE=$(date +%F)
cp ../www/data/stats.json ../www/data/old/stats_${DATE:?}.json
cp ../www/data/municipalities.json ../www/data/old/municipalities_${DATE:?}.json
