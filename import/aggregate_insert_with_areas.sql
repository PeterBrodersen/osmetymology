-- Import points, ways, and polygons with area split.
INSERT INTO locations_agg_next (name, searchname, geomtype, featuretype, area_code, object_ids, "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata", wikidatas, geom)
(
	SELECT op.name, toSearchString(op.name), 'point', featureType(jsonb_merge_agg(tags)), a.area_id, array_agg(op.node_id), "name:etymology", "name:etymology:wikipedia","name:etymology:wikidata", regexp_split_to_array("name:etymology:wikidata", '\s*;\s*'), CASE WHEN a.area_id IS NULL THEN ST_Collect(geom) ELSE ST_Intersection(ST_Collect(geom), a.wkb_geometry) END
	FROM osm_points op
	LEFT JOIN areas a ON op.geom && a.wkb_geometry AND ST_Intersects(op.geom, a.wkb_geometry)
	WHERE op.geom IS NOT NULL AND op.name IS NOT NULL AND ("name:etymology" IS NOT NULL OR "name:etymology:wikipedia" IS NOT NULL OR "name:etymology:wikidata" is not NULL)
	GROUP by op.name, a.area_name, a.area_id, "name:etymology", "name:etymology:wikipedia","name:etymology:wikidata", a.wkb_geometry
)
UNION
(
	SELECT ow.name, toSearchString(ow.name), 'line', featureType(jsonb_merge_agg(tags)), a.area_id, array_agg(ow.way_id), "name:etymology", "name:etymology:wikipedia","name:etymology:wikidata", regexp_split_to_array("name:etymology:wikidata", '\s*;\s*'), CASE WHEN a.area_id IS NULL THEN ST_Collect(geom) ELSE ST_Intersection(ST_Collect(geom), a.wkb_geometry) END
	FROM osm_ways ow
	LEFT JOIN areas a ON ow.geom && a.wkb_geometry AND ST_Intersects(ow.geom, a.wkb_geometry)
	WHERE ow.geom IS NOT NULL AND ow.name IS NOT NULL AND ("name:etymology" IS NOT NULL OR "name:etymology:wikipedia" IS NOT NULL OR "name:etymology:wikidata" is not NULL)
	GROUP by ow.name, a.area_name, a.area_id, "name:etymology", "name:etymology:wikipedia","name:etymology:wikidata", a.wkb_geometry
)
UNION (
	SELECT op.name, toSearchString(op.name), 'polygon', featureType(jsonb_merge_agg(tags)), a.area_id, array_agg(op.area_id), "name:etymology", "name:etymology:wikipedia","name:etymology:wikidata", regexp_split_to_array("name:etymology:wikidata", '\s*;\s*'), CASE WHEN a.area_id IS NULL THEN ST_Union(geom) ELSE ST_Intersection(ST_Union(geom), a.wkb_geometry) END
	FROM osm_polygons op
	LEFT JOIN areas a ON op.geom && a.wkb_geometry AND ST_Intersects(op.geom, a.wkb_geometry)
	WHERE op.geom IS NOT NULL AND op.name IS NOT NULL AND ("name:etymology" IS NOT NULL OR "name:etymology:wikipedia" IS NOT NULL OR "name:etymology:wikidata" is not NULL)
	GROUP by op.name, a.area_id, "name:etymology", "name:etymology:wikipedia","name:etymology:wikidata", a.wkb_geometry
);

DROP INDEX IF EXISTS areas_idx;
CREATE INDEX areas_idx ON areas ("area_id");
