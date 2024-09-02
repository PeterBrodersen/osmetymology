#!/bin/sh
# Remember to set $PGDATABASE to database name
# Remember to create schema osmetymology - could be done automatically?

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
wget 'https://api.dataforsyningen.dk/kommuner?format=geojson' -O kommuner.geojson

# Main import. Estimated time: 10-30 minutes
osm2pgsql -d "${PGDATABASE:?}" -O flex -S jsonb.lua -s denmark-latest.osm.pbf

# Import municipalities. Takes about 20 seconds.
ogr2ogr PG:dbname="${PGDATABASE:?}" kommuner.geojson -lco SCHEMA=osmetymology -nln 'osmetymology.municipalities' -overwrite
psql -f municipalitybuffers.sql

# Aggregate, split by municipality boundaries. Estimated time: 10-15 minutes. Perhaps the geometry should be simplified.
psql -f aggregate.sql

# Download and import all Wikidata items. Estimated time: 5-10 minutes.
php wikidataimport.php

# Create aggregated FlatGeobuf file for web usage. Estimated time: 1-2 minutes.
FGBFILE="../www/data/aggregate.fgb"
CSVFILE="../www/data/navne.csv"
if [ -f "$FGBFILE" ] ; then
    rm -- "$FGBFILE"
fi
if [ -f "$CSVFILE" ] ; then
    rm -- "$CSVFILE"
fi
ogr2ogr "${FGBFILE:?}" PG:dbname="${PGDATABASE:?}" -sql '@tofgb.sql' -nln 'Stednavne'
ogr2ogr "${CSVFILE:?}" PG:dbname="${PGDATABASE:?}" -sql '@tocsv.sql'
php updatestatsfile.php