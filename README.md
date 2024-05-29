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

The munitipality split is based on the idea that any named conceptual road should only exist once in a municipality. Every road segment for a street with a specific name should be considered the conceptually same road. OpenStreetMap does not group roads with the same name in the same area or split roads on municipality boundaries and roads do not have the official Danish muncipality+street codes (3+4 digits).

Performing the grouping and split makes it easier to answer conceptual questions such as:
* _How many roads are named after H.C. Andersen?_
* _What is the most common street name in Denmark?_
* _Which item are Danish roads often named after?_
* _Which item is referenced by the most different names?_

In these cases it makes no sense to tally up every road segment with the same name or Wikidata item. This would result in an arbitrary count as even a straight road might consist of several individual segments with different speed limits, lane count, surface material, oneway rules, and so on.

## Editors and data sources
OpenStreetMap and Wikidata can be edited by anyone. One of the most used editors for adding etymology data to streets and other objeccts is the [MapComplete Etymology Map](https://mapcomplete.org/etymology?z=16.5&lat=56.148988551988964&lon=10.203088105223515&fs-welcome-message=false). Of course, other editors such as JOSM can be used as well for advanced users.

Some of the more active users for adding etymological data in Denmark are [Søren Johannessen](https://hdyc.neis-one.org/?AE35) and [Peter Brodersen](https://hdyc.neis-one.org/?Peter%20Brodersen).

### Adding data
There are multiple options for figuring out the origin of a street name, such as:
* City reference guides for street names (books, Wiki pages)
* Web searches for names for the specific town
* Local context (e.g. a road named Roskildevej leading to the city of Roskilde)
* Names with unambigious topics (e.g. Folke Bernadottes Alle → [Folke Bernadotte](https://www.wikidata.org/wiki/Q212163), Ugandavej → [Uganda](https://www.wikidata.org/wiki/Q1036), Birkevej → [birk](https://www.wikidata.org/wiki/Q25243) )

#### Caveats
Some items might be deceptive, not unlike the linguistic topic of [false friends](https://en.wikipedia.org/wiki/False_friend). These are cases where the answer seems obvious but where the devil is in the detail.

A couple of examples:
* **Lærkevej** is the most common street name in Denmark, but the name is ambigious as it could be named after the [lærke/lark bird](https://www.wikidata.org/wiki/Q29858), the [lærk/larch tree](https://www.wikidata.org/wiki/Q25618) or even the common female given name [Lærke](https://www.wikidata.org/wiki/Q1879346), perhaps a specific person with Lærke as [first name](https://www.wikidata.org/wiki/Q454582 "Lærke Møller") or [surname](https://www.wikidata.org/wiki/Q65556414 "Frederikke Lærke")
  * Usually it could be determined from the context of nearby street names. If they are called Bøgevej, Platanvej, Birkevej they might refer to trees. If they are called Duevej, Hejrevej, Vibevej they probably refer to birds.
    * And even here we have roads such as [Lærkevænget in Asserbo](https://www.openstreetmap.org/way/39963793) with the roads [Hejrevænget](https://www.openstreetmap.org/way/39963512) ([Heron; a bird](https://www.wikidata.org/wiki/Q18789)) and [Birkevænget](https://www.openstreetmap.org/way/39963978) ([birch; a tree](https://www.wikidata.org/wiki/Q25243)) on each side.
* Several people can share the same name. Some families have several people where the child has the same name as their parent. Other times two persons just happen to share the same name. And even other times a road is not named after an individual person but their whole family.
  * [Holbergstien in Randers](https://www.openstreetmap.org/way/54614119) is named after Ludvig Holberg, but not [the famous one](https://www.wikidata.org/wiki/Q216692). Not even [the secondary one](https://www.wikidata.org/wiki/Q15106952) but [a third one](https://www.wikidata.org/wiki/Q124792455) who wasn't even present in Wikidata when adding the information.
  * [Pontoppidansgade in Randers](https://www.openstreetmap.org/way/856861811) is named after [the Pontoppidan family](https://www.wikidata.org/wiki/Q121301188) and not specifically the most known person Henrik Pontoppidan.

### Resources
Check the source list for different cities in Denmark (todo).

## Other resources
Similar projects exists, such as [Open Etymology Map](https://etymology.dsantini.it/) <sup>[GitHub](https://gitlab.com/openetymologymap/open-etymology-map/)</sup>.
