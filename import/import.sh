#!/bin/sh
# Remember to set $PGDATABASE to database name

if [ -z "${PGDATABASE:-}" ]; then
    echo "Error: Set variable PGDATABASE in environment" 1>&2
    exit 1
fi

PBFFILE='greater-london-latest.osm.pbf'
SCHEMA='london_osmetymology'

# Get Greater London OSM file (~120 MB)
wget 'https://download.geofabrik.de/europe/united-kingdom/england/greater-london-updates/state.txt' -O state.txt
wget 'https://download.geofabrik.de/europe/united-kingdom/england/greater-london-latest.osm.pbf' -O $PBFFILE

if [ ! -s "$PBFFILE" ]; then
    echo "Error: Couldn't download $PBFFILE"
    exit 1
fi

# Main import. Estimated time: 2 minutes
psql -c "CREATE SCHEMA IF NOT EXISTS ${SCHEMA:?}"
osm2pgsql --schema ${SCHEMA:?} -d "${PGDATABASE:?}" -O flex -S jsonb.lua --drop -s ${PBFFILE:?}

# Import boroughs. Takes about a second.
ogr2ogr PG:dbname="${PGDATABASE:?}" boroughs.fgb -lco SCHEMA=${SCHEMA:?} -nln "${SCHEMA:?}.boroughs" -overwrite

# Aggregate, split by borough boundaries. Estimated time: A couple of seconds.
psql -f aggregate.sql

# Download and import Wikidata items. Estimated time: 10 seconds for first import, otherwise only fetch missing items.
# For a clean import of all items, use --cleanimport
php wikidataimport.php --auto

# Create aggregated FlatGeobuf file for web usage. Estimated time: 1-2 minutes.
FGBFILE="../www/data/names.fgb"
CSVFILE="../www/data/names.csv"
if [ -f "$FGBFILE" ] ; then
    rm -- "$FGBFILE"
fi
if [ -f "$CSVFILE" ] ; then
    rm -- "$CSVFILE"
fi
ogr2ogr -progress "${FGBFILE:?}" PG:dbname="${PGDATABASE:?}" -sql '@tofgb.sql' -nln 'Etymology for places'
ogr2ogr -progress "${CSVFILE:?}" PG:dbname="${PGDATABASE:?}" -lco SEPARATOR=SEMICOLON -sql '@tocsv.sql'
php updatestatsfile.php

# Backup stats file with import date
DATE=$(date +%F)
cp ../www/data/stats.json ../www/data/old/stats_${DATE:?}.json
cp ../www/data/boroughs.json ../www/data/old/boroughs_${DATE:?}.json
