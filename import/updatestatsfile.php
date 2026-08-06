<?php
// Create static stats file
require("../www/connect.inc.php");
$statefile = '../local/state.txt';
$statsjsonfile = '../www/data/stats.json';
$areajsonfile = '../www/data/areas.json';
$areajsonfolder = '../www/data/areas/';

print date("H:i:s") . ": Creating stats" . PHP_EOL;

function loadStatsFromJsonFile($path)
{
	if (!is_readable($path)) {
		return null;
	}

	$decoded = json_decode(file_get_contents($path), true);
	return is_array($decoded) ? $decoded : null;
}

function getImportFileMetadata($statefile)
{
	$importfiletime = null;
	$importfiletimedate = null;
	if (file_exists($statefile)) {
		if (preg_match('/^timestamp=(.*)$/m', file_get_contents($statefile), $match)) {
			$importfiletime = strtotime(stripslashes($match[1]));
			$importfiletimedate = date('Y-m-d H:i:s', $importfiletime);
		}
	}

	return [
		'importfiletime' => $importfiletime,
		'importfiletimedate' => $importfiletimedate,
	];
}

function getImportJobStats($statefile)
{
	global $dbh;

	$stats = [
		'totalroads' => $dbh->query('SELECT COUNT(*) FROM locations_agg')->fetchColumn(),
		'uniquenamedroads' => $dbh->query('SELECT COUNT(DISTINCT name) FROM locations_agg')->fetchColumn(),
		'uniqueetymologywikidata' => $dbh->query('WITH wds AS (SELECT DISTINCT UNNEST(wikidatas) AS wikidata_item FROM locations_agg) SELECT COUNT(wikidata_item) FROM wds')->fetchColumn(),
		'localwikidataitems' => $dbh->query('SELECT COUNT(*) FROM wikidata')->fetchColumn(), // including extra content such as "instance of" data
	];

	return array_merge($stats, getImportFileMetadata($statefile));
}

function writeJsonFile($path, $data)
{
	file_put_contents($path, json_encode($data));
}

function getDiffSuffix($key, $newStats, $oldStats)
{
	if (!is_array($oldStats) || !array_key_exists($key, $oldStats)) {
		return '';
	}

	$newValue = $newStats[$key] ?? null;
	$oldValue = $oldStats[$key];
	if (!is_numeric($newValue) || !is_numeric($oldValue)) {
		return '';
	}

	$diff = (int) $newValue - (int) $oldValue;
	$sign = $diff > 0 ? '+' : '';
	return ' (' . $sign . $diff . ')';
}

function printStatsSummary($newStats, $oldStats)
{
	$statsToPrint = [
		'totalroads' => 'Total roads',
		'uniquenamedroads' => 'Unique named roads',
		'uniqueetymologywikidata' => 'Unique etymology wikidata',
		'localwikidataitems' => 'Local wikidata items',
	];

	foreach ($statsToPrint as $key => $label) {
		$value = $newStats[$key] ?? 0;
		print $label . ': ' . $value . getDiffSuffix($key, $newStats, $oldStats) . PHP_EOL;
	}
}

$oldstats = loadStatsFromJsonFile($statsjsonfile);
$stats = getImportJobStats($statefile);
writeJsonFile($statsjsonfile, $stats);

function hasAreasTable()
{
	global $dbh;
	return (bool) $dbh->query("SELECT to_regclass('areas') IS NOT NULL")->fetchColumn();
}

function getEmptyAreaTotals()
{
	return [
		'unique_human_female_topic' => 0,
		'unique_human_male_topic' => 0,
		'unique_female_topic' => 0,
		'unique_male_topic' => 0,
		'unique_nogender_topic' => 0,
		'total_unique_topics' => 0,
		'total_gendered_topics' => 0,
		'unique_ways_with_gender' => 0,
		'human_female_percentage' => 0,
		'human_male_percentage' => 0,
		'female_percentage' => 0,
		'male_percentage' => 0,
	];
}

