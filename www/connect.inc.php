<?php
require '../config/db.php';
$dsn = 'pgsql:host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';user=' . DBUSER . ';password=' . DBPASS;
try {
	$dbh = new PDO($dsn);
	$dbh->exec("SET search_path TO " . DBSCHEMA . ", public");
} catch (PDOException $e) {
	print "Error!<br>\n";
	print_r($e);
	exit;
}
