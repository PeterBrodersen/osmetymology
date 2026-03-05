<?php
// Create static stats file
require("../www/connect.inc.php");
$statefile = 'state.txt';
$statsjsonfile = '../www/data/stats.json';
$arrondissementjsonfile = '../www/data/arrondissements.json';
$arrondissementjsonfolder = '../www/data/arrondissements/';

print date("H:i:s") . ": Creating stats" . PHP_EOL;

$totalroads = $dbh->query('SELECT COUNT(*) FROM ' . DBSCHEMA . '.locations_agg')->fetchColumn();
$uniquenamedroads = $dbh->query('SELECT COUNT(DISTINCT name) FROM ' . DBSCHEMA . '.locations_agg')->fetchColumn();
$uniqueetymologywikidata = $dbh->query('WITH wds AS (SELECT DISTINCT UNNEST(wikidatas) AS wikidata_item FROM ' . DBSCHEMA . '.locations_agg) SELECT COUNT(wikidata_item) FROM wds')->fetchColumn();
$localwikidataitems = $dbh->query('SELECT COUNT(*) FROM ' . DBSCHEMA . '.wikidata')->fetchColumn(); // including extra content such as "instance of" data
$importfiletime = NULL;
$importfiletimedate = NULL;
if (file_exists($statefile)) {
	if (preg_match('/^timestamp=(.*)$/m', file_get_contents($statefile), $match)) {
		$importfiletime = strtotime(stripslashes($match[1]));
		$importfiletimedate = date('Y-m-d H:i:s', $importfiletime);
	}
}

$stats = [
	'totalroads' => $totalroads,
	'uniquenamedroads' => $uniquenamedroads,
	'uniqueetymologywikidata' => $uniqueetymologywikidata,
	'localwikidataitems' => $localwikidataitems,
	'importfiletime' => $importfiletime,
	'importfiletimedate' => $importfiletimedate
];
file_put_contents($statsjsonfile, json_encode($stats));

