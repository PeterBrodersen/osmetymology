# OSM Etymology
Etymology map based on OpenStreetMap and Wikidata.

This is the generic branch template, meant to be cloned and customized for a specific area.

There are [live versions](https://etymology.findvej.dk/) for a handful of  countries and cities.

## Overview
OpenStreetMap is a freely available map resource. Wikidata is a freely available structured data resource.

OpenStreetMap uses tags such as [`name:etymology:wikidata`](https://wiki.openstreetmap.org/wiki/Key:name:etymology:wikidata) to link to Wikidata items. Using these items it is possible to show maps based on different topics such as country, gender, profession and so on. Check out [live versions](https://etymology.findvej.dk/) such as [Berlin](https://berlin.etymology.findvej.dk) to see a map colour coded for gender.

## Install
This is the generic template for country or city imports.

### Requirements
* Postgres database
* PHP installation
* [osm2pgsql](https://osm2pgsql.org/)
* [ogr2ogr](https://gdal.org/en/stable/programs/ogr2ogr.html), typically found in `gdal-bin` package in Linux distributions
* [osmosis](https://github.com/openstreetmap/osmosis); only needed if extract is used

### Setup
Copy [config/config.example.json](config/config.example.json) to `config/config.json` and update values for:
* `place`: initial map location and geocoding defaults for the web frontend.
* `db`: database credentials and schema.
* `osm_urls`: URLs to resources at e.g. [GeoFabrik download](https://download.geofabrik.de/) for OSM file.
* `import`: import settings.
  * `enable_associated_street_relations`: Check for [associatedStreet](https://wiki.openstreetmap.org/wiki/Relation:associatedStreet) tagging.
  * `keep_nonusable_osm_data`: Keep OSM data not used in final import.
* `area`: *optional* FlatGeobuf area file and field names.
* `extract`: *optional* GIS area file to extract from downloaded OSM resource.
* `external_urls`: *optional* links shown on the website.
* `language`: language settings.
  * `wikidata`: prioritized array used for Wikidata labels/descriptions; first value is used for alias extraction during import.

For web usage:
* Point your web server to the `www` folder.

### Installation
* Run the [import script](import/import.sh) in the `import` folder.

This will generate the aggregated GIS table as well as supporting FlatGeobuf file (for web usage) and CSV file (for simple overview).

The import script can simply be run again to retrieve updated data. GeoFabrik usually updates around daily. Only new Wikidata items are fetched after the first run to keep requests down.

## Code
The web project is based on [Leaflet](https://leafletjs.com/) with [PostgreSQL](https://www.postgresql.org/) as DB backend. No OpenStreetMap editing feature is planned.

The FlatGeobuf map file contains all data when clicking the map.

A search option allows users to search for street names as well as topics.

### Import process
The import script works as follows:

1. Download [copy of OpenStreetMap data for the specific area](https://download.geofabrik.de/) from GeoFabrik.
2. Optionally extract a subsection of the downloaded data.
3. Import to PostgreSQL using [osm2pgsql](https://osm2pgsql.org/doc/manual.html#the-flex-output) with Flex output for storing keys in JSON field.
4. Optionally import areas for grouping places. Area boundary files are not included.
5. Create aggregated table of imported data, grouping by name and etymology ― no need to have several individual road segments.
6. Fetch set of every Wikidata item from the OpenStreetMap data as well as their "Instance of" items.
7. Save geometry table as [FlatGeobuf](https://flatgeobuf.org/) file for web service as well as CSV file.
8. Create statistics for each area.
9. Profit!

The optional area split is based on the idea that any named conceptual road should only exist once in a area. Every road segment for a street with a specific name should be considered the conceptually same road.

Performing the grouping and split makes it easier to answer conceptual questions such as:
* _How many roads are named after George Washington?_
* _What is the most common street name in (place)?_
* _Which item are (place) roads often named after?_
* _Which item is referenced by the most different names?_

In these cases it makes no sense to tally up every road segment with the same name or Wikidata item. This would result in an arbitrary count as even a straight road might consist of several individual segments with different speed limits, lane count, surface material, oneway rules, and so on.

## Editors and data sources
OpenStreetMap and Wikidata can be edited by anyone. One of the most used editors for adding etymology data to streets and other objects is the [MapComplete Etymology Map](https://mapcomplete.org/etymology.html). Of course, other editors such as JOSM can be used as well for advanced users.

### Adding data
There are multiple options for figuring out the origin of a street name, such as:
* City reference guides for street names (books, Wiki pages)
* Web searches for names for the specific town
* Local context (e.g. a road named Roskildevej leading to the city of Roskilde; a Church Road leading to the specific local church)
* Names with unambigious topics

## Other resources
Similar projects exists, such as [Open Etymology Map](https://etymology.dsantini.it/) <sup>[GitHub](https://gitlab.com/openetymologymap/open-etymology-map/)</sup>.
