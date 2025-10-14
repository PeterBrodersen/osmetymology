<?php
require("connect.inc.php");
header("Content-Type: application/json");
$search = (string) ($_GET['search'] ?? '');
$streetname = (string) ($_GET['streetname'] ?? '');
$itemname = (string) ($_GET['itemname'] ?? '');
$term = (string) ($_GET['term'] ?? '');
$request = (string) ($_GET['request'] ?? '');
$itemid = (string) ($_GET['itemid'] ?? '');
$coordinates = (string) ($_GET['coordinates'] ?? '');
$municipalitycode = (int) ($_GET['municipalitycode'] ?? 0);
$bbox = (string) ($_GET['bbox'] ?? '');
if ($search) {
	if (preg_match('_^Q\d+$_', $search)) {
		$itemid = $search;
	} else {
		$streetname = $search;
	}
}
if (!$itemname) {
	$itemname = $term;
}

$searchname = preg_replace('_[^[:alnum:]]_u', '', mb_strtolower($streetname));
$searchitem = preg_replace('_[^[:alnum:]]_u', '', mb_strtolower($itemname));
$result = [];

function convertPGArraysToPHPArray($result)
{
	$cleanresult = [];
	foreach ($result as $row) {
		$row['object_ids'] = json_decode($row['object_ids']);
		$row['wikidatas'] = json_decode($row['wikidatas']);
		$row['wikidataset'] = json_decode($row['wikidataset']);
		$cleanresult[] = $row;
	}
	return $cleanresult;
}

function getColumns($coordinates = FALSE)
{
	$columns = [
		'l.id',
		'l.name AS streetname',
		'array_to_json(l.object_ids) AS object_ids',
		'l.object_ids[1] AS sampleobject_id',
		'l.featuretype',
		'l."name:etymology"',
		'l."name:etymology:wikidata"',
		'l."name:etymology:wikipedia"',
		'm.navn AS municipalityname',
		'w."name" AS wikilabel',
		'w.description AS wikidescription',
		'w2."name" AS wikiinstanceoflabel',
		'w2.description AS wikiinstanceofdescription',
		'gendermap.gender',
		"to_date(w.claims->'P569'->0->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date AS wikidateofbirth",
		"to_date(w.claims->'P570'->0->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date AS wikidateofdeath",
		"w.sitelinks->'dawiki'->>'title' AS wikipediatitleda",
		'ST_X(ST_ClosestPoint(geom, ST_Centroid(geom))) AS centroid_onfeature_longitude',
		'ST_Y(ST_ClosestPoint(geom, ST_Centroid(geom))) AS centroid_onfeature_latitude',
		'array_to_json(l.wikidatas) AS wikidatas',
		'wikidatas.wikidataset',
		'wikidatas.wikilabel'
	];
	if ($coordinates) {
		$columns[] = "l.geom_dk <-> ST_Transform('SRID=4326;POINT(" . $coordinates['longitude'] . " " . $coordinates['latitude'] . ")'::geometry, 25832) AS distance";
	}
	$columnList = implode(', ', $columns);
	return $columnList;
}