function getArrondissementStats()
{
	global $dbh;
	$dbschema = DBSCHEMA; // for use in query string; can't be used directly in heredoc
	$querystring = <<<EOD
		WITH expanded AS (
			SELECT l.arrondissement_code, l.name, UNNEST(wikidatas) AS wikidata_id
			FROM $dbschema.locations_agg l
			WHERE l.featuretype IN('way','square')
		)
		SELECT
			expanded.arrondissement_code,
			m.l_aroff AS arrondissement_name,
			COUNT(DISTINCT CASE WHEN gender = 'female' AND w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' THEN w.itemid END) AS unique_human_female_topic,
			COUNT(DISTINCT CASE WHEN gender = 'male' AND w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' THEN w.itemid END) AS unique_human_male_topic,
			COUNT(DISTINCT CASE WHEN gender = 'female' THEN w.itemid END) AS unique_female_topic,
			COUNT(DISTINCT CASE WHEN gender = 'male' THEN w.itemid END) AS unique_male_topic,
			COUNT(DISTINCT CASE WHEN gender IS NULL THEN w.itemid END) AS unique_nogender_topic,
			COUNT(DISTINCT w.itemid) AS total_unique_topics,
			COUNT(DISTINCT CASE WHEN gender IS NOT NULL THEN w.itemid END) AS total_gendered_topics,
			COUNT(DISTINCT CASE WHEN gender IS NOT NULL THEN expanded.name END) AS unique_ways_with_gender, -- A person can have more than one way named after them
			ROUND(
				100.0 * COUNT(DISTINCT CASE WHEN gender = 'female' AND w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' THEN w.itemid END) / 
				GREATEST(COUNT(DISTINCT CASE WHEN gender IN ('male', 'female') AND w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' THEN w.itemid END), 1), 2
			) AS human_female_percentage,
			ROUND(
				100.0 * COUNT(DISTINCT CASE WHEN gender = 'male' AND w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' THEN w.itemid END) / 
				GREATEST(COUNT(DISTINCT CASE WHEN gender IN ('male', 'female') AND w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' THEN w.itemid END), 1), 2
			) AS human_male_percentage,
			ROUND(
				100.0 * COUNT(DISTINCT CASE WHEN gender = 'female' THEN w.itemid END) / 
				GREATEST(COUNT(DISTINCT CASE WHEN gender IN ('male', 'female') THEN w.itemid END), 1), 2
			) AS female_percentage,
			ROUND(
				100.0 * COUNT(DISTINCT CASE WHEN gender = 'male' THEN w.itemid END) / 
				GREATEST(COUNT(DISTINCT CASE WHEN gender IN ('male', 'female') THEN w.itemid END), 1), 2
			) AS male_percentage
		FROM expanded
		INNER JOIN $dbschema.arrondissements m on expanded.arrondissement_code = m.c_ar
		INNER JOIN $dbschema.wikidata w ON expanded.wikidata_id = w.itemid
		LEFT JOIN $dbschema.gendermap ON w.claims->'P21'->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
		GROUP BY expanded.arrondissement_code, m.l_aroff
		ORDER BY expanded.arrondissement_code
	EOD;
	$q = $dbh->query($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$result = $q->fetchAll();
	$resultclean = [];
	foreach ($result as $row) {
		$resultclean[] = array_map('strtofloat', $row); // hack due to PDO returning floats as string; fixed in PHP 8.4: https://github.com/devnexen/php-src/commit/c176f3d21688b0c7cc10f8afe31c17ca9adaed16
	}

	$dbschema = DBSCHEMA; // for use in query string; can't be used directly in heredoc
	// total stats; need own query to remove duplicates
	$querystring = <<<EOD
		WITH expanded AS (
			SELECT l.arrondissement_code, l.name, UNNEST(wikidatas) AS wikidata_id
			FROM $dbschema.locations_agg l
			WHERE l.featuretype IN('way','square')
		)
		SELECT
			COUNT(DISTINCT CASE WHEN gender = 'female' AND w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' THEN w.itemid END) AS unique_human_female_topic,
			COUNT(DISTINCT CASE WHEN gender = 'male' AND w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' THEN w.itemid END) AS unique_human_male_topic,
			COUNT(DISTINCT CASE WHEN gender = 'female' THEN w.itemid END) AS unique_female_topic,
			COUNT(DISTINCT CASE WHEN gender = 'male' THEN w.itemid END) AS unique_male_topic,
			COUNT(DISTINCT CASE WHEN gender IS NULL THEN w.itemid END) AS unique_nogender_topic,
			COUNT(DISTINCT w.itemid) AS total_unique_topics,
			COUNT(DISTINCT CASE WHEN gender IS NOT NULL THEN w.itemid END) AS total_gendered_topics,
			COUNT(DISTINCT CASE WHEN gender IS NOT NULL THEN expanded.name END) AS unique_ways_with_gender, -- A person can have more than one way named after them
			ROUND(
				100.0 * COUNT(DISTINCT CASE WHEN gender = 'female' AND w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' THEN w.itemid END) / 
				GREATEST(COUNT(DISTINCT CASE WHEN gender IN ('male', 'female') AND w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' THEN w.itemid END), 1), 2
			) AS human_female_percentage,
			ROUND(
				100.0 * COUNT(DISTINCT CASE WHEN gender = 'male' AND w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' THEN w.itemid END) / 
				GREATEST(COUNT(DISTINCT CASE WHEN gender IN ('male', 'female') AND w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' THEN w.itemid END), 1), 2
			) AS human_male_percentage,
			ROUND(
				100.0 * COUNT(DISTINCT CASE WHEN gender = 'female' THEN w.itemid END) / 
				GREATEST(COUNT(DISTINCT CASE WHEN gender IN ('male', 'female') THEN w.itemid END), 1), 2
			) AS female_percentage,
			ROUND(
				100.0 * COUNT(DISTINCT CASE WHEN gender = 'male' THEN w.itemid END) / 
				GREATEST(COUNT(DISTINCT CASE WHEN gender IN ('male', 'female') THEN w.itemid END), 1), 2
			) AS male_percentage
		FROM expanded
		INNER JOIN $dbschema.wikidata w ON expanded.wikidata_id = w.itemid
		LEFT JOIN $dbschema.gendermap ON w.claims->'P21'->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
	EOD;
	$q = $dbh->query($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$resulttotal = $q->fetch();
	$resulttotal = array_map('strtofloat', $resulttotal); // hack due to PDO returning floats as string; fixed in PHP 8.4: https://github.com/devnexen/php-src/commit/c176f3d21688b0c7cc10f8afe31c17ca9adaed16

	$result = ['etymologystats' => ['total' => $resulttotal, 'arrondissements' => $resultclean]];
	return $result;
}

function strtofloat($scalar)
{
	return is_numeric($scalar) ? $scalar + 0 : $scalar;
}


function getSingleArrondissementWayPersons($arrondissementcode)
{
	global $dbh;
	$q = $dbh->prepare("SELECT c_ar AS arrondissement_code, l_aroff AS arrondissement_name FROM " . DBSCHEMA . ".arrondissements WHERE c_ar = ?");
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$q->execute([$arrondissementcode]);
	$result = $q->fetch();
	if (!$result) {
		return [];
	}

	$dbschema = DBSCHEMA; // for use in query string; can't be used directly in heredoc

	$querystring = <<<EOD
		WITH expanded AS (
			SELECT DISTINCT l."name", unnest(wikidatas) AS wd
			FROM $dbschema.locations_agg l
			WHERE l.featuretype IN('way','square')
			AND l.arrondissement_code = ?
		)
		SELECT w.name AS personname, gendermap.gender, w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' AS is_human, w.description, wd AS wikidata_item, STRING_AGG(expanded.name, ';' ORDER BY expanded.name) AS ways
		FROM expanded
		INNER JOIN $dbschema.wikidata w ON expanded.wd = w.itemid
		INNER JOIN $dbschema.gendermap ON w.claims->'P21'->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
		GROUP BY personname, gender, description, wikidata_item, is_human
		ORDER BY gender, is_human DESC, personname
	EOD;
	$q = $dbh->prepare($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$q->execute([$arrondissementcode]);
	$result['items'] = $q->fetchAll();
	return $result;
}

function getArrondissementCodes()
{
	global $dbh;
	$result = $dbh->query("SELECT c_ar FROM " . DBSCHEMA . ".arrondissements ORDER BY c_ar")->fetchAll(PDO::FETCH_COLUMN);
	return $result;
}

$arrondissementstats = getArrondissementStats();
$arrondissementstats['importjob'] = $stats;
file_put_contents($arrondissementjsonfile, json_encode($arrondissementstats));

print date("H:i:s") . ": Creating arrondissement stats" . PHP_EOL;
$acount = 0;
$arrondissementcodes = getArrondissementCodes();
foreach ($arrondissementcodes as $arrondissementcode) {
	$acount++;
	$data = getSingleArrondissementWayPersons($arrondissementcode);
	$jsonpath = $arrondissementjsonfolder . $arrondissementcode . '.json';
	file_put_contents($jsonpath, json_encode($data));
	print $acount . ' / ' . count($arrondissementcodes) . ' arrondissements' . "\r";
}

print PHP_EOL . date("H:i:s") . ": Stats done!" . PHP_EOL;

print "Total roads: " . $totalroads . PHP_EOL;
print "Unique named roads: " . $uniquenamedroads . PHP_EOL;
print "Unique etymology wikidata: " . $uniqueetymologywikidata . PHP_EOL;
print "Local wikidata items: " . $localwikidataitems . PHP_EOL;
