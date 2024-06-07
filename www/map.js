let map;
let highlightWayId;
document.addEventListener("DOMContentLoaded", async () => {
    let minZoom = 12;
    let maxZoom = 19;
    var osmLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        minZoom,
        maxZoom,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });

    var wmsOrtoLayer = L.tileLayer.wms('https://api.dataforsyningen.dk/orto_foraar_DAF?service=WMS&token=5d6c5118e3f2ab00b8b2aa21e9140087&', {
        layers: 'orto_foraar_12_5',
        attribution: 'Indeholder data fra Styrelsen for Dataforsyning og Infrastruktur, Ortofoto Forår, WMS-tjeneste',
        minZoom,
        maxZoom
    });

    var Thunderforest_SpinalMap = L.tileLayer('https://{s}.tile.thunderforest.com/spinal-map/{z}/{x}/{y}.png?apikey=35178872612640c0abf67975149afa20', {
        attribution: '&copy; <a href="http://www.thunderforest.com/">Thunderforest</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        apikey: '35178872612640c0abf67975149afa20',
        minZoom,
        maxZoom
    });
    map = L.map('map', { fullscreenControl: true, layers: [osmLayer] }).setView([55.6794, 12.5740], 15);

    var baseMaps = {
        "OpenStreetMap": osmLayer,
        "Luftfoto": wmsOrtoLayer,
        "Spinal Map": Thunderforest_SpinalMap,
    }
    var geocoderOptions = {
        geocoder: new L.Control.Geocoder.nominatim({
            geocodingQueryParams: {
                "countrycodes": "dk"
            }
        })
    };

    var layerControl = L.control.layers(baseMaps).addTo(map);
    L.control.scale().addTo(map);

    L.Control.geocoder(geocoderOptions).addTo(map);

    function mapBoundingBox() {
        const bounds = map.getBounds();
        return {
            minX: bounds.getWest(),
            minY: bounds.getSouth(),
            maxX: bounds.getEast(),
            maxY: bounds.getNorth(),
        };
    }

    function capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    function getPopupText(feature) {
        let popupText = `<h1>${feature.properties["streetname"] ?? '(uden navn)'}</h1>`;
        let wikidataurlprefix = 'https://www.wikidata.org/wiki/';
        if (feature.properties["name:etymology:wikidata"]) {
            let wikidataId = feature.properties["name:etymology:wikidata"];
            let wikidatalabel = feature.properties["wikidata_label"];
            let wikidatadescription = capitalizeFirstLetter(feature.properties["wikidata_description"] ?? '');
            popupText += `<div>Opkaldt efter: <a href="${wikidataurlprefix}${wikidataId}" class="wikidataname" data-wikidata="${wikidataId}">${wikidatalabel}</a> <sup><a href="#${wikidataId}" onclick="doSearch('${wikidataId}'); return false;">[Søg]</a></sup></div>`;
            popupText += `<div>${wikidatadescription}</div>`;
        }
        if (feature.properties["name:etymology"]) {
            let etymologyText = feature.properties["name:etymology"];
            popupText += `<div>${etymologyText}</div>`;
        }
        return popupText;
    }

    function getLineColorFromGender(feature) {
        let lineColor = '#00cc0099';
        if (feature.properties['gender'] == 'male') {
            lineColor = '#0000ff99';
        } else if (feature.properties['gender'] == 'female') {
            lineColor = '#ff000099';
        }
        return lineColor;
    }

    // track the previous results so we can remove them when adding new results
    // :TODO: Show spinner when loading
    let previousResults = L.layerGroup().addTo(map);
    async function updateResults() {
        // remove the old results
        previousResults.remove();
        const nextResults = L.layerGroup().addTo(map);
        previousResults = nextResults;

        // only fetch the relevant bbox subset of data
        let iter = flatgeobuf.deserialize('/data/aggregate.fgb', mapBoundingBox());
        for await (let feature of iter) {

            const popupText = getPopupText(feature);
            let lineColor = getLineColorFromGender(feature);
            if (feature.properties["id"] == highlightWayId) {
                lineColor = '#cccc00ff';
            }

            const defaultStyle = {
                color: lineColor,
                weight: 5,
                fillOpacity: 0.1,
            };

            L.geoJSON(feature, {
                style: defaultStyle,
            }).on({
                'mouseover': function (e) {
                    const layer = e.target;
                    layer.setStyle({
                        color: lineColor,
                        weight: 7,
                        fillOpacity: 0.7,
                    });
                    layer.bringToFront();
                },
                'mouseout': function (e) {
                    const layer = e.target;
                    layer.setStyle(defaultStyle);
                }
            }).bindPopup(popupText, { autoPan: false })
                .addTo(nextResults);
        }
    }
    // if the user is panning around alot, only update once per second max
    updateResults = _.throttle(updateResults, 1000);

    // update on startup and on movement
    updateResults();
    map.on("moveend", function (s) {
        updateResults();
    });
});

function panToWayId(latitude, longitude, wayId) {
    highlightWayId = wayId;
    map.panTo([latitude, longitude]);

}