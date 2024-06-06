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

function findStreetName($searchname)
{
	global $dbh;
	if (strlen($searchname) < 2) {
		return false;
	}
	$q = $dbh->prepare('
		SELECT ow.id, ow.name AS streetname, ow.way_ids, ow.way_ids[1] AS sampleway_id, ow."name:etymology", ow."name:etymology:wikidata", ow."name:etymology:wikipedia", m.navn AS municipalityname, ST_X(ST_Centroid(geom)) AS centroid_longitude, ST_Y(ST_Centroid(geom)) AS centroid_latitude
		FROM osmetymology.ways_agg ow
		inner join osmetymology.municipalities m on ow.municipality_code = m.kode
		WHERE searchname LIKE ?
		ORDER BY ow.name, m.navn
		LIMIT 1000
	');
	$q->setFetchMode(PDO::FETCH_ASSOC);
	$q->execute([$searchname . '%']);
	$result = $q->fetchAll();
	return $result;
}

function findStreetsFromItem($itemid)
{
	global $dbh;
	if (!preg_match('_^Q\d+$_', $itemid)) {
		return false;
	}
	$q = $dbh->prepare('
		SELECT ow.id, ow.name AS streetname, ow.way_ids, ow.way_ids[1] AS sampleway_id, ow."name:etymology", ow."name:etymology:wikidata", ow."name:etymology:wikipedia", m.navn AS municipalityname
		FROM osmetymology.ways_agg ow
		inner join osmetymology.municipalities m on ow.municipality_code = m.kode
		WHERE "name:etymology:wikidata" = ?
		ORDER BY ow.name, m.navn
		LIMIT 1000
	');
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
