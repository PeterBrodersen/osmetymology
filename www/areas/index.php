<?php
require __DIR__ . '/../i18n.php';

$configPath = __DIR__ . '/../../config/config.json';
$translationPath = __DIR__ . '/../translations.json';
$placeName = '';
$translations = loadTranslationCatalogue($translationPath);
if (is_readable($configPath)) {
    $decodedConfig = json_decode(file_get_contents($configPath), true);
    if (is_array($decodedConfig)) {
        $placeName = $decodedConfig['place']['name'] ?? '';
    }
}
$decodedConfig = isset($decodedConfig) && is_array($decodedConfig) ? $decodedConfig : [];
$buildConfiguredI18nContext = 'buildConfiguredI18nContext';
$i18nContext = $buildConfiguredI18nContext($translations, $decodedConfig, static function (string $localeCode, string $localizedPlaceName): array {
    return [
        'placeName' => $localizedPlaceName,
        'projectDisplayName' => $localizedPlaceName,
    ];
});
$translations = $i18nContext['translations'];
$locale = $i18nContext['locale'];
$translationOverrides = $i18nContext['translationOverrides'];
$localeParams = $i18nContext['localeParams'];
$translationParams = $i18nContext['translationParams'] ?: [
    'placeName' => $placeName,
    'projectDisplayName' => $placeName,
];
$placeName = $translationParams['placeName'] ?? $placeName;
$titleKey = $placeName ? 'areas.titleWithPlace' : 'areas.title';
$headerKey = $placeName ? 'areas.headerWithPlace' : 'areas.header';
$title = translateCatalogue($translations, $locale, $titleKey, $translationParams);
$header = translateCatalogue($translations, $locale, $headerKey, $translationParams);
$appConfig = [
    'i18n' => buildI18nConfig($translations, $locale, 'areas', $translationParams, $localeParams, $translationOverrides),
];
?>
<!DOCTYPE html>
<html lang="<?php print htmlspecialchars($locale); ?>">

<head>
    <title data-i18n="<?php print htmlspecialchars($titleKey); ?>"><?php print htmlspecialchars($title); ?></title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.32.0/js/jquery.tablesorter.min.js" integrity="sha512-O/JP2r8BG27p5NOtVhwqsSokAwEP5RwYgvEzU9G6AfNjLYqyt2QT8jqU1XrXCiezS50Qp1i3ZtCQWkHZIRulGA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link href='/style.css' rel='stylesheet' />
    <script>
        window.appConfig = <?php echo json_encode($appConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="/i18n.js"></script>
    <script src="/areas/helper.js"></script>
    <meta property="og:image" content="https://navne.findvej.dk/media/l%C3%A6rkevej.png" />
    <meta property="og:image:width" content="1000" />
    <meta property="og:image:height" content="800" />
</head>

<body>
    <div class="page-header">
        <h1 data-i18n="<?php print htmlspecialchars($headerKey); ?>"><?php print htmlspecialchars($header); ?></h1>

        <div class="language-switcher">
            <label for="language-select" data-i18n="common.languageLabel"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'common.languageLabel', $translationParams)); ?></label>
            <select id="language-select"></select>
        </div>
    </div>
    <p data-i18n-html="areas.introHtml"><?php print translateCatalogue($translations, $locale, 'areas.introHtml', $translationParams); ?></p>
    <p>
        <span data-i18n="areas.datasetDate"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.datasetDate', $translationParams)); ?></span> <span id="importfiletime"></span>
    </p>

    <table id="areastats" class="resulttable">
        <thead>
            <tr>
                <th rowspan="2" data-i18n="areas.table.area"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.table.area', $translationParams)); ?></th>
                <th rowspan="2" data-i18n="areas.table.name"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.table.name', $translationParams)); ?></th>
                <th colspan="3" data-i18n="areas.table.humansExisted"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.table.humansExisted', $translationParams)); ?></th>
                <th colspan="3" data-i18n="areas.table.allHumansIncludingFictional"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.table.allHumansIncludingFictional', $translationParams)); ?></th>
            </tr>
            <tr>
                <th data-i18n="areas.table.women"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.table.women', $translationParams)); ?></th>
                <th data-i18n="areas.table.men"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.table.men', $translationParams)); ?></th>
                <th data-i18n="areas.table.percentageDistribution"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.table.percentageDistribution', $translationParams)); ?></th>
                <th data-i18n="areas.table.women"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.table.women', $translationParams)); ?></th>
                <th data-i18n="areas.table.men"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.table.men', $translationParams)); ?></th>
                <th data-i18n="areas.table.percentageDistribution"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.table.percentageDistribution', $translationParams)); ?></th>
            </tr>
        </thead>
        <tbody>

        </tbody>
        <tfoot>
            <tr>
                <th data-i18n="areas.table.area"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.table.area', $translationParams)); ?></th>
                <th data-i18n="areas.table.name"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.table.name', $translationParams)); ?></th>
                <th data-i18n-html="areas.table.womenHumans"><?php print translateCatalogue($translations, $locale, 'areas.table.womenHumans', $translationParams); ?></th>
                <th data-i18n-html="areas.table.menHumans"><?php print translateCatalogue($translations, $locale, 'areas.table.menHumans', $translationParams); ?></th>
                <th data-i18n-html="areas.table.percentageDistributionHumans"><?php print translateCatalogue($translations, $locale, 'areas.table.percentageDistributionHumans', $translationParams); ?></th>
                <th data-i18n-html="areas.table.womenAll"><?php print translateCatalogue($translations, $locale, 'areas.table.womenAll', $translationParams); ?></th>
                <th data-i18n-html="areas.table.menAll"><?php print translateCatalogue($translations, $locale, 'areas.table.menAll', $translationParams); ?></th>
                <th data-i18n="areas.table.percentageDistribution"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'areas.table.percentageDistribution', $translationParams)); ?></th>
            </tr>
        </tfoot>
    </table>

    <table class="resulttable" id="singlearea">
    </table>

    <div class="clear"></div>

    <p data-i18n-html="areas.sumNoteHtml"><?php print translateCatalogue($translations, $locale, 'areas.sumNoteHtml', $translationParams); ?></p>

    <div id="betaboilerplate">
        <p data-i18n-html="areas.roadsOnlyHtml"><?php print translateCatalogue($translations, $locale, 'areas.roadsOnlyHtml', $translationParams); ?></p>
        <p data-i18n-html="areas.projectHtml"><?php print translateCatalogue($translations, $locale, 'areas.projectHtml', $translationParams); ?></p>
        <p class="copyright" data-i18n-html="areas.copyrightHtml"><?php print translateCatalogue($translations, $locale, 'areas.copyrightHtml', $translationParams); ?></p>
    </div>

</body>

</html>