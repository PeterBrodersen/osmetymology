-- ogr2ogr convert from database to CSV file
SELECT l.id, l.name AS streetname, l."name:etymology", l."name:etymology:wikidata", l."name:etymology:wikipedia", l.area_code, a.area_name, w."name" AS wikidata_label, w.description AS wikidata_description, gender_agg.gender, to_date(w.claims->'P569'->0->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date as dateofbirth, (w.claims->'P569'->0->'mainsnak'->'datavalue'->'value'->>'precision')::integer AS dateofbirth_precision, to_date(w.claims->'P570'->0->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date as dateofdeath, (w.claims->'P570'->0->'mainsnak'->'datavalue'->'value'->>'precision')::integer AS dateofdeath_precision, w.sitelinks->'enwiki'->>'title' AS wikidata_wikipediatitleen, w2."itemid" AS wikidata_instanceOfItemId, w2."name" AS wikidata_instanceOfLabel, w2.description AS wikidata_instanceOfDescription, (w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'longitude')::double precision AS wikidata_location_longitude, (w.claims->'P625'->0->'mainsnak'->'datavalue'->'value'->>'latitude')::double precision AS wikidata_location_latitude, ST_X(ST_ClosestPoint(geom, ST_Centroid(geom))::geometry) AS centroid_onfeature_longitude, ST_Y(ST_ClosestPoint(geom, ST_Centroid(geom))::geometry) AS centroid_onfeature_latitude
FROM locations_agg l
LEFT JOIN areas a on l.area_code = a.area_id
LEFT JOIN wikidata w ON l."name:etymology:wikidata" = w.itemid
LEFT JOIN wikidata w2 ON w.claims->'P31'->0->'mainsnak'->'datavalue'->'value'->>'id' = w2.itemid
LEFT JOIN LATERAL(
	SELECT CASE
		WHEN COALESCE(bool_or(gendermap.gender = 'male'), FALSE) AND COALESCE(bool_or(gendermap.gender = 'female'), FALSE) THEN 'mixed'
		WHEN COALESCE(bool_or(gendermap.gender = 'male'), FALSE) THEN 'male'
		WHEN COALESCE(bool_or(gendermap.gender = 'female'), FALSE) THEN 'female'
		ELSE NULL
	END AS gender
	FROM wikidatamap map
	INNER JOIN wikidata w ON map.wikidata_id = w.itemid
	LEFT JOIN gendermap ON w.claims->'P21'->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
	WHERE l.id = map.location_id
) AS gender_agg ON TRUE
ORDER BY l.name, a.area_name NULLS LAST, a.area_id NULLS LAST
