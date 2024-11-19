<?php
// Create static stats file
require("../www/connect.inc.php");
$statefile = 'state.txt';
$statsjsonfile = '../www/data/stats.json';
$municipalityjsonfile = '../www/data/municipalities.json';

print date("H:i:s") . ": Creating stats" . PHP_EOL;

$totalroads = $dbh->query('SELECT COUNT(*) FROM osmetymology.ways_agg')->fetchColumn();
$uniquenamedroads = $dbh->query('SELECT COUNT(DISTINCT name) FROM osmetymology.ways_agg')->fetchColumn();
$uniqueetymologywikidata = $dbh->query('SELECT COUNT(DISTINCT "name:etymology:wikidata") FROM osmetymology.ways_agg')->fetchColumn(); // :TODO: split/expand multiple name:etymology:wikidata records in same value, e.g. "Q44412;Q491280"
$localwikidataitems = $dbh->query('SELECT COUNT(*) FROM osmetymology.wikidata')->fetchColumn(); // :TODO: Only check up against actual values in ways_agg, not extra content
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

function getMunicipalityStats() { // :TODO: split/expand multiple name:etymology:wikidata records in same value, e.g. "Q44412;Q491280"
	global $dbh;
	$querystring = <<<EOD
	WITH dist AS (
		SELECT DISTINCT ow.municipality_code, m.navn, gendermap.gender, w.itemid
		FROM osmetymology.ways_agg ow
		INNER JOIN osmetymology.municipalities m ON ow.municipality_code = m.kode
		INNER JOIN osmetymology.wikidata w ON ow."name:etymology:wikidata" = w.itemid
		LEFT JOIN (VALUES ('Q6581072', 'female'), ('Q6581097', 'male'), ('Q1052281', 'female'), ('Q2449503', 'male')) AS gendermap (itemid, gender) ON w.claims#>'{P21,0}'->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
		WHERE ow.featuretype = 'way'
	), groups AS (
		SELECT municipality_code, navn, coalesce(SUM((gender = 'female')::int),0) AS female, coalesce(SUM((gender = 'male')::int),0) AS male, coalesce(SUM((gender is NULL)::int),0) AS no_gender, COUNT(*) AS totalcount
		FROM dist
		GROUP BY municipality_code, navn
	)
	SELECT *, (female+male) AS gendered_ways, female::float/greatest(female+male, 1) AS female_share, male::float/greatest(female+male, 1) AS male_share
	FROM groups
	ORDER BY municipality_code
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