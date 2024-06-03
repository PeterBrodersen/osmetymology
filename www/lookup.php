<?php
require("connect.inc.php");
header("Content-Type: application/json");
$streetname = (string) ($_GET['streetname'] ?? '');
$searchname = preg_replace('_[^[:alnum:]]_u', '', mb_strtolower($streetname));
$result = [];
if (strlen($streetname) < 2) {
	print json_encode($result);
	exit;
}

$q = $dbh->prepare('
	SELECT ow.id, ow.name AS streetname, ow.way_ids, ow.way_ids[1] AS sampleway_id, ow."name:etymology", ow."name:etymology:wikidata", ow."name:etymology:wikipedia", m.navn AS municipalityname
	FROM osmetymology.ways_agg ow
	inner join osmetymology.municipalities m on ow.municipality_code = m.kode
	WHERE searchname LIKE ?
	ORDER BY ow.name, m.navn
	LIMIT 1000
');
$q->setFetchMode(PDO::FETCH_ASSOC);

$q->execute([$searchname . '%']);
$result = $q->fetchAll();
print json_encode($result);

?>
