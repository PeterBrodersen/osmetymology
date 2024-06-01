<?php
require '../config/db.php';
$dsn = 'pgsql:host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';user=' . DBUSER . ';password=' . DBPASS;
$dbh = new PDO($dsn);
try {
    $dbh = new PDO($dsn);
} catch (PDOException $e) {
	print "Error!<br>\n";
	print_r($e);
	exit;
}
?>
