<?php
$configPath = __DIR__ . '/../config/config.json';

if (!is_readable($configPath)) {
	print "Error: Missing config/config.json.<br>\n";
	exit;
}

$configData = json_decode(file_get_contents($configPath), true);
if (!is_array($configData) || !isset($configData['db']) || !is_array($configData['db'])) {
	print "Error: Invalid config/config.json: missing db object.<br>\n";
	exit;
}

$dbConfig = $configData['db'];
$dbHost = $dbConfig['host'] ?? 'localhost';
$dbPort = $dbConfig['port'] ?? '5432';
$dbName = $dbConfig['name'] ?? '';
$dbUser = $dbConfig['user'] ?? '';
$dbPass = $dbConfig['pass'] ?? '';
$dbSchema = $dbConfig['schema'] ?? 'public';

$dsn = 'pgsql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';user=' . $dbUser . ';password=' . $dbPass;
try {
	$dbh = new PDO($dsn);
	$dbh->exec("SET search_path TO " . $dbSchema . ", public");
} catch (PDOException $e) {
	print "Error!<br>\n";
	print_r($e);
	exit;
}
