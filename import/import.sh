#!/usr/bin/env bash

set -eu

SKIP_DOWNLOAD=false
SKIP_IMPORT_OSM=false
SKIP_IMPORT_AREAS=false
SKIP_AGGREGATE=false
SKIP_IMPORT_WIKIDATA=false
SKIP_GENERATE_FILES=false
SKIP_STATISTICS=false

for arg in "$@"; do
    case "$arg" in
        --skip-download)
            SKIP_DOWNLOAD=true
            ;;
        --skip-import-osm)
            SKIP_IMPORT_OSM=true
            ;;
        --skip-import-areas)
            SKIP_IMPORT_AREAS=true
            ;;
        --skip-aggregate)
            SKIP_AGGREGATE=true
            ;;
        --skip-import-wikidata)
            SKIP_IMPORT_WIKIDATA=true
            ;;
        --skip-generate-files)
            SKIP_GENERATE_FILES=true
            ;;
        --skip-statistics)
            SKIP_STATISTICS=true
            ;;
        --help|-h)
            echo "Usage: $0 [--skip-download] [--skip-import-osm] [--skip-import-areas] [--skip-aggregate] [--skip-import-wikidata] [--skip-generate-files] [--skip-statistics]"
            exit 0
            ;;
        *)
            echo "Error: Unknown option '$arg'" 1>&2
            exit 1
            ;;
    esac
done

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
URL_OSMFILE="$(json_get 'osm_urls.osmfile' '')"
AREAFILE="$(json_get 'area.file' '')"
AREAFILE_ID="$(json_get 'area.id_field' '')"
AREAFILE_NAME="$(json_get 'area.name_field' '')"
DB_HOST="$(json_get 'db.host' '')"
DB_PORT="$(json_get 'db.port' '')"
DB_NAME="$(json_get 'db.name' '')"
DB_USER="$(json_get 'db.user' '')"
DB_PASS="$(json_get 'db.pass' '')"
OSM2PGSQL_ENABLE_ASSOCIATED_STREET_RELATIONS="$([ "$(json_get 'import.enable_associated_street_relations' 'false')" = "true" ] && echo 1 || echo 0)"

if [ "$SKIP_DOWNLOAD" = false ] && { [ -z "$URL_STATEFILE" ] || [ -z "$URL_OSMFILE" ]; }; then
    echo "Error: Missing osm_urls values in $CONFIG_FILE (required unless --skip-download is used)" 1>&2
    exit 1
fi

if [ "$SKIP_IMPORT_OSM" = false ] && [ -z "$URL_OSMFILE" ]; then
    echo "Error: Missing osm_urls.osmfile in $CONFIG_FILE (required unless --skip-import-osm is used)" 1>&2
    exit 1
fi

if [ "$SKIP_IMPORT_AREAS" = false ] && { [ -z "$AREAFILE" ] || [ -z "$AREAFILE_ID" ] || [ -z "$AREAFILE_NAME" ]; }; then
    echo "Error: Missing area values in $CONFIG_FILE (required unless --skip-import-areas is used)" 1>&2
    exit 1
fi

OSMFILE="$(basename "$URL_OSMFILE")"
STATEFILE="state.txt"
LOCAL_DIR="../local"
OSMFILE_FULLPATH="${LOCAL_DIR}/${OSMFILE}"
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

if [ "$SKIP_IMPORT_AREAS" = false ] && [ ! -r "$AREAFILE_FULLPATH" ]; then
    echo "Error: Area file is missing or not readable: $AREAFILE_FULLPATH" 1>&2
    exit 1
fi

if [ "$SKIP_DOWNLOAD" = false ]; then
    # Get OSM file
    wget "${URL_STATEFILE:?}" -O "$STATEFILE_FULLPATH"
    wget "${URL_OSMFILE:?}" -O "$OSMFILE_FULLPATH"
fi

if [ "$SKIP_IMPORT_OSM" = false ]; then
    if [ ! -s "$OSMFILE_FULLPATH" ]; then
        echo "Error: Couldn't download $OSMFILE"
        exit 1
    fi
    # Main import.
    psql -c "CREATE SCHEMA IF NOT EXISTS ${SCHEMA:?}"
    OSM2PGSQL_ENABLE_ASSOCIATED_STREET_RELATIONS="${OSM2PGSQL_ENABLE_ASSOCIATED_STREET_RELATIONS:-1}" osm2pgsql --schema "${SCHEMA:?}" -d "${PGDATABASE:?}" -O flex -S nameimport.lua --drop -s "${OSMFILE_FULLPATH:?}"
fi

if [ "$SKIP_IMPORT_AREAS" = false ]; then
    # Import areas.
    ogr2ogr PG:dbname="${PGDATABASE:?}" "${AREAFILE_FULLPATH:?}" -t_srs EPSG:4326 -lco SCHEMA="${SCHEMA:?}" -nln "${SCHEMA:?}.areas" -overwrite
    # Rename fields
    psql -c "ALTER TABLE ${SCHEMA:?}.areas RENAME COLUMN ${AREAFILE_ID:?} TO area_id"
    psql -c "ALTER TABLE ${SCHEMA:?}.areas RENAME COLUMN ${AREAFILE_NAME:?} TO area_name"
fi


# Aggregate, split by area boundaries.
# :TODO: Allow for import without areas, then aggregate without area boundaries.
if [ "$SKIP_AGGREGATE" = false ]; then
    psql -f aggregate.sql
fi

# Download and import Wikidata items. First import fetches all referred items, otherwise only fetch missing items.
# For a clean import of all items, use --cleanimport
if [ "$SKIP_IMPORT_WIKIDATA" = false ]; then
    php wikidataimport.php --auto
fi

# Create aggregated FlatGeobuf file for web usage.
FGBFILE="../www/data/names.fgb"
CSVFILE="../www/data/names.csv"
if [ "$SKIP_GENERATE_FILES" = false ]; then
    FGB_TMPFILE="$(dirname "${FGBFILE:?}")/.tmp.$$.$(basename "${FGBFILE:?}")"
    CSV_TMPFILE="$(dirname "${CSVFILE:?}")/.tmp.$$.$(basename "${CSVFILE:?}")"

    rm -f -- "$FGB_TMPFILE" "$CSV_TMPFILE"

    ogr2ogr -progress "$FGB_TMPFILE" PG:dbname="${PGDATABASE:?}" -sql '@tofgb.sql' -nln 'Etymology for places'
    mv -f -- "$FGB_TMPFILE" "${FGBFILE:?}"

    ogr2ogr -progress "$CSV_TMPFILE" PG:dbname="${PGDATABASE:?}" -lco SEPARATOR=SEMICOLON -sql '@tocsv.sql'
    mv -f -- "$CSV_TMPFILE" "${CSVFILE:?}"
fi

if [ "$SKIP_STATISTICS" = false ]; then
    php updatestatsfile.php
    # Backup stats file with import date
    DATE=$(date +%F)
    cp ../www/data/stats.json ../www/data/old/stats_${DATE:?}.json
    cp ../www/data/areas.json ../www/data/old/areas_${DATE:?}.json
fi

