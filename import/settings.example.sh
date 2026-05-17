# Settings read by bash scripts.
# Copy to settings.sh and replace for own values

URL_STATEFILE='https://download.geofabrik.de/europe/united-kingdom/england/greater-london-updates/state.txt'
URL_PBFFILE='https://download.geofabrik.de/europe/united-kingdom/england/greater-london-latest.osm.pbf'
PBFFILE=$(basename $URL_PBFFILE)
SCHEMA='place_osmetymology'

# AREAFILE should be a path to a GIS file in folder "local". Specify the file's field names for id and name
AREAFILE=areas.fgb
AREAFILE_ID=ogc_fid # Must be a number. ogc_fid is default for GIS import.
AREAFILE_NAME=area_name
