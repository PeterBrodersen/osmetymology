<?php
// Reduce existing Wikidata JSON fields to the configured project languages.
require(__DIR__ . '/../www/connect.inc.php');
require(__DIR__ . '/wikidata_language.php');

$configPath = __DIR__ . '/../config/config.json';
$configData = json_decode(file_get_contents($configPath), true);
$languages = $configData['language']['wikidata'] ?? [];
if (!is_array($languages) || count($languages) === 0) {
    die("Error: language.wikidata must contain at least one language." . PHP_EOL);
}

$tableExists = $dbh->query("SELECT to_regclass('wikidata') IS NOT NULL")->fetchColumn();
if (!$tableExists) {
    die("Error: Table wikidata does not exist." . PHP_EOL);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);

$processed = 0;
$changed = 0;
try {
    if (!$dryRun) {
        $dbh->beginTransaction();
    }
    if (!$dryRun) {
        $dbh->exec('ALTER TABLE wikidata ADD COLUMN IF NOT EXISTS wikipedia TEXT');
    }
    $select = $dbh->query('SELECT id, labels, descriptions, sitelinks, aliases FROM wikidata ORDER BY id');
    $update = null;
    if (!$dryRun) {
        $update = $dbh->prepare('
            UPDATE wikidata
            SET name = ?, description = ?, labels = ?::jsonb, descriptions = ?::jsonb,
                sitelinks = ?::jsonb, aliases = ?::jsonb, wikipedia = ?
            WHERE id = ?
        ');
    }

    while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
        $labels = json_decode($row['labels'] ?? '{}', true) ?: [];
        $descriptions = json_decode($row['descriptions'] ?? '{}', true) ?: [];
        $sitelinks = json_decode($row['sitelinks'] ?? '{}', true) ?: [];
        $aliases = json_decode($row['aliases'] ?? '{}', true) ?: [];
        $reducedLabels = reduceWikidataLanguageMap($labels, $languages);
        $reducedDescriptions = reduceWikidataLanguageMap($descriptions, $languages);
        $reducedSitelinks = reduceWikidataSitelinks($sitelinks, $languages);
        if (count($sitelinks) > 0 && count($reducedSitelinks) === 0) {
            throw new RuntimeException('Sitelink reduction produced no values for Wikidata row ' . $row['id'] . '. No changes were committed.');
        }
        $primaryLanguage = $languages[0];
        $reducedAliases = [];
        if (array_key_exists($primaryLanguage, $aliases)) {
            $reducedAliases[$primaryLanguage] = $aliases[$primaryLanguage];
        }
        $name = getBestWikidataValue($reducedLabels, $languages);
        $description = getBestWikidataValue($reducedDescriptions, $languages);
        $wikipedia = getBestWikidataWikipediaUrl($reducedSitelinks, $languages);
        $changed++;

        if (!$dryRun) {
            $update->execute([
                $name,
                $description,
                json_encode($reducedLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($reducedDescriptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($reducedSitelinks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($reducedAliases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $wikipedia,
                $row['id'],
            ]);
        }
        $processed++;
    }

    if (!$dryRun) {
        $dbh->commit();
    }
} catch (Throwable $error) {
    if (!$dryRun && $dbh->inTransaction()) {
        $dbh->rollBack();
    }
    die("Error reducing Wikidata data: " . $error->getMessage() . PHP_EOL);
}

$mode = $dryRun ? 'Dry run' : 'Reduced';
print date('H:i:s') . ": $mode $processed Wikidata rows using languages: " . implode(', ', $languages) . PHP_EOL;
if ($dryRun) {
    print "No database changes were made." . PHP_EOL;
}
