# OSM Etymology
Etymology map based on OpenStreetMap and Wikidata. This is geared for Danish content.

OpenStreetMap has references to Wikidata for a bunch of Danish streets. This project aims to make the information searchable.

A live version of the project can be found at **https://navne.findvej.dk/**

## Overview
OpenStreetMap is a freely available map resource. Wikidata is a freely available structured data resource.

OpenStreetMap uses tags such as [`name:etymology:wikidata`](https://wiki.openstreetmap.org/wiki/Key:name:etymology:wikidata) to link to Wikidata items. Using these items it is possible to show maps based on different topics such as country, gender, profession and so on. Check out [an example from Open Etymology Map](https://etymology.dsantini.it/#10.3907,55.3966,14.8,occupation,pmtiles_all,stamen_toner,etymology) showing a map of Odense grouped by occupation.

## Install
### Requirements
* Postgres database
* PHP installation
* `osm2pgsql`
* `ogr2ogr`, typically found in `gdal-bin`.
### Installation
1. Set the `PGDATABASE` variable to the name of your database.
2. Create a schema called `osmetymology` in your Postgres database.
3. Run the [import script](import/import.sh). This takes about half an hour.

This will generate the aggregated GIS table as well as supporting FlatGeobuf file (for web usage) and CSV file (for simple overview).

The import script can simply be run again to retrieve updated data. GeoFabrik usually updates around daily.

For web usage:

4. Copy [config/db.example.php](config/db.example.php) to `config/db.php` and update the variables with your database credentials.
5. Point your web server to the `www` folder.

All done!

## Code
The web project is based on [Leaflet](https://leafletjs.com/) with [PostgreSQL](https://www.postgresql.org/) as DB backend. No OpenStreetMap editing feature is planned.

The FlatGeobuf map file contains all data when clicking the map.

A search option allows users to search for street names.

### Import process
The import script works as follows:

1. Download [copy of OpenStreetMap data in Denmark](https://download.geofabrik.de/europe/denmark.html) from GeoFabrik
2. Import to PostgreSQL using [osm2pgsql](https://osm2pgsql.org/doc/manual.html#the-flex-output) with Flex output for storing keys in JSON field
3. Import Danish extended municipality boundaries (included)
4. Create aggregated table of imported data, grouping by name and etymology - no need to have several individual road segments
5. Fetch set of every Wikidata item from the OpenStreetMap data as well as their "Instance of" items
6. Save geometry table as [FlatGeobuf](https://flatgeobuf.org/) file for web service as well as CSV file
7. Profit!

The munitipality split is based on the idea that any named conceptual road should only exist once in a municipality. Every road segment for a street with a specific name should be considered the conceptually same road. OpenStreetMap does not group roads with the same name in the same area or split roads on municipality boundaries and roads do not have the official Danish muncipality+street codes (4+4 digits).

Performing the grouping and split makes it easier to answer conceptual questions such as:
* _How many roads are named after H.C. Andersen?_
* _What is the most common street name in Denmark?_
* _Which item are Danish roads often named after?_
* _Which item is referenced by the most different names?_

In these cases it makes no sense to tally up every road segment with the same name or Wikidata item. This would result in an arbitrary count as even a straight road might consist of several individual segments with different speed limits, lane count, surface material, oneway rules, and so on.

## Updating the map
The service does not provide any edit feature, however there are several editors and other services to help you. Check out e.g. [MapComplete Etymology Map](https://mapcomplete.org/etymology?z=16.5&lat=56.148988551988964&lon=10.203088105223515&fs-welcome-message=false).

Check out the [editing article](Editing.md) for more information about caveats and issues.

## Editors and data sources
OpenStreetMap and Wikidata can be edited by anyone. One of the most used editors for adding etymology data to streets and other objects is the [MapComplete Etymology Map](https://mapcomplete.org/etymology?z=16.5&lat=56.148988551988964&lon=10.203088105223515&fs-welcome-message=false). Of course, other editors such as JOSM can be used as well for advanced users.

Some of the more active users for adding etymological data in Denmark are [Søren Johannessen](https://hdyc.neis-one.org/?AE35) and [Peter Brodersen](https://hdyc.neis-one.org/?Peter%20Brodersen).

### Adding data
There are multiple options for figuring out the origin of a street name, such as:
* City reference guides for street names (books, Wiki pages)
* Web searches for names for the specific town
* Local context (e.g. a road named Roskildevej leading to the city of Roskilde; a Kirkevej leading to the specific local church)
* Names with unambigious topics (e.g. Folke Bernadottes Alle → [Folke Bernadotte](https://www.wikidata.org/wiki/Q212163), Ugandavej → [Uganda](https://www.wikidata.org/wiki/Q1036), Birkevej → [birk](https://www.wikidata.org/wiki/Q25243) )

#### Caveats
Some items might be deceptive, not unlike the linguistic topic of [false friends](https://en.wikipedia.org/wiki/False_friend). These are cases where the answer seems obvious but where the devil is in the detail.

A couple of examples:
* **Lærkevej** is the most common street name in Denmark, but the name is ambigious as it could be named after the [lærke/lark bird](https://www.wikidata.org/wiki/Q29858), the [lærk/larch tree](https://www.wikidata.org/wiki/Q25618) or even the common female given name [Lærke](https://www.wikidata.org/wiki/Q1879346), perhaps a specific person with Lærke as [first name](https://www.wikidata.org/wiki/Q454582 "Lærke Møller") or [surname](https://www.wikidata.org/wiki/Q65556414 "Frederikke Lærke")
  * Usually it could be determined from the context of nearby street names. If they are called Bøgevej, Platanvej, Birkevej they might refer to trees. If they are called Duevej, Hejrevej, Vibevej they probably refer to birds.
    * And even here we have roads such as [Lærkevænget, Asserbo](https://www.openstreetmap.org/way/39963793) with the roads [Hejrevænget](https://www.openstreetmap.org/way/39963512) ([Heron; a bird](https://www.wikidata.org/wiki/Q18789)) and [Birkevænget](https://www.openstreetmap.org/way/39963978) ([birch; a tree](https://www.wikidata.org/wiki/Q25243)) on each side.
      * And even **Hejrevej** could be ambigious ([Bromus; a plant](https://www.wikidata.org/wiki/Q147621) or [Heron; a bird](https://www.wikidata.org/wiki/Q18789))
* Several people can share the same name. Some families have several people where the child has the same name as their parent. Other times two persons just happen to share the same name. And even other times a road is not named after an individual person but their whole family.
  * [Holbergstien, Randers](https://www.openstreetmap.org/way/54614119) is named after Ludvig Holberg, but not [the famous one](https://www.wikidata.org/wiki/Q216692). Not even [the secondary one](https://www.wikidata.org/wiki/Q15106952) but [a third one](https://www.wikidata.org/wiki/Q124792455) who wasn't even present in Wikidata when adding the information.
  * [Pontoppidansgade, Randers](https://www.openstreetmap.org/way/856861811) is named after [the Pontoppidan family](https://www.wikidata.org/wiki/Q121301188) and not specifically the most known person Henrik Pontoppidan.
* Some names might seem connected with the same theme but one of them could have a different origin.
  * [Amerikavej, Copenhagen](https://www.openstreetmap.org/way/1881227) is not named after The Americas or US but after a local mansion that an American consul named _America_.
  * [Stockholmsgade, Copenhagen](https://www.openstreetmap.org/way/788241681) is not named after the Swedish captial Stockholm, but after an old beer garden called _Stokholm_. However other roads in the area are named after Nordic cities and persons.
  * [Englandsvej, Copenhagen](https://www.openstreetmap.org/way/161862400) is not quite clear. There are several roads in the area definitely named after other countries, but Englandsvej could be the road that leads to the countryside with the meadows (enge). (It [probably is named after England](http://www.hovedstadshistorie.dk/sundbyvester/englandsvej/) though).
  * [Vejlegade, Nakskov](https://www.openstreetmap.org/way/105868044) is not named after the Danish town Vejle but after "vejle", an old name for a ford (vadested). This is also the origin of Vejle's name.
* Some names are just deceptive.
  * [Ananasvænget, Odense](https://www.openstreetmap.org/way/55451768) ("Pineapple field") is not named after Ananas/Pineapple but after a specific apple cultivar called [Rød Ananas/Red Pineapple](https://www.wikidata.org/wiki/Q44275015).
  * [Fuglebakken, Vordingborg](https://www.openstreetmap.org/way/25472023) ("Bird hill") is not named after birds but a gardener named [Axel Fugl](https://www.wikidata.org/wiki/Q130548760).
  * [Gåsestræde, Svendborg](https://www.openstreetmap.org/way/119759269) ("Goose alley") is not named after geese but a clergyman named [Hans Gaas](https://www.wikidata.org/wiki/Q16206237).
* Some roads have names based on historic data but due to changes they could be misinterpreted.
  * Roads with names such as Kirkevej, Skolevej, Stationsvej and so on might refer to earlier schools, churches and railway stations. School buildings have often changed purposes (public schools becoming gymnasiums, gymnasiums might merge, etc).
    * [Kirkevej, Dragør](https://www.openstreetmap.org/way/237227739) is just outside Dragør Kirke, but it was named before Dragør Kirke was built, and it in fact leads to [Store Magleby Kirke](https://www.wikidata.org/wiki/Q12003400).
* An animal or a plant could have the same name in different [taxonimic ranks](https://en.wikipedia.org/wiki/Taxonomic_rank). A road named **Rosevej** could be named after the rose [order](https://www.wikidata.org/wiki/Q21895 "Rosales"), [family](https://www.wikidata.org/wiki/Q46299 "Rosaceae") or [genus](https://www.wikidata.org/wiki/Q34687 "Rosa"). Or even a person named _Rose_ as mentioned under the _Lærkevej_ example.
  * There is no clear answer here as the responsible people at the municipality (or whomever named the road) might not be taxonomists as well but simply pointed at some plants, stating "This road is named after those things". A project grouping categories might need a list of the most common connections as the OpenStreetMap editors might simply choose an arbitrary taxonomic rank when there is no obvious choice.
    * Furthermore every animal and plant order, family, and species are simply listed as [taxon](https://www.wikidata.org/wiki/Q16521). Further checks, usually at the [Wikidata subclass level](https://www.wikidata.org/wiki/Property:P279 "subclass of"), should be checked to determine basic information such as whether the item is an animal, plant or whatnot if possible (even if there is [no such thing as a fish](https://en.wikipedia.org/wiki/No_Such_Thing_as_a_Fish#Title "Wikipedia: No such thing as a Fish; Title")).

### Resources
Check the [source list](Resources.md) for different locations in Denmark.

## Bugs
Probably several (check Issues).

## Other resources
Similar projects exists, such as [Open Etymology Map](https://etymology.dsantini.it/) <sup>[GitHub](https://gitlab.com/openetymologymap/open-etymology-map/)</sup>.
