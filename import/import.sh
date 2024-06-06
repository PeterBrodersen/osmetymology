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

# Create aggregated FlatGeobuf file for web usage. Takes about 5 seconds.
FGBFILE="../www/data/aggregate.fgb"
CSVFILE="../www/data/navne.csv"
if [ -f "$FGBFILE" ] ; then
    rm -- "$FGBFILE"
fi
if [ -f "$CSVFILE" ] ; then
    rm -- "$CSVFILE"
fi
ogr2ogr "${FGBFILE:?}" PG:dbname="${PGDATABASE:?}" -sql 'SELECT w.id, w.name, w."name:etymology", w."name:etymology:wikipedia", w."name:etymology:wikidata", w.municipality_code, w.geom, m.navn AS municipality_name, wd.name AS wikidata_label, wd.description AS wikidata_description FROM osmetymology.ways_agg w LEFT JOIN osmetymology.municipalities m ON w.municipality_code = m.kode LEFT JOIN osmetymology.wikidata wd ON w."name:etymology:wikidata" = wd.itemid'

ogr2ogr "${CSVFILE:?}" PG:dbname="${PGDATABASE:?}" -sql 'SELECT w.id, w.name, w."name:etymology", w."name:etymology:wikipedia", w."name:etymology:wikidata", w.municipality_code, m.navn AS municipality_name, wd.name AS wikidata_label, wd.description AS wikidata_description, ST_X(ST_Centroid(geom)) AS centroid_longitude, ST_Y(ST_Centroid(geom)) AS centroid_latitude FROM osmetymology.ways_agg w LEFT JOIN osmetymology.municipalities m ON w.municipality_code = m.kode LEFT JOIN osmetymology.wikidata wd ON w."name:etymology:wikidata" = wd.itemid'
