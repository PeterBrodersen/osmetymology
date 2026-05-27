-- ogr2ogr convert from database to FlatGeobuf file
SELECT l.id, l.name AS streetname, l."name:etymology", l."name:etymology:wikidata", l."name:etymology:wikipedia", l.municipality_code, m.navn AS municipality_name, w."name" AS wikidata_label, w.description AS wikidata_description, COALESCE(gendermap.gender,'') AS gender, to_date(w.claims->'P569'->0->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date as wikidata_dateofbirth, to_date(w.claims->'P570'->0->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date as wikidata_dateofdeath, w.sitelinks->'dawiki'->>'title' AS wikidata_wikipediatitleda, w2."itemid" AS wikidata_instanceOfItemId, w2."name" AS wikidata_instanceOfLabel, w2.description AS wikidata_instanceOfDescription, CASE
        WHEN w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'longitude' IS NOT NULL
            AND w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'latitude' IS NOT NULL
        THEN ST_AsText(ST_SetSRID(ST_Point(
            (w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'longitude')::double precision,
            (w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'latitude')::double precision
        ), 4326))
        ELSE NULL
END AS wikidata_location, l.geomtype, l.object_ids[1] AS sampleobject_id, wikidatas.wikidataset, wikidatas.wikilabel, l.geom
FROM osmetymology.locations_agg l
INNER JOIN osmetymology.municipalities m on l.municipality_code = m.kode
LEFT JOIN osmetymology.wikidata w ON l."name:etymology:wikidata" = w.itemid
LEFT JOIN osmetymology.wikidata w2 ON w.claims->'P31'->0->'mainsnak'->'datavalue'->'value'->>'id' = w2.itemid
LEFT JOIN osmetymology.gendermap ON w.claims->'P21'->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
LEFT JOIN LATERAL(
    SELECT jsonb_agg(jsonb_build_object(
        'itemid', w.itemid,
        'label', w.name,
        'description', w.description,
        'gender', gendermap.gender,
        'dateofbirth', to_date(w.claims->'P569'->0->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date,
        'dateofdeath', to_date(w.claims->'P570'->0->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date,
        'wikipediatitleda', w.sitelinks->'dawiki'->>'title'
    )) AS wikidataset,
    string_agg(w.name, '; ') AS wikilabel
    FROM osmetymology.wikidatamap map
    INNER JOIN osmetymology.wikidata w ON map.wikidata_id = w.itemid
    LEFT JOIN osmetymology.wikidata w2 ON w.claims->'P31'->0->'mainsnak'->'datavalue'->'value'->>'id' = w2.itemid
    LEFT JOIN osmetymology.gendermap ON w.claims->'P21'->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
    WHERE l.id = map.location_id
) AS wikidatas ON TRUE
ORDER BY l.name, m.navn
