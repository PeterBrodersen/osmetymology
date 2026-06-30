-- Shared aggregation finalization used by both area and no-area modes.

UPDATE locations_agg_next SET geom_dk = ST_Transform(geom, 25832);

CREATE INDEX locations_agg_next_geom_idx ON locations_agg_next USING gist (geom);
CREATE INDEX locations_agg_next_geom_dk_idx ON locations_agg_next USING gist (geom_dk);
CREATE INDEX locations_agg_next_name_idx ON locations_agg_next ("name" text_pattern_ops);
CREATE INDEX locations_agg_next_searchname_idx ON locations_agg_next ("searchname" text_pattern_ops);
CREATE INDEX locations_agg_next_area_idx ON locations_agg_next ("area_code");
CREATE INDEX locations_agg_next_name_etymology_wikidata_idx ON locations_agg_next ("name:etymology:wikidata");
CREATE INDEX locations_agg_next_wikidatas_idx ON locations_agg_next USING gin("wikidatas");

-- Create map from locations to Wikidata items
DROP TABLE IF EXISTS wikidatamap_next;
CREATE TABLE wikidatamap_next AS
SELECT
	id AS location_id,
	unnest(wikidatas) AS wikidata_id
FROM
	locations_agg_next
WHERE
	wikidatas IS NOT NULL AND array_length(wikidatas, 1) > 0;

CREATE INDEX wikidatamap_next_wikidata_id_idx ON wikidatamap_next (wikidata_id);
CREATE INDEX wikidatamap_next_location_id_idx ON wikidatamap_next (location_id);

BEGIN;
ALTER TABLE IF EXISTS locations_agg RENAME TO locations_agg_old;
ALTER TABLE locations_agg_next RENAME TO locations_agg;
ALTER TABLE IF EXISTS wikidatamap RENAME TO wikidatamap_old;
ALTER TABLE wikidatamap_next RENAME TO wikidatamap;
DROP TABLE IF EXISTS locations_agg_old;
DROP TABLE IF EXISTS wikidatamap_old;
ALTER INDEX IF EXISTS locations_agg_next_geom_idx RENAME TO locations_agg_geom_idx;
ALTER INDEX IF EXISTS locations_agg_next_geom_dk_idx RENAME TO locations_agg_geom_dk_idx;
ALTER INDEX IF EXISTS locations_agg_next_name_idx RENAME TO locations_agg_name_idx;
ALTER INDEX IF EXISTS locations_agg_next_searchname_idx RENAME TO locations_agg_searchname_idx;
ALTER INDEX IF EXISTS locations_agg_next_area_idx RENAME TO locations_agg_area_idx;
ALTER INDEX IF EXISTS locations_agg_next_name_etymology_wikidata_idx RENAME TO locations_agg_name_etymology_wikidata_idx;
ALTER INDEX IF EXISTS locations_agg_next_wikidatas_idx RENAME TO locations_agg_wikidatas_idx;
ALTER INDEX IF EXISTS wikidatamap_next_wikidata_id_idx RENAME TO wikidatamap_wikidata_id_idx;
ALTER INDEX IF EXISTS wikidatamap_next_location_id_idx RENAME TO wikidatamap_location_id_idx;
COMMIT;
