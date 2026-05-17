# Settings read by bash scripts.
# Copy to settings.sh and replace for own values

URL_STATEFILE='https://download.geofabrik.de/europe/united-kingdom/england/greater-london-updates/state.txt'
URL_PBFFILE='https://download.geofabrik.de/europe/united-kingdom/england/greater-london-latest.osm.pbf'
PBFFILE=$(basename $URL_PBFFILE)
SCHEMA='place_osmetymology'

# AREAFILE should be a prepared FlatGeobuf file with area id as field 'id', area name as field 'name'.
AREAFILE=areas.fgb