function getAreaStats()
{
	global $dbh;
	$useAreas = hasAreasTable();
	$areasJoin = $useAreas ? 'LEFT JOIN areas a ON expanded.area_code = a.area_id' : '';
	$areaCodeExpr = 'COALESCE(expanded.area_code, 0)';
	$areaNameExpr = $useAreas ? "COALESCE(a.area_name, 'No area')" : "'No area'";
	$querystring = <<<EOD
		WITH expanded AS (
			SELECT l.area_code, l.name, UNNEST(wikidatas) AS wikidata_id
			FROM locations_agg l
			WHERE l.featuretype IN('way','square')
		)
		SELECT
			$areaCodeExpr AS area_code,
			$areaNameExpr AS area_name,
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
		$areasJoin
		INNER JOIN wikidata w ON expanded.wikidata_id = w.itemid
		LEFT JOIN gendermap ON w.claims->'P21'->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
		GROUP BY $areaCodeExpr, $areaNameExpr
		ORDER BY ($areaCodeExpr = 0), area_code
	EOD;
	$q = $dbh->query($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$result = $q->fetchAll();
	$resultclean = [];
	foreach ($result as $row) {
		$resultclean[] = array_map('strtofloat', $row); // hack due to PDO returning floats as string; fixed in PHP 8.4: https://github.com/devnexen/php-src/commit/c176f3d21688b0c7cc10f8afe31c17ca9adaed16
	}

	$hasNoAreaRow = false;
	foreach ($resultclean as $row) {
		if ((int) $row['area_code'] === 0) {
			$hasNoAreaRow = true;
			break;
		}
	}
	if (!$hasNoAreaRow) {
		$resultclean[] = array_merge(['area_code' => 0, 'area_name' => 'No area'], getEmptyAreaTotals());
	}

	// total stats; need own query to remove duplicates
	$querystring = <<<EOD
		WITH expanded AS (
			SELECT l.area_code, l.name, UNNEST(wikidatas) AS wikidata_id
			FROM locations_agg l
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
		INNER JOIN wikidata w ON expanded.wikidata_id = w.itemid
		LEFT JOIN gendermap ON w.claims->'P21'->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
	EOD;
	$q = $dbh->query($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$resulttotal = $q->fetch();
	$resulttotal = array_map('strtofloat', $resulttotal); // hack due to PDO returning floats as string; fixed in PHP 8.4: https://github.com/devnexen/php-src/commit/c176f3d21688b0c7cc10f8afe31c17ca9adaed16

	$result = ['etymologystats' => ['total' => $resulttotal, 'areas' => $resultclean]];
	return $result;
}

function strtofloat($scalar)
{
	return is_numeric($scalar) ? $scalar + 0 : $scalar;
}

function decodeWaysField($items)
{
	foreach ($items as &$item) {
		if (!isset($item['ways']) || !is_string($item['ways'])) {
			continue;
		}
		$decoded = json_decode($item['ways'], true);
		if (json_last_error() === JSON_ERROR_NONE) {
			$item['ways'] = $decoded;
		}
	}
	unset($item);
	return $items;
}


function getSingleAreaWayPersons($areacode)
{
	global $dbh;
	$areacode = (int) $areacode;
	if ($areacode === 0) {
		$result = ['area_code' => 0, 'area_name' => 'No area'];
		$expandedWhere = 'l.area_code IS NULL';
		$params = [];
	} else {
		if (!hasAreasTable()) {
			return [];
		}
		$q = $dbh->prepare("SELECT area_id AS area_code, area_name FROM areas WHERE area_id = ?");
		$q->setFetchMode(PDO::FETCH_ASSOC);
		$q->execute([$areacode]);
		$result = $q->fetch();
		if (!$result) {
			return [];
		}
		$expandedWhere = 'l.area_code = ?';
		$params = [$areacode];
	}

	$querystring = <<<EOD
		WITH expanded AS (
			SELECT DISTINCT l."name", l.id AS internal_location_id, unnest(wikidatas) AS wd
			FROM locations_agg l
			WHERE l.featuretype IN('way','square')
			AND $expandedWhere
		)
		SELECT w.name AS personname, gendermap.gender, w.claims @@ '$.P31[*].mainsnak.datavalue.value.id == "Q5"' AS is_human, w.description, wd AS wikidata_item, jsonb_agg(jsonb_build_object('name', expanded.name, 'internal_location_id', expanded.internal_location_id) ORDER BY expanded.name, expanded.internal_location_id) AS ways
		FROM expanded
		INNER JOIN wikidata w ON expanded.wd = w.itemid
		INNER JOIN gendermap ON w.claims->'P21'->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
		GROUP BY personname, gender, description, wikidata_item, is_human
		ORDER BY gender, is_human DESC, personname
	EOD;
	$q = $dbh->prepare($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$q->execute($params);
	$result['items'] = decodeWaysField($q->fetchAll());
	return $result;
}

function getAreaCodes()
{
	global $dbh;
	$result = $dbh->query("SELECT area_code FROM (SELECT DISTINCT COALESCE(area_code, 0) AS area_code FROM locations_agg WHERE featuretype IN('way','square') UNION SELECT 0) areaset ORDER BY (area_code = 0), area_code")->fetchAll(PDO::FETCH_COLUMN);
	return $result;
}

$areastats = getAreaStats();
$areastats['importjob'] = $stats;
file_put_contents($areajsonfile, json_encode($areastats));

print date("H:i:s") . ": Creating area stats" . PHP_EOL;
$acount = 0;
$areacodes = getAreaCodes();
foreach ($areacodes as $areacode) {
	$acount++;
	$data = getSingleAreaWayPersons($areacode);
	$jsonpath = $areajsonfolder . $areacode . '.json';
	file_put_contents($jsonpath, json_encode($data));
	print $acount . ' / ' . count($areacodes) . ' areas' . "\r";
}

print PHP_EOL . date("H:i:s") . ": Stats done!" . PHP_EOL;

printStatsSummary($stats, $oldstats);
