#!/usr/bin/env bash

set -eu

CONFIG_FILE="../config/config.json"

if [ ! -r "$CONFIG_FILE" ]; then
    echo "Error: Could not read config file at $CONFIG_FILE" 1>&2
    exit 1
fi

json_get() {
    key="$1"
    default_value="${2:-}"

    if command -v jq >/dev/null 2>&1; then
        value=$(jq -er --arg key "$key" 'getpath($key | split(".")) // empty' "$CONFIG_FILE" 2>/dev/null || true)
    elif command -v python3 >/dev/null 2>&1; then
        value=$(python3 -c 'import json,sys; data=json.load(open(sys.argv[1], encoding="utf-8")); cur=data; [cur:=cur.get(part) if isinstance(cur,dict) else None for part in sys.argv[2].split(".")]; print("" if cur is None else cur)' "$CONFIG_FILE" "$key" 2>/dev/null || true)
    else
        echo "Error: Neither jq nor python3 is available to parse $CONFIG_FILE" 1>&2
        exit 1
    fi

    if [ -n "$value" ]; then
        printf '%s\n' "$value"
    else
        printf '%s\n' "$default_value"
    fi
}

SCHEMA="$(json_get 'db.schema' 'place_osmetymology')"
URL_STATEFILE="$(json_get 'osm_urls.statefile' '')"
URL_PBFFILE="$(json_get 'osm_urls.osmfile' '')"
AREAFILE="$(json_get 'area.file' '')"
AREAFILE_ID="$(json_get 'area.id_field' '')"
AREAFILE_NAME="$(json_get 'area.name_field' '')"
DB_HOST="$(json_get 'db.host' '')"
DB_PORT="$(json_get 'db.port' '')"
DB_NAME="$(json_get 'db.name' '')"
DB_USER="$(json_get 'db.user' '')"
DB_PASS="$(json_get 'db.pass' '')"

if [ -z "$URL_STATEFILE" ] || [ -z "$URL_PBFFILE" ] || [ -z "$AREAFILE" ] || [ -z "$AREAFILE_ID" ] || [ -z "$AREAFILE_NAME" ]; then
    echo "Error: Missing required import values in $CONFIG_FILE" 1>&2
    exit 1
fi

PBFFILE="$(basename "$URL_PBFFILE")"
STATEFILE="state.txt"
LOCAL_DIR="../local"
PBFFILE_FULLPATH="${LOCAL_DIR}/${PBFFILE}"
STATEFILE_FULLPATH="${LOCAL_DIR}/${STATEFILE}"
AREAFILE_FULLPATH="${LOCAL_DIR}/${AREAFILE}"

# Allow environment overrides while defaulting to values from config.json.
: "${PGHOST:=$DB_HOST}"
: "${PGPORT:=$DB_PORT}"
: "${PGUSER:=$DB_USER}"
: "${PGPASSWORD:=$DB_PASS}"
: "${PGDATABASE:=$DB_NAME}"

export PGHOST PGPORT PGUSER PGPASSWORD PGDATABASE
export PGOPTIONS="-c search_path=${SCHEMA:?},public"

if [ -z "${PGDATABASE:-}" ]; then
    echo "Error: Missing database name. Set db.name in config/config.json or PGDATABASE in environment" 1>&2
    exit 1
fi

# Get OSM file
wget "${URL_STATEFILE:?}" -O "$STATEFILE_FULLPATH"
wget "${URL_PBFFILE:?}" -O "$PBFFILE_FULLPATH"

if [ ! -s "$PBFFILE_FULLPATH" ]; then
    echo "Error: Couldn't download $PBFFILE"
    exit 1
fi

# Main import.
psql -c "CREATE SCHEMA IF NOT EXISTS ${SCHEMA:?}"
osm2pgsql --schema "${SCHEMA:?}" -d "${PGDATABASE:?}" -O flex -S nameimport.lua --drop -s "${PBFFILE_FULLPATH:?}"

# Import areas.
ogr2ogr PG:dbname="${PGDATABASE:?}" "${AREAFILE_FULLPATH:?}" -lco SCHEMA="${SCHEMA:?}" -nln "${SCHEMA:?}.areas" -overwrite
# Rename fields
psql -c "ALTER TABLE ${SCHEMA:?}.areas RENAME COLUMN ${AREAFILE_ID:?} TO area_id"
psql -c "ALTER TABLE ${SCHEMA:?}.areas RENAME COLUMN ${AREAFILE_NAME:?} TO area_name"


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
