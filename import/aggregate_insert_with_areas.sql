-- Import points, ways, and polygons with area split.
INSERT INTO locations_agg_next (name, searchname, element, featuretype, area_code, object_ids, object_id_lowest, "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata", geom)
(
	SELECT op.name, toSearchString(op.name), 'node', featureType(jsonb_merge_agg(tags)), a.area_id, array_agg(op.node_id), min(op.node_id), "name:etymology", "name:etymology:wikipedia","name:etymology:wikidata", CASE WHEN a.area_id IS NULL THEN ST_Collect(geom) ELSE ST_Intersection(ST_Collect(geom), a.wkb_geometry) END
	FROM osm_points op
	LEFT JOIN areas a ON op.geom && a.wkb_geometry AND ST_Intersects(op.geom, a.wkb_geometry)
	WHERE op.geom IS NOT NULL AND op.name IS NOT NULL AND ("name:etymology" IS NOT NULL OR "name:etymology:wikipedia" IS NOT NULL OR "name:etymology:wikidata" is not NULL)
	GROUP by op.name, a.area_name, a.area_id, "name:etymology", "name:etymology:wikipedia","name:etymology:wikidata", a.wkb_geometry
)
UNION
(
	SELECT ow.name, toSearchString(ow.name), 'way', featureType(jsonb_merge_agg(tags)), a.area_id, array_agg(ow.way_id), min(ow.way_id), "name:etymology", "name:etymology:wikipedia","name:etymology:wikidata", CASE WHEN a.area_id IS NULL THEN ST_Collect(geom) ELSE ST_Intersection(ST_Collect(geom), a.wkb_geometry) END
	FROM osm_ways ow
	LEFT JOIN areas a ON ow.geom && a.wkb_geometry AND ST_Intersects(ow.geom, a.wkb_geometry)
	WHERE ow.geom IS NOT NULL AND ow.name IS NOT NULL AND ("name:etymology" IS NOT NULL OR "name:etymology:wikipedia" IS NOT NULL OR "name:etymology:wikidata" is not NULL)
	GROUP by ow.name, a.area_name, a.area_id, "name:etymology", "name:etymology:wikipedia","name:etymology:wikidata", a.wkb_geometry
)
UNION (
	SELECT op.name, toSearchString(op.name), CASE WHEN min(op.area_id) < 0 THEN 'way' ELSE 'relation' END, featureType(jsonb_merge_agg(tags)), a.area_id, array_agg(abs(op.area_id)), min(abs(op.area_id)), "name:etymology", "name:etymology:wikipedia","name:etymology:wikidata", CASE WHEN a.area_id IS NULL THEN ST_Union(geom) ELSE ST_Intersection(ST_Union(geom), a.wkb_geometry) END
	FROM osm_polygons op
	LEFT JOIN areas a ON op.geom && a.wkb_geometry AND ST_Intersects(op.geom, a.wkb_geometry)
	WHERE op.geom IS NOT NULL AND op.name IS NOT NULL AND ("name:etymology" IS NOT NULL OR "name:etymology:wikipedia" IS NOT NULL OR "name:etymology:wikidata" is not NULL)
	GROUP by op.name, a.area_id, "name:etymology", "name:etymology:wikipedia","name:etymology:wikidata", a.wkb_geometry
);

DROP INDEX IF EXISTS areas_idx;
CREATE INDEX areas_idx ON areas ("area_id");
