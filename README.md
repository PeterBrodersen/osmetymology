# OSM Etymology
Etymology map based on OpenStreetMap and Wikidata. This is geared for Danish content.

OpenStreetMap has references to Wikidata for a bunch of Danish streets.

## Overview
OpenStreetMap is a freely available map resource. Wikidata is a freely available structured data resource.

OpenStreetMap uses tags such as [`name:etymology:wikidata`](https://wiki.openstreetmap.org/wiki/Key:name:etymology:wikidata`) to link to Wikidata items. Using these items it is possible to show maps based on different topics such as country, gender, profession and so on. Check out [an example from Open Etymology Map](https://etymology.dsantini.it/#10.3907,55.3966,14.8,occupation,pmtiles_all,stamen_toner,etymology) showing a map of Odense grouped by occupation.

## Import process
Suggested automated process for using content:

1. Download [copy of Denmark](https://download.geofabrik.de/europe/denmark.html) from GeoFabrik
2. Import to PostgreSQL using [osm2pgsql](https://osm2pgsql.org/doc/manual.html#the-flex-output) with Flex output for storing keys in JSON field
3. Create secondary table with aggregated copy, grouping by name, etymology and highway type. No need to have several individual road segments
4. Split based on Danish municipality boundaries
5. Save road map as [FlatGeobuf](https://flatgeobuf.org/) for very fast web lookup ([FlatGeobuf example](https://flatgeobuf.org/examples/leaflet/filtered.html))
6. Fetch set of every Wikidata entry
7. Create web interface for secondary table to look up names, municipalities and subjects
8. Profit!

## Editors and data sources
OpenStreetMap and Wikidata can be edited by anyone. One of the most used editors for adding etymology data to streets and other objeccts is the [MapComplete Etymology Map](https://mapcomplete.org/etymology?z=16.5&lat=56.148988551988964&lon=10.203088105223515&fs-welcome-message=false). Of course, other editors such as JOSM can be used as well for advanced users.

Some of the more active users for adding etymological data in Denmark are [Søren Johannessen](https://hdyc.neis-one.org/?AE35) and [Peter Brodersen](https://hdyc.neis-one.org/?Peter%20Brodersen).

### Adding data
There are multiple options for figuring out the origin of a street name, such as:
* City reference guides for street names (books, Wiki pages)
* Web searches for names for the specific town
* Local context (e.g. a road named Roskildevej leading to the city of Roskilde)
* Names with unambigious topics (e.g. Folke Bernadottes Alle → [Folke Bernadotte](https://www.wikidata.org/wiki/Q212163), Ugandavej → [Uganda](https://www.wikidata.org/wiki/Q1036), Birkevej → [birk](https://www.wikidata.org/wiki/Q25243) )

### Resources
Check the source list for different cities in Denmark (todo).




## Other resources
Similar projects exists, such as [Open Etymology Map](https://etymology.dsantini.it/) <sup>[GitHub](https://gitlab.com/openetymologymap/open-etymology-map/)</sup>.
