# OSM Etymology
Etymology map based on OpenStreetMap and Wikidata.

This is the generic branch template, meant to be cloned and customized for a specific area.

## Overview
OpenStreetMap is a freely available map resource. Wikidata is a freely available structured data resource.

OpenStreetMap uses tags such as [`name:etymology:wikidata`](https://wiki.openstreetmap.org/wiki/Key:name:etymology:wikidata) to link to Wikidata items. Using these items it is possible to show maps based on different topics such as country, gender, profession and so on. Check out [an example from Open Etymology Map](https://etymology.dsantini.it/#10.3907,55.3966,14.8,occupation,pmtiles_all,stamen_toner,etymology) showing a map of Odense grouped by occupation.

## Install
This is the generic template for country or city imports.

### Requirements
* Postgres database
* PHP installation
* `osm2pgsql`
* `ogr2ogr`, typically found in `gdal-bin`.

### Setup
* Copy [import/settings.example.sh](import/settings.example.sh) to `import/settings.sh` and change variables:
    * `SCHEMA`: Local database schema. Will be created if it does not exist.
    * `URL_STATEFILE` and `URL_PBFFILE`: URLs to resources at e.g. [GeoFabrik download](https://download.geofabrik.de/) for OSM file.
    * `AREAFILE`: prepared FlatGeobuf file for partitioning place, with area id as field 'id', area name as field 'name'.
    * `AREAFILE_ID`: Column ID for area. Must be a number. `ogc_fid` would work as a default id.
    * `AREAFILE_NAME`: Column name for area.
* Copy [config/db.example.php](config/db.example.php) to `config/db.php` and update the variables with your database credentials and schema.

For web usage:
* Point your web server to the `www` folder.

### Installation
1. Set the `PGDATABASE` variable to the name of your database.
2. Run the [import script](import/import.sh).

This will generate the aggregated GIS table as well as supporting FlatGeobuf file (for web usage) and CSV file (for simple overview).

The import script can simply be run again to retrieve updated data. GeoFabrik usually updates around daily.

All done!

## Code
The web project is based on [Leaflet](https://leafletjs.com/) with [PostgreSQL](https://www.postgresql.org/) as DB backend. No OpenStreetMap editing feature is planned.

The FlatGeobuf map file contains all data when clicking the map.

A search option allows users to search for street names as well as topics.

### Import process
The import script works as follows:

1. Download [copy of OpenStreetMap data for the specific area](https://download.geofabrik.de/) from GeoFabrik
2. Import to PostgreSQL using [osm2pgsql](https://osm2pgsql.org/doc/manual.html#the-flex-output) with Flex output for storing keys in JSON field
3. Import areas (need to be found on your own) for grouping places
4. Create aggregated table of imported data, grouping by name and etymology - no need to have several individual road segments
5. Fetch set of every Wikidata item from the OpenStreetMap data as well as their "Instance of" items
6. Save geometry table as [FlatGeobuf](https://flatgeobuf.org/) file for web service as well as CSV file
7. Profit!

The area split is based on the idea that any named conceptual road should only exist once in a area. Every road segment for a street with a specific name should be considered the conceptually same road.

Performing the grouping and split makes it easier to answer conceptual questions such as:
* _How many roads are named after George Washington?_
* _What is the most common street name in (place)?_
* _Which item are (place) roads often named after?_
* _Which item is referenced by the most different names?_

In these cases it makes no sense to tally up every road segment with the same name or Wikidata item. This would result in an arbitrary count as even a straight road might consist of several individual segments with different speed limits, lane count, surface material, oneway rules, and so on.

## Updating the map
The service does not provide any edit feature, however there are several editors and other services to help you. Check out e.g. [MapComplete Etymology Map](https://mapcomplete.org/etymology.html).

## Editors and data sources
OpenStreetMap and Wikidata can be edited by anyone. One of the most used editors for adding etymology data to streets and other objects is the [MapComplete Etymology Map](https://mapcomplete.org/etymology.html). Of course, other editors such as JOSM can be used as well for advanced users.

### Adding data
There are multiple options for figuring out the origin of a street name, such as:
* City reference guides for street names (books, Wiki pages)
* Web searches for names for the specific town
* Local context (e.g. a road named Roskildevej leading to the city of Roskilde; a Church Road leading to the specific local church)
* Names with unambigious topics

## Bugs
Probably several (check Issues).

* Map settings (start location, zoom level boundaries, geocoder country bias) is currently hardcoded to London in [www/map.js](www/map.js). A local JSON file should probably exist for this.
* Areas should be optional, not requiring an area GIS file.

## Other resources
Similar projects exists, such as [Open Etymology Map](https://etymology.dsantini.it/) <sup>[GitHub](https://gitlab.com/openetymologymap/open-etymology-map/)</sup>.
