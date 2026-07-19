#!/usr/bin/env bash

set -eu

SKIP_DOWNLOAD=false
SKIP_IMPORT_OSM=false
SKIP_IMPORT_AREAS=false
SKIP_AGGREGATE=false
SKIP_IMPORT_WIKIDATA=false
SKIP_GENERATE_FILES=false
SKIP_STATISTICS=false
TIMING_ENABLED=false

TIMING_ENTRIES=""
IMPORT_START_TIME=""

timing_now() {
    date +%s
}

timing_format_seconds() {
    total_seconds="$1"
    hours=$((total_seconds / 3600))
    minutes=$(((total_seconds % 3600) / 60))
    seconds=$((total_seconds % 60))
    printf '%02d:%02d:%02d' "$hours" "$minutes" "$seconds"
}

timing_record_section() {
    section_name="$1"
    section_start="$2"
    section_state="$3"

    if [ "$TIMING_ENABLED" != true ]; then
        return
    fi

    section_end="$(timing_now)"
    section_elapsed=$((section_end - section_start))
    TIMING_ENTRIES="${TIMING_ENTRIES}${section_name}|${section_elapsed}|${section_state}
"
}

timing_print_summary() {
    if [ "$TIMING_ENABLED" != true ] || [ -z "$IMPORT_START_TIME" ]; then
        return
    fi

    total_elapsed=$(( $(timing_now) - IMPORT_START_TIME ))

    echo
    echo "Timing summary"
    echo "--------------"

    while IFS='|' read -r section_name section_elapsed section_state; do
        [ -n "$section_name" ] || continue
        printf '%-20s %s (%s)\n' "$section_name" "$(timing_format_seconds "$section_elapsed")" "$section_state"
    done <<EOF
${TIMING_ENTRIES}
EOF

    echo "--------------"
    printf '%-20s %s\n' "Total" "$(timing_format_seconds "$total_elapsed")"
}

run_section() {
    section_name="$1"
    skip_flag="$2"
    section_function="$3"
    section_start="$(timing_now)"

    if [ "$skip_flag" = true ]; then
        timing_record_section "$section_name" "$section_start" "skipped"
        return
    fi

    "$section_function"
    timing_record_section "$section_name" "$section_start" "done"
}

CONFIG_FILE="../config/config.json"

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

parse_args() {
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
            --timing)
                TIMING_ENABLED=true
                ;;
            --help|-h)
                echo "Usage: $0 [--skip-download] [--skip-import-osm] [--skip-import-areas] [--skip-aggregate] [--skip-import-wikidata] [--skip-generate-files] [--skip-statistics] [--timing]"
                exit 0
                ;;
            *)
                echo "Error: Unknown option '$arg'" 1>&2
                exit 1
                ;;
        esac
    done
}

init_timing() {
    if [ "$TIMING_ENABLED" = true ]; then
        IMPORT_START_TIME="$(timing_now)"
    fi
}

validate_config_file() {
    if [ ! -r "$CONFIG_FILE" ]; then
        echo "Error: Could not read config file at $CONFIG_FILE" 1>&2
        exit 1
    fi
}

load_config() {
    SCHEMA="$(json_get 'db.schema' 'place_osmetymology')"
    URL_STATEFILE="$(json_get 'osm_urls.statefile' '')"
    URL_OSMFILE="$(json_get 'osm_urls.osmfile' '')"
    AREAFILE="$(json_get 'area.file' '')"
    AREAFILE_ID="$(json_get 'area.id_field' '')"
    AREAFILE_NAME="$(json_get 'area.name_field' '')"
    EXTRACTFILE="$(json_get 'extract.file' '')"
    DB_HOST="$(json_get 'db.host' '')"
    DB_PORT="$(json_get 'db.port' '')"
    DB_NAME="$(json_get 'db.name' '')"
    DB_USER="$(json_get 'db.user' '')"
    DB_PASS="$(json_get 'db.pass' '')"
    OSM2PGSQL_ENABLE_ASSOCIATED_STREET_RELATIONS="$([ "$(json_get 'import.enable_associated_street_relations' 'false')" = "true" ] && echo 1 || echo 0)"
    OSM2PGSQL_KEEP_NONUSABLE_OSM_DATA="$([ "$(json_get 'import.keep_nonusable_osm_data' 'false')" = "true" ] && echo 1 || echo 0)"

    OSMFILE="$(basename "$URL_OSMFILE")"
    STATEFILE="state.txt"
    LOCAL_DIR="../local"
    OSMFILE_FULLPATH="${LOCAL_DIR}/${OSMFILE}"
    STATEFILE_FULLPATH="${LOCAL_DIR}/${STATEFILE}"
    AREAFILE_FULLPATH="${LOCAL_DIR}/${AREAFILE}"
    EXTRACTFILE_FULLPATH="${LOCAL_DIR}/${EXTRACTFILE}"
    ORIGINAL_OSMFILE="original_${OSMFILE}"
    ORIGINAL_OSMFILE_FULLPATH="${LOCAL_DIR}/${ORIGINAL_OSMFILE}"
    FGBFILE="../www/data/names.fgb"
    CSVFILE="../www/data/names.csv"
}

validate_config() {
    if [ -z "$AREAFILE" ] && [ "$SKIP_IMPORT_AREAS" = false ]; then
        echo "Info: area.file is empty in $CONFIG_FILE; skipping area import and area-based aggregation"
        SKIP_IMPORT_AREAS=true
    fi

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
}

configure_database_env() {
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
}