function getQuerystring($type, $coordinates = FALSE, $bbox = FALSE)
{
	$columns = getColumns($coordinates);
	$where = '';
	$limit = 1000;
	$orderbylist = ['l.name, m.navn'];
	if ($type == 'searchnamelike') {
		$where = "WHERE searchname LIKE osmetymology.toSearchString(?) || '%'";
	} elseif ($type == 'itemid') {
		$where = 'WHERE wikidatas @> ARRAY[?]';
	} elseif ($type == 'nearest') {
		$limit = 20;
		$orderbylist = ['distance'];
	} elseif ($type == 'bbox') {
		[$latitudeA, $longitudeA, $latitudeB, $longitudeB] = $bbox;
		$where = "WHERE ST_Intersects(geom, ST_Envelope('SRID=4326;LINESTRING($longitudeA $latitudeA, $longitudeB $latitudeB)'::geometry))";
		$limit = 100;
		// :TODO: add order by distance from center of bbox
	}
	$orderby = implode(', ', $orderbylist);
	$querystring = <<<EOD
		SELECT $columns
		FROM osmetymology.locations_agg l
		INNER JOIN osmetymology.municipalities m on l.municipality_code = m.kode
		LEFT JOIN osmetymology.wikidata w ON l."name:etymology:wikidata" = w.itemid
		LEFT JOIN osmetymology.wikidata w2 ON w.claims->'P31'->0->'mainsnak'->'datavalue'->'value'->>'id' = w2.itemid
		LEFT JOIN osmetymology.gendermap ON w.claims->'P21'->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
		LEFT JOIN LATERAL(
			SELECT jsonb_agg(jsonb_build_object(
				'itemid', w.itemid,
				'label', w.name,
				'description', w.description,
				'gender', gendermap.gender,
				'dateofbirth', to_date(w.claims->'P569'->0->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date,
				'dateofdeath', to_date(w.claims->'P570'->0->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date,
				'wikipediatitleda', w.sitelinks->'dawiki'->>'title'
			)) AS wikidataset,
			string_agg(w.name, '; ') AS wikilabel
			FROM osmetymology.wikidatamap map
			INNER JOIN osmetymology.wikidata w ON map.wikidata_id = w.itemid
			LEFT JOIN osmetymology.wikidata w2 ON w.claims->'P31'->0->'mainsnak'->'datavalue'->'value'->>'id' = w2.itemid
			LEFT JOIN osmetymology.gendermap ON w.claims->'P21'->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
			WHERE l.id = map.location_id
		) AS wikidatas ON TRUE
		$where
		ORDER BY $orderby
		LIMIT $limit
	EOD;
	return $querystring;
}

function findPlaceName($searchname)
{
	global $dbh;
	if (strlen($searchname) < 2) {
		return false;
	}
	$querystring = getQuerystring('searchnamelike');
	$q = $dbh->prepare($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$q->execute([$searchname]);
	$result = $q->fetchAll();
	$result = convertPGArraysToPHPArray($result);
	return $result;
}

function findWikidataLabel($searchitem)
{
	global $dbh;
	if (strlen($searchitem) < 2) {
		return false;
	}
	if (preg_match('_^Q\d+$_i', $searchitem)) {
		$searchitem = strtoupper($searchitem);
		$querystring = <<<EOD
			SELECT COUNT(l.id) AS placecount, w.name AS label, w.name AS alias, w.description, w.itemid
			FROM osmetymology.wikidata w
			INNER JOIN osmetymology.locations_agg l ON l.wikidatas @> ARRAY[w.itemid]
			WHERE w.itemid = ?
			GROUP BY w.itemid, w.description, w.name
		EOD;
	} else {
		$querystring = <<<EOD
			SELECT * FROM (
				SELECT DISTINCT ON (w.itemid) COUNT(l.id) AS placecount, w.name AS label, wl.label AS alias , w.description, w.itemid
				FROM osmetymology.wikilabels wl
				INNER JOIN osmetymology.wikidata w ON wl.itemid = w.itemid 
				INNER JOIN osmetymology.locations_agg l ON l.wikidatas @> ARRAY[w.itemid]
				WHERE wl.searchlabel LIKE osmetymology.toSearchString(?) || '%'
				GROUP BY wl.label, w.itemid, w.description, w.name
			) t
			ORDER BY placecount DESC, label, itemid
			LIMIT 50
		EOD;
	}
	$q = $dbh->prepare($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$q->execute([$searchitem]);
	$result = $q->fetchAll();
	return $result;
}

function findStreetsFromItem($itemid)
{
	global $dbh;
	if (!preg_match('_^Q\d+$_', $itemid)) {
		return false;
	}
	$querystring = getQuerystring('itemid');
	$q = $dbh->prepare($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$q->execute([$itemid]);
	$result = $q->fetchAll();
	$result = convertPGArraysToPHPArray($result);
	return $result;
}

function findNearestPlacesFromLocation($coordinates)
{
	global $dbh;
	[$latitude, $longitude] = explode(",", $coordinates);
	$latLng = ['latitude' => (float) $latitude, 'longitude' => (float) $longitude];
	$querystring = getQuerystring('nearest', $latLng);
	$q = $dbh->prepare($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$q->execute();
	$result = $q->fetchAll();
	$result = convertPGArraysToPHPArray($result);
	return $result;
}

function findNearestPlacesFromBBOX($bboxstring)
{
	global $dbh;
	$bbox = array_map('floatval', explode(",", $bboxstring));
	$querystring = getQuerystring('bbox', FALSE, $bbox);
	$q = $dbh->prepare($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$q->execute();
	$result = $q->fetchAll();
	$result = convertPGArraysToPHPArray($result);
	return $result;
}

function getStats()
{
	global $dbh;
	$querystring = "SELECT label, value FROM osmetymology.stats";
	$q = $dbh->query($querystring);
	$q->setFetchMode(PDO::FETCH_KEY_PAIR);
	$result = $q->fetchAll();
	return $result;
}

function getSingleMunicipalityWayPersons($municipalitycode)
{
	global $dbh;
	$municipalitycode = '0' . $municipalitycode;
	$q = $dbh->prepare("SELECT kode AS municipality_code, navn AS municipality_name, regionsnavn AS region_name FROM osmetymology.municipalities WHERE kode = ?");
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$q->execute([$municipalitycode]);
	$result = $q->fetch();
	if (!$result) {
		return [];
	}

	$querystring = <<<EOD
		WITH expanded AS (
			SELECT DISTINCT l."name", unnest(wikidatas) AS wd
			FROM osmetymology.locations_agg l
			WHERE l.featuretype = 'way'
			AND l.municipality_code = ?
		)
		SELECT w.name AS personname, gendermap.gender, w.description, STRING_AGG(expanded.name, ';' ORDER BY expanded.name) AS ways
		FROM expanded
		INNER JOIN osmetymology.wikidata w ON expanded.wd = w.itemid
		INNER JOIN osmetymology.gendermap ON w.claims->'P21->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
		GROUP BY personname, gender, description
		ORDER BY gender, personname
	EOD;
	$q = $dbh->prepare($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$q->execute([$municipalitycode]);
	$result['items'] = $q->fetchAll();
	return $result;
}

function getMunicipalityStats()
{
	global $dbh;
	$querystring = <<<EOD
		WITH expanded AS (
			SELECT l.municipality_code, l.name, UNNEST(wikidatas) AS wikidata_id
			FROM osmetymology.locations_agg l
			WHERE l.featuretype = 'way'
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
		LEFT JOIN osmetymology.gendermap ON w.claims->'P21'->0->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
		GROUP BY expanded.municipality_code, m.navn
		ORDER BY expanded.municipality_code
	EOD;
	$q = $dbh->query($querystring);
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$result = $q->fetchAll();
	$resultclean = [];
	foreach ($result as $row) {
		$resultclean[] = array_map('strtofloat', $row); // hack due to PDO returning floats as string; fixed in PHP 8.4: https://github.com/devnexen/php-src/commit/c176f3d21688b0c7cc10f8afe31c17ca9adaed16
	}
	return $resultclean;
}

function strtofloat($scalar)
{
	return is_numeric($scalar) ? $scalar + 0 : $scalar;
}

if ($searchname) {
	$result = findPlaceName($searchname);
} elseif ($searchitem) {
	$result = findWikidataLabel($searchitem);
} elseif ($itemid) {
	$result = findStreetsFromItem($itemid);
} elseif ($coordinates) {
	$result = findNearestPlacesFromLocation($coordinates);
} elseif ($bbox) {
	$result = findNearestPlacesFromBBOX($bbox);
} elseif ($request == 'stats') {
	$result = getStats();
} elseif ($municipalitycode) {
	$result = getSingleMunicipalityWayPersons($municipalitycode);
} elseif ($request == 'municipalitystats') {
	$result = getMunicipalityStats();
}

print json_encode($result);
