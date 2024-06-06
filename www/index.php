<!DOCTYPE html>
<html>

<head>
    <title>
        Hvad er danske vejnavne opkaldt efter?
    </title>
    <script src="https://unpkg.com/underscore@1.13.1/underscore-min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" integrity="sha256-kLaT2GOSpHechhsozzB+flnD+zUyjE2LlfWPgU04xyI=" crossorigin="" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/flatgeobuf/dist/flatgeobuf-geojson.min.js"></script>
    <script src="https://unpkg.com/json-formatter-js"></script>
    <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
    <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css' rel='stylesheet' />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/color/jquery.color-2.1.2.min.js"></script>
    <script src="/map.js"></script>
    <script src="/helper.js"></script>
    <link href='/style.css' rel='stylesheet' />
    <meta property="og:image" content="https://navne.findvej.dk/l%C3%A6rkevej.png" />
    <meta property="og:image:width" content="1000" />
    <meta property="og:image:height" content="800" />
</head>

<body>
    <h1>Hvad er danske vejnavne opkaldt efter?</h1>

    <div id="userinput"><input required autofocus id="namefind" placeholder="Indtast vejnavn"> <span id="copylink"><a href="#">[kopiér link]</a></span></div>

    <div id="result">
    </div>

    <template id="tabletemplate">
        <table class="resulttable">
            <tr class="tableheader">
                <th>Kort</th>
                <th>Vejnavn</th>
                <th>Kommune</th>
                <th>Wikidata-emne</th>
                <th>Beskrivelse</th>
            </tr>
        </table>
    </template>

    <div id="map" style="height: 700px; width: 100%; border: 1px solid black; z-index: 90; margin-top: 10px;"></div>

</body>

</html>