validate_inputs() {
    if [ "$SKIP_IMPORT_AREAS" = false ] && [ ! -r "$AREAFILE_FULLPATH" ]; then
        echo "Error: Area file is missing or not readable: $AREAFILE_FULLPATH" 1>&2
        exit 1
    fi
}

download_section() {
    # Get OSM file
    wget "${URL_STATEFILE:?}" -O "$STATEFILE_FULLPATH"
    wget "${URL_OSMFILE:?}" -O "$OSMFILE_FULLPATH"

    if [ -n "$EXTRACTFILE" ]; then
        if ! command -v osmium >/dev/null 2>&1; then
            echo "Error: extract.file is set, but osmium is not installed or not in PATH" 1>&2
            exit 1
        fi

        if [ ! -r "$EXTRACTFILE_FULLPATH" ]; then
            echo "Error: Extract file is missing or not readable: $EXTRACTFILE_FULLPATH" 1>&2
            exit 1
        fi

        mv -f -- "$OSMFILE_FULLPATH" "$ORIGINAL_OSMFILE_FULLPATH"

        # Clip to extract only. Fall back to the full downloaded file if extraction fails.
        if ! osmium extract --polygon="$EXTRACTFILE_FULLPATH" --overwrite -o "$OSMFILE_FULLPATH" "$ORIGINAL_OSMFILE_FULLPATH"; then
            echo "Warning: osmium extract failed. Falling back to full downloaded file" 1>&2
            cp -f -- "$ORIGINAL_OSMFILE_FULLPATH" "$OSMFILE_FULLPATH"
        fi
    fi
}

import_osm_section() {
    if [ ! -s "$OSMFILE_FULLPATH" ]; then
        echo "Error: Couldn't download $OSMFILE"
        exit 1
    fi

    # Main import.
    psql -c "CREATE SCHEMA IF NOT EXISTS ${SCHEMA:?}"
    OSM2PGSQL_ENABLE_ASSOCIATED_STREET_RELATIONS="${OSM2PGSQL_ENABLE_ASSOCIATED_STREET_RELATIONS:-1}" OSM2PGSQL_KEEP_NONUSABLE_OSM_DATA="${OSM2PGSQL_KEEP_NONUSABLE_OSM_DATA:-0}" osm2pgsql --schema "${SCHEMA:?}" -d "${PGDATABASE:?}" -O flex -S nameimport.lua --drop -s "${OSMFILE_FULLPATH:?}"
}

import_areas_section() {
    # Import areas.
    ogr2ogr PG:dbname="${PGDATABASE:?}" "${AREAFILE_FULLPATH:?}" -t_srs EPSG:4326 -lco SCHEMA="${SCHEMA:?}" -nln "${SCHEMA:?}.areas" -overwrite
    # Rename fields
    psql -c "ALTER TABLE ${SCHEMA:?}.areas RENAME COLUMN ${AREAFILE_ID:?} TO area_id"
    psql -c "ALTER TABLE ${SCHEMA:?}.areas RENAME COLUMN ${AREAFILE_NAME:?} TO area_name"
}

aggregate_section() {
    AGGREGATE_SQL="aggregate_no_areas.sql"
    if [ "$SKIP_IMPORT_AREAS" = false ]; then
        HAS_AREAS_TABLE="$(psql -qtAX -c "SELECT to_regclass('${SCHEMA:?}.areas') IS NOT NULL" | tr -d '\r[:space:]')"
        if [ "$HAS_AREAS_TABLE" = "t" ]; then
            AGGREGATE_SQL="aggregate.sql"
        else
            echo "Warning: areas table not found; running aggregation without areas"
        fi
    fi
    psql -f "$AGGREGATE_SQL"
}

import_wikidata_section() {
    php wikidataimport.php --auto
}

generate_files_section() {
    FGB_TMPFILE="$(dirname "${FGBFILE:?}")/.tmp.$$.$(basename "${FGBFILE:?}")"
    CSV_TMPFILE="$(dirname "${CSVFILE:?}")/.tmp.$$.$(basename "${CSVFILE:?}")"

    rm -f -- "$FGB_TMPFILE" "$CSV_TMPFILE"

    ogr2ogr -progress "$FGB_TMPFILE" PG:dbname="${PGDATABASE:?}" -sql '@tofgb.sql' -nln 'Etymology for places'
    mv -f -- "$FGB_TMPFILE" "${FGBFILE:?}"

    ogr2ogr -progress "$CSV_TMPFILE" PG:dbname="${PGDATABASE:?}" -lco SEPARATOR=SEMICOLON -sql '@tocsv.sql'
    mv -f -- "$CSV_TMPFILE" "${CSVFILE:?}"
}

statistics_section() {
    php updatestatsfile.php
    DATE=$(date +%F)
    cp ../www/data/stats.json ../www/data/old/stats_${DATE:?}.json
    cp ../www/data/areas.json ../www/data/old/areas_${DATE:?}.json
}

main() {
    parse_args "$@"
    init_timing
    validate_config_file
    load_config
    validate_config
    configure_database_env
    validate_inputs
    run_section "download" "$SKIP_DOWNLOAD" download_section
    run_section "import-osm" "$SKIP_IMPORT_OSM" import_osm_section
    run_section "import-areas" "$SKIP_IMPORT_AREAS" import_areas_section
    run_section "aggregate" "$SKIP_AGGREGATE" aggregate_section
    run_section "import-wikidata" "$SKIP_IMPORT_WIKIDATA" import_wikidata_section
    run_section "generate-files" "$SKIP_GENERATE_FILES" generate_files_section
    run_section "statistics" "$SKIP_STATISTICS" statistics_section
    timing_print_summary
}

main "$@"

