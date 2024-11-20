<?php
// Create static stats file
require("../www/connect.inc.php");
$statefile = 'state.txt';
$statsjsonfile = '../www/data/stats.json';
$municipalityjsonfile = '../www/data/municipalities.json';

print date("H:i:s") . ": Creating stats" . PHP_EOL;

$totalroads = $dbh->query('SELECT COUNT(*) FROM osmetymology.ways_agg')->fetchColumn();
$uniquenamedroads = $dbh->query('SELECT COUNT(DISTINCT name) FROM osmetymology.ways_agg')->fetchColumn();
$uniqueetymologywikidata = $dbh->query('WITH wds AS (SELECT DISTINCT UNNEST(wikidatas) AS wikidata_item FROM osmetymology.ways_agg) SELECT COUNT(wikidata_item) FROM wds')->fetchColumn();
$localwikidataitems = $dbh->query('SELECT COUNT(*) FROM osmetymology.wikidata')->fetchColumn(); // including extra content such as "instance of" data
$importfiletime = NULL;
if (file_exists($statefile)) {
    if (preg_match('/^timestamp=(.*)$/m', file_get_contents($statefile), $match)) {
        $importfiletime = strtotime(stripslashes($match[1]));
    }
}

$stats = [
    'totalroads' => $totalroads,
    'uniquenamedroads' => $uniquenamedroads,
    'uniqueetymologywikidata' => $uniqueetymologywikidata,
    'localwikidataitems' => $localwikidataitems,
    'importfiletime' => $importfiletime
];
file_put_contents($statsjsonfile, json_encode($stats));

function getMunicipalityStats() {
	global $dbh;
	$querystring = <<<EOD
		WITH expanded AS (
			SELECT ow.municipality_code, ow.name, UNNEST(wikidatas) AS wikidata_id
			FROM osmetymology.ways_agg ow
			WHERE ow.featuretype = 'way'
		)
		SELECT
			expanded.municipality_code,
			m.navn AS municipality_name,
			COUNT(DISTINCT CASE WHEN gender = 'female' THEN w.itemid END) AS unique_female_topic,
			COUNT(DISTINCT CASE WHEN gender = 'male' THEN w.itemid END) AS unique_male_topic,
			COUNT(DISTINCT CASE WHEN gender IS NULL THEN w.itemid END) AS unique_nogender_topic,
			COUNT(DISTINCT w.itemid) AS total_unique_topics,
			COUNT(DISTINCT CASE WHEN gender IS NOT NULL THEN w.itemid END) AS total_gendered_topics,
			COUNT(DISTINCT CASE WHEN gender IS NOT NULL THEN expanded.name END) AS unique_ways_with_gender, -- A person can have more than one way named after them
			ROUND(
				100.0 * COUNT(DISTINCT CASE WHEN gender = 'female' THEN w.itemid END) / 
				GREATEST(COUNT(DISTINCT CASE WHEN gender IN ('male', 'female') THEN w.itemid END), 1), 2
			) AS female_percentage,
			ROUND(
				100.0 * COUNT(DISTINCT CASE WHEN gender = 'male' THEN w.itemid END) / 
				GREATEST(COUNT(DISTINCT CASE WHEN gender IN ('male', 'female') THEN w.itemid END), 1), 2
			) AS male_percentage
		FROM expanded
		INNER JOIN osmetymology.municipalities m on expanded.municipality_code = m.kode
		INNER JOIN osmetymology.wikidata w ON expanded.wikidata_id = w.itemid
		LEFT JOIN (VALUES ('Q6581072', 'female'), ('Q6581097', 'male'), ('Q1052281', 'female'), ('Q2449503', 'male')) AS gendermap (itemid, gender) ON w.claims#>'{P21,0}'->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
		GROUP BY expanded.municipality_code, m.navn
		ORDER BY expanded.municipality_code
	EOD;
	$q = $dbh->query($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$result = $q->fetchAll();
	$resultclean = [];
	foreach($result AS $row) {
		$resultclean[] = array_map('strtofloat', $row); // hack due to PDO returning floats as string; fixed in PHP 8.4: https://github.com/devnexen/php-src/commit/c176f3d21688b0c7cc10f8afe31c17ca9adaed16
	}
	return $resultclean;
}

function strtofloat($scalar) {
    return is_numeric($scalar) ? $scalar+0 : $scalar;
}

$municipalitystats = getMunicipalityStats();
// :TODO: Add date and other information; put municipalities in their own array
file_put_contents($municipalityjsonfile, json_encode($municipalitystats));


/*
SELECT COUNT(*) FROM osmetymology.ways_agg
SELECT COUNT(DISTINCT name) FROM osmetymology.ways_agg
SELECT COUNT(DISTINCT "name:etymology:wikidata") FROM osmetymology.ways_agg
SELECT COUNT(*) FROM osmetymology.wikidata
SELECT EXTRACT(epoch from now())::INT) ); -- Should be file date for 'denmark-latest.osm.pbf


INSERT INTO osmetymology.stats (label, value) VALUES ('totalroads', (SELECT COUNT(*) FROM osmetymology.ways_agg) );
INSERT INTO osmetymology.stats (label, value) VALUES ('uniquenamedroads', (SELECT COUNT(DISTINCT name) FROM osmetymology.ways_agg) );
INSERT INTO osmetymology.stats (label, value) VALUES ('uniqueetymologywikidata', (SELECT COUNT(DISTINCT "name:etymology:wikidata") FROM osmetymology.ways_agg) );
INSERT INTO osmetymology.stats (label, value) VALUES ('localwikidataitems', (SELECT COUNT(*) FROM osmetymology.wikidata) );
INSERT INTO osmetymology.stats (label, value) VALUES ('importfinishtime', (SELECT EXTRACT(epoch from now())::INT) ); -- Should be file date for 'denmark-latest.osm.pbf'?
*/