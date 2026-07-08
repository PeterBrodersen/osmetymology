<?php
require __DIR__ . '/i18n.php';

$appConfig = [];
$configPath = __DIR__ . '/../config/config.json';
$translationPath = __DIR__ . '/translations.json';
$placeName = '';
$translations = loadTranslationCatalogue($translationPath);
if (is_readable($configPath)) {
    $decodedConfig = json_decode(file_get_contents($configPath), true);
    if (is_array($decodedConfig)) {
        $appConfig = [
            'place' => is_array($decodedConfig['place'] ?? null) ? $decodedConfig['place'] : [],
            'external_urls' => is_array($decodedConfig['external_urls'] ?? null) ? $decodedConfig['external_urls'] : [],
        ];
        $placeName = $decodedConfig['place']['name'] ?? '';
    }
}
$decodedConfig = isset($decodedConfig) && is_array($decodedConfig) ? $decodedConfig : [];
$buildConfiguredI18nContext = 'buildConfiguredI18nContext';
$i18nContext = $buildConfiguredI18nContext($translations, $decodedConfig, static function (string $localeCode, string $localizedPlaceName, array $config): array {
    return [
        'placeName' => $localizedPlaceName,
        'projectDisplayName' => $localizedPlaceName,
        'geocodingCountryName' => $config['place']['geocoding_country_name'] ?? 'United Kingdom',
    ];
});
$translations = $i18nContext['translations'];
$locale = $i18nContext['locale'];
$translationOverrides = $i18nContext['translationOverrides'];
$localeParams = $i18nContext['localeParams'];
$translationParams = $i18nContext['translationParams'] ?: [
    'placeName' => $placeName,
    'projectDisplayName' => $placeName,
    'geocodingCountryName' => $appConfig['place']['geocoding_country_name'] ?? 'United Kingdom',
];
$placeName = $translationParams['placeName'] ?? $placeName;
$titleKey = $placeName ? 'home.titleWithPlace' : 'home.title';
$title = translateCatalogue($translations, $locale, $titleKey, $translationParams);
$appConfig['i18n'] = buildI18nConfig($translations, $locale, 'home', $translationParams, $localeParams, $translationOverrides);
?>
<!DOCTYPE html>
<html lang="<?php print htmlspecialchars($locale); ?>">

