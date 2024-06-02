<?php
require("connect.inc.php");
header("Content-Type: application/json");
$name = (string) ($_GET['term'] ?? '');
$searchname = preg_replace('_[^[:alnum:]]_u', '', mb_strtolower($name));
$result = [];
if (strlen($name) < 2) {
	print json_encode($result);
	exit;
}

$q = $dbh->prepare('
	SELECT ow.id, ow.name, ow."name:etymology:wikidata", m.navn 
	FROM osmetymology.ways_agg ow
	inner join osmetymology.municipalities m on ow.municipality_code = m.kode
	WHERE searchname LIKE ? LIMIT 10');
$q->execute([$searchname . '%']);
foreach($q AS $row) {
	$result[] = $row;
}
print json_encode($result);

?>
