-- ogr2ogr convert from database to FlatGeobuf file
SELECT ow.id, ow.name AS streetname, ow."name:etymology", ow."name:etymology:wikidata", ow."name:etymology:wikipedia", ow.municipality_code, m.navn AS municipality_name, w."name" AS wikidata_label, w.description AS wikidata_description, COALESCE(gendermap.gender,'') AS gender, to_date(w.claims#>'{P569,0}'->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date as wikidata_dateofbirth, to_date(w.claims#>'{P570,0}'->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date as wikidata_dateofdeath, w.sitelinks->'dawiki'->>'title' AS wikidata_wikipediatitleda, w2."itemid" AS wikidata_instanceOfItemId, w2."name" AS wikidata_instanceOfLabel, w2.description AS wikidata_instanceOfDescription, ow.geomtype, ow.object_ids[1] AS sampleobject_id, ow.geom
FROM osmetymology.ways_agg ow
INNER JOIN osmetymology.municipalities m on ow.municipality_code = m.kode
LEFT JOIN osmetymology.wikidata w ON ow."name:etymology:wikidata" = w.itemid
LEFT JOIN osmetymology.wikidata w2 ON w.claims#>'{P31,0}'->'mainsnak'->'datavalue'->'value'->>'id' = w2.itemid
LEFT JOIN osmetymology.gendermap ON w.claims#>'{P21,0}'->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
ORDER BY ow.name, m.navn