<head>
    <title data-i18n="<?php print htmlspecialchars($titleKey); ?>"><?php print htmlspecialchars($title); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/underscore@1.13.1/underscore-min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.3/dist/leaflet.css" integrity="sha256-kLaT2GOSpHechhsozzB+flnD+zUyjE2LlfWPgU04xyI=" crossorigin="" />
    <script src="https://cdn.jsdelivr.net/npm/leaflet/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatgeobuf/dist/flatgeobuf-geojson.min.js"></script>
    <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
    <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css' rel='stylesheet' />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.14.0/jquery-ui.js"></script>
    <script src="https://code.jquery.com/color/jquery.color-2.1.2.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.0/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <script src="https://cdn.jsdelivr.net/npm/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    <script>
        window.appConfig = <?php echo json_encode($appConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol/dist/L.Control.Locate.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol/dist/L.Control.Locate.min.js" charset="utf-8"></script> -->
    <script src="/i18n.js"></script>
    <script src="/map.js"></script>
    <script src="/helper.js"></script>
    <link href='/style.css' rel='stylesheet' />
    <meta property="og:image" content="https://navne.findvej.dk/media/l%C3%A6rkevej.png" />
    <meta property="og:image:width" content="1000" />
    <meta property="og:image:height" content="800" />
</head>

<body>
    <div class="page-header">
        <h1 data-i18n="<?php print htmlspecialchars($titleKey); ?>"><?php print htmlspecialchars($title); ?></h1>

        <div class="language-switcher">
            <label for="language-select" data-i18n="common.languageLabel"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'common.languageLabel', $translationParams)); ?></label>
            <select id="language-select"></select>
        </div>
    </div>

    <div style="clear: both;">
    </div>

    <div id="userinput">
        <div id="placename"><input required autofocus id="namefind" data-i18n-placeholder="home.searchPlaceholder" placeholder="<?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.searchPlaceholder', $translationParams)); ?>" accesskey="f" size="35"> <span id="copylink"><a href="#" data-i18n="common.copyLink"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'common.copyLink', $translationParams)); ?></a></span></div>
        <div id="itemname"><input required autofocus id="itemfind" data-i18n-placeholder="home.topicPlaceholder" placeholder="<?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.topicPlaceholder', $translationParams)); ?>" accesskey="t"></div>
    </div>

    <div id="result">
    </div>

    <template id="tabletemplate">
        <table class="resulttable">
            <thead>
                <tr class="tableheader">
                    <th data-i18n="home.table.map"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.table.map', $translationParams)); ?></th>
                    <th data-i18n="home.table.type"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.table.type', $translationParams)); ?></th>
                    <th data-i18n="home.table.name"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.table.name', $translationParams)); ?></th>
                    <th data-i18n="home.table.area"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.table.area', $translationParams)); ?></th>
                    <th data-i18n="home.table.topic"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.table.topic', $translationParams)); ?></th>
                    <th data-i18n="home.table.description"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.table.description', $translationParams)); ?></th>
                </tr>
            </thead>
        </table>
    </template>

    <div id="maplinks">
        <div>
            <a href="#" id="getposition" data-i18n="home.findPlacesNearYou"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.findPlacesNearYou', $translationParams)); ?></a> <span class="location-loader" style="display:none;"></span>
        </div>
        <div>
            <a href="#" id="showplacesinmapview" data-i18n="home.listPlacesInMapView"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.listPlacesInMapView', $translationParams)); ?></a><br>
        </div>
        <div>
            <a href="#" id="copylinktomap" data-i18n="common.copyLinkToMap"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'common.copyLinkToMap', $translationParams)); ?></a><br>
        </div>

    </div>
    <div class="drlink">
        <p>
            <span data-i18n="home.alsoCheckOut"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.alsoCheckOut', $translationParams)); ?></span>
        </p>
        <ul>
            <li><a href="areas/" data-i18n="home.genderDistributionLink"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.genderDistributionLink', $translationParams)); ?></a></li>
            <li id="osrm_gender"><a href="#" id="avoid_gender" data-i18n="home.avoidGenderLink"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.avoidGenderLink', $translationParams)); ?></a> (<a href="#" id="avoid_gender_example" data-i18n="home.example"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.example', $translationParams)); ?></a>)</li>
        </ul>
    </div>

    <div id="map" style="height: 700px; width: 100%; border: 1px solid black; z-index: 90; margin-top: 10px;"></div>

    <div id="betaboilerplate">
        <p data-i18n-html="home.downloadsHtml"><?php print translateCatalogue($translations, $locale, 'home.downloadsHtml', $translationParams); ?></p>
        <p data-i18n-html="home.projectHtml"><?php print translateCatalogue($translations, $locale, 'home.projectHtml', $translationParams); ?></p>

        <p class="copyright" data-i18n-html="home.copyrightHtml"><?php print translateCatalogue($translations, $locale, 'home.copyrightHtml', $translationParams); ?></p>

        <p class="stats">
            <span data-i18n="home.stats.content"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.stats.content', $translationParams)); ?></span><br>
            <span data-i18n="home.stats.places"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.stats.places', $translationParams)); ?></span> <span id="totalroads"></span><br>
            <span data-i18n="home.stats.uniquelyNamedPlaces"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.stats.uniquelyNamedPlaces', $translationParams)); ?></span> <span id="uniquenamedroads"></span><br>
            <span data-i18n="home.stats.uniqueTopics"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.stats.uniqueTopics', $translationParams)); ?></span> <span id="uniqueetymologywikidata"></span><br>
            <span data-i18n="home.stats.datasetDate"><?php print htmlspecialchars(translateCatalogue($translations, $locale, 'home.stats.datasetDate', $translationParams)); ?></span> <span id="importfiletime"></span>
        </p>
    </div>
</body>

</html>