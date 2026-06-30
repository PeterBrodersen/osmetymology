-- Import points, ways, and polygons without area split.
INSERT INTO locations_agg_next (name, searchname, geomtype, featuretype, area_code, object_ids, "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata", wikidatas, geom)
(
	SELECT op.name, toSearchString(op.name), 'point', featureType(jsonb_merge_agg(tags)), NULL::bigint, array_agg(op.node_id), "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata", regexp_split_to_array("name:etymology:wikidata", '\s*;\s*'), ST_Transform(ST_Collect(geom), 4326)
	FROM osm_points op
	WHERE op.geom IS NOT NULL AND op.name IS NOT NULL AND ("name:etymology" IS NOT NULL OR "name:etymology:wikipedia" IS NOT NULL OR "name:etymology:wikidata" is not NULL)
	GROUP by op.name, "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata"
)
UNION
(
	SELECT ow.name, toSearchString(ow.name), 'line', featureType(jsonb_merge_agg(tags)), NULL::bigint, array_agg(ow.way_id), "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata", regexp_split_to_array("name:etymology:wikidata", '\s*;\s*'), ST_Transform(ST_Collect(geom), 4326)
	FROM osm_ways ow
	WHERE ow.geom IS NOT NULL AND ow.name IS NOT NULL AND ("name:etymology" IS NOT NULL OR "name:etymology:wikipedia" IS NOT NULL OR "name:etymology:wikidata" is not NULL)
	GROUP by ow.name, "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata"
)
UNION (
	SELECT op.name, toSearchString(op.name), 'polygon', featureType(jsonb_merge_agg(tags)), NULL::bigint, array_agg(op.area_id), "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata", regexp_split_to_array("name:etymology:wikidata", '\s*;\s*'), ST_Transform(ST_Union(geom), 4326)
	FROM osm_polygons op
	WHERE op.geom IS NOT NULL AND op.name IS NOT NULL AND ("name:etymology" IS NOT NULL OR "name:etymology:wikipedia" IS NOT NULL OR "name:etymology:wikidata" is not NULL)
	GROUP by op.name, "name:etymology", "name:etymology:wikipedia", "name:etymology:wikidata"
);
