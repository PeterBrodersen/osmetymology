<?php
require("connect.inc.php");
header("Content-Type: application/json");
$search = (string) ($_GET['search'] ?? '');
$streetname = (string) ($_GET['streetname'] ?? '');
$itemid = (string) ($_GET['itemid'] ?? '');
if ($search) {
	if (preg_match('_^Q\d+$_', $search)) {
		$itemid = $search;
	} else {
		$streetname = $search;
	}
}
$searchname = preg_replace('_[^[:alnum:]]_u', '', mb_strtolower($streetname));
$result = [];

function getColumns()
{
	$columns = [
		'ow.id',
		'ow.name AS streetname',
		'ow.object_ids',
		'ow.object_ids[1] AS sampleobject_id',
		'ow."name:etymology"',
		'ow."name:etymology:wikidata"',
		'ow."name:etymology:wikipedia"',
		'm.navn AS municipalityname',
		'w."name" AS wikilabel',
		'w.description AS wikidescription',
		'w2."name" AS wikiinstanceoflabel',
		'gendermap.gender',
		'ST_X(ST_Centroid(ow.geom)) AS centroid_longitude',
		'ST_Y(ST_Centroid(geom)) AS centroid_latitude'

	];
	$columnList = implode(', ', $columns);
	return $columnList;
}

function getQuerystring($type)
{
	$columns = getColumns();
	$where = '';
	if ($type == 'searchnamelike') {
		$where = "WHERE searchname LIKE TRANSLATE(REGEXP_REPLACE(LOWER(?), '[^[:alnum:]]', '', 'gi'), 'áàâäãçéèêëíìîïñóòôöõúùûüýÿ', 'aaaaaceeeeiiiinooooouuuuyy') || '%'";
	} elseif ($type = 'itemid') {
		$where = 'WHERE "name:etymology:wikidata" = ?';
	}
	$querystring = <<<EOD
	SELECT $columns
	FROM osmetymology.ways_agg ow
	INNER JOIN osmetymology.municipalities m on ow.municipality_code = m.kode
	LEFT JOIN osmetymology.wikidata w ON ow."name:etymology:wikidata" = w.itemid
	LEFT JOIN osmetymology.wikidata w2 ON w.claims#>'{P31,0}'->'mainsnak'->'datavalue'->'value'->>'id' = w2.itemid
	LEFT JOIN (VALUES ('Q6581072', 'female'), ('Q6581097', 'male'), ('Q1052281', 'female'), ('Q2449503', 'male')) as gendermap (itemid, gender) ON w.claims#>'{P21,0}'->'mainsnak'->'datavalue'->'value'->>'id' = gendermap.itemid
	$where
	ORDER BY ow.name, m.navn
	LIMIT 1000
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

if ($searchname) {
	$result = findStreetName($searchname);
} elseif ($itemid) {
	$result = findStreetsFromItem($itemid);
}

print json_encode($result);
