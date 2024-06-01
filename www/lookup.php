<?php
require("connect.inc.php");
header("Content-Type: application/json");
$name = (string) ($_GET['term'] ?? '');
$result = [];
if (strlen($name) < 2) {
	print json_encode($result);
	exit;
}

$q = $dbh->prepare('SELECT id, name, "name:etymology:wikidata" FROM osmetymology.ways_agg WHERE name ILIKE ? LIMIT 10');
$q->execute([$name . '%']);
foreach($q AS $row) {
	$result[] = $row;
}
print json_encode($result);

?>
