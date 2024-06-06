<?php
// Import all existing Wikidata items to local table
require("../www/connect.inc.php");

$itemIds = [];
$itemLimit = 50;
$apiurlprefix = 'https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=';

function getBestLabel($labels)
{ // Run through languages and search for existing value; pick first existing
    $languages = ['da', 'en', 'sv', 'nb', 'de', 'es', 'fr', 'fi', 'is'];
    $label = NULL;
    foreach ($languages as $language) {
        if (isset($labels->$language)) {
            $label = $labels->$language->value;
            break;
        }
    }
    return $label;
}

$dbh->query("TRUNCATE TABLE osmetymology.wikidata");

$insertdb = $dbh->prepare('
    INSERT INTO osmetymology.wikidata (itemid, name, description, claims, sitelinks)
    VALUES (?,?,?,?,?)
');

// :TODO: Split entities
$itemIds = $dbh->query(
    <<<EOD
    WITH split_content AS (
        SELECT trim(both ' ' FROM unnest(string_to_array("name:etymology:wikidata", ';'))) AS single_items
        FROM osmetymology.ways_agg
    )
    SELECT DISTINCT single_items FROM split_content WHERE single_items ~ '^Q\d+$'
    EOD,
    PDO::FETCH_COLUMN,
    0
)->fetchAll();

$chunks = array_chunk($itemIds, $itemLimit);
foreach ($chunks as $chunkid => $chunk) {
    print "Chunk $chunkid of " . (count($chunks) - 1) . PHP_EOL;
    $itemList = implode('|', $chunk);
    $url = $apiurlprefix . $itemList;
    $jsonResult = json_decode(file_get_contents($url)); // TODO: Error handling
    foreach ($jsonResult->entities as $entity) {
        $pageid = $entity->id;
        $name = getBestLabel($entity->labels);
        $description = getBestLabel($entity->descriptions);
        $claims = json_encode($entity->claims);
        $sitelinks = json_encode($entity->sitelinks);
        $insertdb->execute([$pageid, $name, $description, $claims, $sitelinks]);
    }
}

// Should be unique, but a duplicate might occur? Probably because  Q122811105 redirects to Q12319189.
// We should check the original itemId (and live with duplicates)
$dbh->query('CREATE INDEX wikidata_itemid_idx ON osmetymology.wikidata ("itemid")');
