# Settings read by bash scripts.
# Copy to settings.sh and replace for own values

URL_STATEFILE='https://download.geofabrik.de/europe/united-kingdom/england/greater-london-updates/state.txt'
URL_PBFFILE='https://download.geofabrik.de/europe/united-kingdom/england/greater-london-latest.osm.pbf'
PBFFILE=$(basename $URL_PBFFILE)
SCHEMA='place_osmetymology'

# AREAFILE should be a prepared GIS file with area id as field 'area_id', and area name as field 'area_name'.
AREAFILE=local/areas.fgb
