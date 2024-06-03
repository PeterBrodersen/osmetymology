<!DOCTYPE html>
<html>
    <head>
        <title>
            Hvad er danske vejnavne opkaldt efter?
        </title>
<!-- <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css"
     integrity="sha256-kLaT2GOSpHechhsozzB+flnD+zUyjE2LlfWPgU04xyI="
     crossorigin=""/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/flatgeobuf/dist/flatgeobuf-geojson.min.js"></script>
<script src="https://unpkg.com/json-formatter-js"></script>
<script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
<link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css' rel='stylesheet' /> -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/helper.js"></script>
<style>
    h1 {
        font-family: sans-serif;
    }
    table.resulttable, table.resulttable tr, table.resulttable th, table.resulttable td {
        font-family: sans-serif;
        border: solid 1px black;
        padding: 5px;
    }
    table.resulttable {
        font-family: sans-serif;
        border: solid 1px black;
        border-collapse: collapse;
    }

    table.resulttable tr#tableheader {
        background-color: #ddd;
    }
    div#userinput {
        margin-bottom: 2em;
    }

</style>
</head>
<body>
    <h1>Hvad er danske vejnavne opkaldt efter?</h1>

	<div id="userinput"><input required autofocus id="namefind"></div>
	
    <div id="result">
    </div>

    <template id="tabletemplate">
        <table class="resulttable">
                <tr class="tableheader"><th>Vejnavn</th><th>Kommune</th><th>Wikidata-emne</th><th>Beskrivelse</th></tr>
        </table>
    </template>

</body>
</html>
<?php
exit;
?>
    <div id="header">
        <h3>Parsed header content</h3>
    </div>

<div id="map" style="height: 700px; width: 100%; border: 1px solid black; z-index: 90; margin-top: 10px;">
<script>

        document.addEventListener("DOMContentLoaded", async () => { 
            // basic OSM Leaflet map
            let map = L.map('map').setView([55.6794, 12.5740], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

           // optionally show some meta-data about the FGB file
            function handleHeaderMeta(headerMeta) {
                const header = document.getElementById('header')
                const formatter = new JSONFormatter(headerMeta, 10)
                header.appendChild(formatter.render())
            }

            const response = await fetch('/data/aggregate.fgb');
            for await (let feature of flatgeobuf.deserialize(response.body, undefined, handleHeaderMeta)) {
                // Leaflet styling
                const defaultStyle = { 
                    color: 'blue', 
                    weight: 2, 
                    fillOpacity: 0.2,
                };

                // Add the feature to the map
                L.geoJSON(feature, { 
                    style: defaultStyle 
                }).on({
                    // highlight on hover
                    'mouseover': function(e) {
                        const layer = e.target;
                        layer.setStyle({
                            color: 'blue',
                            weight: 4,
                            fillOpacity: 0.7,
                        });
                        layer.bringToFront();
                    },
                    // remove highlight when hover stops
                    'mouseout': function(e) {
                        const layer = e.target;
                        layer.setStyle(defaultStyle);
                    }
                })
                // show some per-feature properties when clicking on the feature
                .bindPopup(`<h1>${feature.properties["name"]} ${feature.properties["name:etymology:wikidata"]}</h1>`)
                .addTo(map);
            }
        });

/*

var osmLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
	maxZoom: 19,
	attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
});

var wmsOrtoLayer = L.tileLayer.wms('https://api.dataforsyningen.dk/orto_foraar_DAF?service=WMS&token=5d6c5118e3f2ab00b8b2aa21e9140087&', {
	layers: 'orto_foraar_12_5',
	attribution: 'Indeholder data fra Styrelsen for Dataforsyning og Infrastruktur, Ortofoto Forår, WMS-tjeneste'
});

var Thunderforest_SpinalMap = L.tileLayer('https://{s}.tile.thunderforest.com/spinal-map/{z}/{x}/{y}.png?apikey=35178872612640c0abf67975149afa20', {
	attribution: '&copy; <a href="http://www.thunderforest.com/">Thunderforest</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
	apikey: '35178872612640c0abf67975149afa20',
	maxZoom: 19
});
let map = L.map('map', {fullscreenControl: true, layers: [osmLayer] }).setView([55.67947149702628, 12.5740236666392], 15);

var baseMaps = {
	"OpenStreetMap": osmLayer,
	"Luftfoto": wmsOrtoLayer,
	"Spinal Map": Thunderforest_SpinalMap,
}
var layerControl = L.control.layers(baseMaps).addTo(map);
L.control.scale().addTo(map);

function addGeoJSONToMap(geojson) {
    L.geoJSON(geojson).addTo(map);
}

async function loadFlatGeobuf(url) {
    const response = await fetch(url);
    const arrayBuffer = await response.arrayBuffer();
    const geojson = await flatgeobuf.deserialize(arrayBuffer);
    addGeoJSONToMap(geojson);
}

const flatGeobufUrl = '/data/aggregate.fgb';

*/


</script>


    </body>
</html>
