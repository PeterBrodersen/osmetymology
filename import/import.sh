#!/bin/sh
# Remember to set $PGDATABASE to database name

# Fetch variables
source settings.sh
export PGOPTIONS="-c search_path=${SCHEMA:?}"

if [ -z "${PGDATABASE:-}" ]; then
    echo "Error: Set variable PGDATABASE in environment" 1>&2
    exit 1
fi

# Get OSM file
wget ${URL_STATEFILE:?} -O state.txt
wget ${URL_PBFFILE:?} -O $PBFFILE

if [ ! -s "$PBFFILE" ]; then
    echo "Error: Couldn't download $PBFFILE"
    exit 1
fi

# Main import.
psql -c "CREATE SCHEMA IF NOT EXISTS ${SCHEMA:?}"
osm2pgsql --schema ${SCHEMA:?} -d "${PGDATABASE:?}" -O flex -S jsonb.lua --drop -s ${PBFFILE:?}

# Import areas.
ogr2ogr PG:dbname="${PGDATABASE:?}" areas.fgb -lco SCHEMA=${SCHEMA:?} -nln "${SCHEMA:?}.areas" -overwrite

# Aggregate, split by area boundaries.
# :TODO: Allow for import without areas, then aggregate without area boundaries.
psql -f aggregate.sql

# Download and import Wikidata items. First import fetches all referred items, otherwise only fetch missing items.
# For a clean import of all items, use --cleanimport
php wikidataimport.php --auto

# Create aggregated FlatGeobuf file for web usage.
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
cp ../www/data/areas.json ../www/data/old/areas_${DATE:?}.json
