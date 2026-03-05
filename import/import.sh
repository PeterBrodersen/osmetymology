#!/bin/sh
# Remember to set $PGDATABASE to database name

if [ -z "${PGDATABASE:-}" ]; then
    echo "Error: Set variable PGDATABASE in environment" 1>&2
    exit 1
fi

PBFFILE='ile-de-france-latest.osm.pbf' 
SCHEMA='paris_osmetymology'

# Get Île-de-France OSM file (~300 MB)
wget 'https://download.geofabrik.de/europe/france/ile-de-france-updates/state.txt' -O state.txt
wget 'https://download.geofabrik.de/europe/france/ile-de-france-latest.osm.pbf' -O $PBFFILE

if [ ! -s "$PBFFILE" ]; then
    echo "Error: Couldn't download $PBFFILE"
    exit 1
fi

# Main import. Estimated time: 5 minutes
psql -c "CREATE SCHEMA IF NOT EXISTS $SCHEMA"
osm2pgsql --schema $SCHEMA -d "${PGDATABASE:?}" -O flex -S jsonb.lua -s $PBFFILE

# Import arrondissements. Takes about a second.
ogr2ogr PG:dbname="${PGDATABASE:?}" arrondissements.fgb -lco SCHEMA=$SCHEMA -nln "$SCHEMA.arrondissements" -overwrite

# Aggregate, split by arrondissement boundaries. Estimated time: A couple of seconds.
psql -f aggregate.sql

# Download and import Wikidata items. Estimated time: 5-10 minutes for first import, otherwise only fetch missing items.
# For a clean import of all items, use --cleanimport
php wikidataimport.php --auto

# Create aggregated FlatGeobuf file for web usage. Estimated time: 1-2 minutes.
FGBFILE="../www/data/noms.fgb"
CSVFILE="../www/data/noms.csv"
if [ -f "$FGBFILE" ] ; then
    rm -- "$FGBFILE"
fi
if [ -f "$CSVFILE" ] ; then
    rm -- "$CSVFILE"
fi
ogr2ogr -progress "${FGBFILE:?}" PG:dbname="${PGDATABASE:?}" -sql '@tofgb.sql' -nln 'Noms de lieux'
ogr2ogr -progress "${CSVFILE:?}" PG:dbname="${PGDATABASE:?}" -lco SEPARATOR=SEMICOLON -sql '@tocsv.sql'
php updatestatsfile.php

# Backup stats file with import date
DATE=$(date +%F)
cp ../www/data/stats.json ../www/data/old/stats_${DATE:?}.json
cp ../www/data/arrondissements.json ../www/data/old/arrondissements_${DATE:?}.json
