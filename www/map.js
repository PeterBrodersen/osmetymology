let map;
document.addEventListener("DOMContentLoaded", async () => {
    // basic OSM Leaflet map
    // let map = L.map('map').setView([39, -104], 6);
    map = L.map('map').setView([55.6794, 12.5740], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        minZoom: 14,
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // optionally show some meta-data about the FGB file
    function handleHeaderMeta(headerMeta) {
        const header = document.getElementById('header')
        const formatter = new JSONFormatter(headerMeta, 10)
        header.appendChild(formatter.render())
    }

    function mapBoundingBox() {
        const bounds = map.getBounds();
        return {
            minX: bounds.getWest(),
            minY: bounds.getSouth(),
            maxX: bounds.getEast(),
            maxY: bounds.getNorth(),
        };
    }

    function getPopupText(feature) {
        let popupText = `<h1>${feature.properties["name"] ?? '(ukendt navn))'}</h1>`;
        let wikidataurlprefix = 'https://www.wikidata.org/wiki/';
        if (feature.properties["name:etymology:wikidata"]) {
            let wikidataId = feature.properties["name:etymology:wikidata"];
            popupText += `<div><a href="${wikidataurlprefix}${wikidataId}" class="wikidataname" data-wikidata="${wikidataId}">${wikidataId}</a> <sup><a href="#${wikidataId}" onclick="doSearch('${wikidataId}'); return false;">[Søg]</a></sup></div>`;
        }
        if (feature.properties["name:etymology"]) {
            let etymologyText = feature.properties["name:etymology"];
            popupText += `<div>${etymologyText}</div>`;
        }
        return popupText;

    }

    // track the previous results so we can remove them when adding new results
    let previousResults = L.layerGroup().addTo(map);
    async function updateResults() {
        // remove the old results
        previousResults.remove();
        const nextResults = L.layerGroup().addTo(map);
        previousResults = nextResults;

        // Use flatgeobuf JavaScript API to iterate features as geojson.
        // Because we specify a bounding box, flatgeobuf will only fetch the relevant subset of data,
        // rather than the entire file.
        let iter = flatgeobuf.deserialize('/data/aggregate.fgb', mapBoundingBox());
        for await (let feature of iter) {
            const defaultStyle = {
                color: '#0000ff66',
                weight: 3,
                fillOpacity: 0.1,
            };

            const popupText = getPopupText(feature);

            L.geoJSON(feature, {
                style: defaultStyle,
            }).on({
                'mouseover': function (e) {
                    const layer = e.target;
                    layer.setStyle({
                        color: '#0000ff66',
                        weight: 4,
                        fillOpacity: 0.7,
                    });
                    layer.bringToFront();
                },
                'mouseout': function (e) {
                    const layer = e.target;
                    layer.setStyle(defaultStyle);
                }
            }).bindPopup(popupText)
                .addTo(nextResults);
        }
    }
    // if the user is panning around alot, only update once per second max
    updateResults = _.throttle(updateResults, 1000);

    // show results based on the initial map
    updateResults();

    // ...and update the results whenever the map moves
    map.on("moveend", function (s) {
        updateResults();
    });
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