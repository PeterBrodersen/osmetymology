-- ogr2ogr convert from database to FlatGeobuf file
SELECT l.id, l.name AS streetname, l."name:etymology", l."name:etymology:wikidata", l.area_code, a.area_name, wikidatas.gender, wikidatas.wikidata_location, l.geomtype, l.object_ids[1] AS sampleobject_id, wikidatas.wikidataset, wikidatas.wikilabel, l.geom
FROM locations_agg l
LEFT JOIN areas a on l.area_code = a.area_id
LEFT JOIN LATERAL(
    SELECT jsonb_agg(jsonb_build_object(
        'itemid', w.itemid,
        'label', w.name,
        'description', w.description,
        'gender', gendermap.gender,
        'dateofbirth', to_date(w.claims->'P569'->0->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date,
        'dateofbirth_precision', (w.claims->'P569'->0->'mainsnak'->'datavalue'->'value'->>'precision')::integer,
        'dateofdeath', to_date(w.claims->'P570'->0->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date,
        'dateofdeath_precision', (w.claims->'P570'->0->'mainsnak'->'datavalue'->'value'->>'precision')::integer,
        'wikidata_location', CASE
            WHEN w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'longitude' IS NOT NULL
                AND w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'latitude' IS NOT NULL
            THEN ST_AsText(ST_SetSRID(ST_Point(
                (w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'longitude')::double precision,
                (w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'latitude')::double precision
            ), 4326))
            ELSE NULL
        END,
        'wikipediatitleen', w.sitelinks->'enwiki'->>'title'
    )) AS wikidataset,
    string_agg(w.name, '; ') AS wikilabel,
    CASE
        WHEN COALESCE(bool_or(gendermap.gender = 'male'), FALSE) AND COALESCE(bool_or(gendermap.gender = 'female'), FALSE) THEN 'mixed'
        WHEN COALESCE(bool_or(gendermap.gender = 'male'), FALSE) THEN 'male'
        WHEN COALESCE(bool_or(gendermap.gender = 'female'), FALSE) THEN 'female'
        ELSE NULL
    END AS gender,
    MIN(CASE
        WHEN w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'longitude' IS NOT NULL
            AND w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'latitude' IS NOT NULL
        THEN ST_AsText(ST_SetSRID(ST_Point(
            (w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'longitude')::double precision,
            (w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'latitude')::double precision
        ), 4326))
        ELSE NULL
    END) AS wikidata_location
    FROM wikidatamap map
    INNER JOIN wikidata w ON map.wikidata_id = w.itemid
    LEFT JOIN gendermap ON w.claims->'P21'->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
    WHERE l.id = map.location_id
) AS wikidatas ON TRUE
WHERE l.geom IS NOT NULL AND NOT ST_IsEmpty(l.geom::geometry)
ORDER BY l.name, a.area_name NULLS LAST, a.area_id NULLS LAST
