<?php
require("connect.inc.php");
header("Content-Type: application/json");
$search = (string) ($_GET['search'] ?? '');
$streetname = (string) ($_GET['streetname'] ?? '');
$request = (string) ($_GET['request'] ?? '');
$itemid = (string) ($_GET['itemid'] ?? '');
$coordinates = (string) ($_GET['coordinates'] ?? '');
$bbox = (string) ($_GET['bbox'] ?? '');
if ($search) {
	if (preg_match('_^Q\d+$_', $search)) {
		$itemid = $search;
	} else {
		$streetname = $search;
	}
}
$searchname = preg_replace('_[^[:alnum:]]_u', '', mb_strtolower($streetname));
$result = [];

function getColumns($coordinates = FALSE)
{
	$columns = [
		'ow.id',
		'ow.name AS streetname',
		'ow.object_ids',
		'ow.object_ids[1] AS sampleobject_id',
		'ow.featuretype',
		'ow."name:etymology"',
		'ow."name:etymology:wikidata"',
		'ow."name:etymology:wikipedia"',
		'm.navn AS municipalityname',
		'w."name" AS wikilabel',
		'w.description AS wikidescription',
		'w2."name" AS wikiinstanceoflabel',
		'w2.description AS wikiinstanceofdescription',
		'gendermap.gender',
		"to_date(w.claims#>'{P569,0}'->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date AS wikidateofbirth",
		"to_date(w.claims#>'{P570,0}'->'mainsnak'->'datavalue'->'value'->>'time', 'YYYY-MM-DD')::date AS wikidateofdeath",
		"w.sitelinks->'dawiki'->>'title' AS wikipediatitleda",
		'ST_X(ST_Centroid(ow.geom)) AS centroid_longitude',
		'ST_Y(ST_Centroid(ow.geom)) AS centroid_latitude',
		'array_to_json(wikidatas) AS wikidatas_json',
	];
	if ($coordinates) {
		$columns[] = "ow.geom_dk <-> ST_Transform('SRID=4326;POINT(" . $coordinates['longitude'] . " " . $coordinates['latitude'] . ")'::geometry, 25832) AS distance";
	}
	$columnList = implode(', ', $columns);
	return $columnList;
}

function getQuerystring($type, $coordinates = FALSE, $bbox = FALSE)
{
	$columns = getColumns($coordinates);
	$where = '';
	$limit = 1000;
	$orderbylist = ['ow.name, m.navn'];
	if ($type == 'searchnamelike') {
		$where = "WHERE searchname LIKE osmetymology.toSearchString(?) || '%'";
	} elseif ($type == 'itemid') {
		$where = 'WHERE "name:etymology:wikidata" = ?';
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
	FROM osmetymology.ways_agg ow
	INNER JOIN osmetymology.municipalities m on ow.municipality_code = m.kode
	LEFT JOIN osmetymology.wikidata w ON ow."name:etymology:wikidata" = w.itemid
	LEFT JOIN osmetymology.wikidata w2 ON w.claims#>'{P31,0}'->'mainsnak'->'datavalue'->'value'->>'id' = w2.itemid
	LEFT JOIN (VALUES ('Q6581072', 'female'), ('Q6581097', 'male'), ('Q1052281', 'female'), ('Q2449503', 'male')) as gendermap (itemid, gender) ON w.claims#>'{P21,0}'->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
	$where
	ORDER BY $orderby
	LIMIT $limit
	EOD;
	return $querystring;
}

function findStreetName($searchname)
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

if ($searchname) {
	$result = findStreetName($searchname);
} elseif ($itemid) {
	$result = findStreetsFromItem($itemid);
} elseif ($coordinates) {
	$result = findNearestPlacesFromLocation($coordinates);
} elseif ($bbox) {
	$result = findNearestPlacesFromBBOX($bbox);
} elseif ($request == 'stats') {
	$result = getStats();
}

print json_encode($result);
