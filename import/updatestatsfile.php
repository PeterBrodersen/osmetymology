<?php
// Create static stats file
require("../www/connect.inc.php");
$statefile = 'state.txt';
$statsjsonfile = '../www/data/stats.json';

print date("H:i:s") . ": Creating stats" . PHP_EOL;

$totalroads = $dbh->query('SELECT COUNT(*) FROM osmetymology.ways_agg')->fetchColumn();
$uniquenamedroads = $dbh->query('SELECT COUNT(DISTINCT name) FROM osmetymology.ways_agg')->fetchColumn();
$uniqueetymologywikidata = $dbh->query('SELECT COUNT(DISTINCT "name:etymology:wikidata") FROM osmetymology.ways_agg')->fetchColumn();
$localwikidataitems = $dbh->query('SELECT COUNT(*) FROM osmetymology.wikidata')->fetchColumn();
$importfiletime = NULL;
if (file_exists($statefile)) {
    if (preg_match('/^timestamp=(.*)$/m', file_get_contents($statefile), $match)) {
        $importfiletime = strtotime(stripslashes($match[1]));
    }
}

$stats = [
    'totalroads' => $totalroads,
    'uniquenamedroads' => $uniquenamedroads,
    'uniqueetymologywikidata' => $uniqueetymologywikidata,
    'localwikidataitems' => $localwikidataitems,
    'importfiletime' => $importfiletime
];
file_put_contents($statsjsonfile, json_encode($stats));
/*
SELECT COUNT(*) FROM osmetymology.ways_agg
SELECT COUNT(DISTINCT name) FROM osmetymology.ways_agg
SELECT COUNT(DISTINCT "name:etymology:wikidata") FROM osmetymology.ways_agg
SELECT COUNT(*) FROM osmetymology.wikidata
SELECT EXTRACT(epoch from now())::INT) ); -- Should be file date for 'denmark-latest.osm.pbf


INSERT INTO osmetymology.stats (label, value) VALUES ('totalroads', (SELECT COUNT(*) FROM osmetymology.ways_agg) );
INSERT INTO osmetymology.stats (label, value) VALUES ('uniquenamedroads', (SELECT COUNT(DISTINCT name) FROM osmetymology.ways_agg) );
INSERT INTO osmetymology.stats (label, value) VALUES ('uniqueetymologywikidata', (SELECT COUNT(DISTINCT "name:etymology:wikidata") FROM osmetymology.ways_agg) );
INSERT INTO osmetymology.stats (label, value) VALUES ('localwikidataitems', (SELECT COUNT(*) FROM osmetymology.wikidata) );
INSERT INTO osmetymology.stats (label, value) VALUES ('importfinishtime', (SELECT EXTRACT(epoch from now())::INT) ); -- Should be file date for 'denmark-latest.osm.pbf'?
*/