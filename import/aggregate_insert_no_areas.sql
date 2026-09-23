-- Import points, ways, and polygons without area split.
INSERT INTO locations_agg_next (name, searchname, element, featuretype, area_code, object_ids, object_id_lowest, "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata", geom)
(
	SELECT op.name, toSearchString(op.name), 'node', featureType(jsonb_merge_agg(tags)), NULL::bigint, array_agg(op.node_id), min(op.node_id), "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata", ST_Collect(geom)
	FROM osm_points op
	WHERE op.geom IS NOT NULL AND op.name IS NOT NULL AND ("name:etymology" IS NOT NULL OR "name:etymology:wikipedia" IS NOT NULL OR "name:etymology:wikidata" is not NULL)
	GROUP by op.name, "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata"
)
UNION
(
	SELECT ow.name, toSearchString(ow.name), 'way', featureType(jsonb_merge_agg(tags)), NULL::bigint, array_agg(ow.way_id), min(ow.way_id), "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata", ST_Collect(geom)
	FROM osm_ways ow
	WHERE ow.geom IS NOT NULL AND ow.name IS NOT NULL AND ("name:etymology" IS NOT NULL OR "name:etymology:wikipedia" IS NOT NULL OR "name:etymology:wikidata" is not NULL)
	GROUP by ow.name, "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata"
)
UNION (
	SELECT op.name, toSearchString(op.name), CASE WHEN min(op.area_id) < 0 THEN 'way' ELSE 'relation' END, featureType(jsonb_merge_agg(tags)), NULL::bigint, array_agg(abs(op.area_id)), min(abs(op.area_id)), "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata", ST_Union(geom)
	FROM osm_polygons op
	WHERE op.geom IS NOT NULL AND op.name IS NOT NULL AND ("name:etymology" IS NOT NULL OR "name:etymology:wikipedia" IS NOT NULL OR "name:etymology:wikidata" is not NULL)
	GROUP by op.name, "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata"
);
