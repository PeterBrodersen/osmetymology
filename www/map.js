let map;
let highlightWayId;
let highlightWayPopup = false;
document.addEventListener("DOMContentLoaded", async () => {
    let minZoom = 12;
    let maxZoom = 19;
    var osmLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        minZoom,
        maxZoom,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });

    var wmsOrtoLayer = L.tileLayer.wms('https://api.dataforsyningen.dk/orto_foraar_DAF?service=WMS&token=5d6c5118e3f2ab00b8b2aa21e9140087&', {
        layers: 'orto_foraar_12_5',
        attribution: 'Indeholder data fra Styrelsen for Dataforsyning og Infrastruktur, Ortofoto Forår, WMS-tjeneste',
        minZoom,
        maxZoom
    });

    var Thunderforest_SpinalMap = L.tileLayer('https://{s}.tile.thunderforest.com/spinal-map/{z}/{x}/{y}.png?apikey=35178872612640c0abf67975149afa20', {
        attribution: '&copy; <a href="https://www.thunderforest.com/">Thunderforest</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        apikey: '35178872612640c0abf67975149afa20',
        minZoom,
        maxZoom
    });
    map = L.map('map', { fullscreenControl: true, layers: [osmLayer] }).setView([55.6794, 12.5740], 15);

    map.createPane('polygonsPane');
    map.getPane('polygonsPane').style.zIndex = 350;

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
        }),
        placeholder: 'Søg efter sted i Danmark'
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
        let placename = feature.properties["streetname"] ?? '(uden navn)';
        let popupText = `<h1 class="popupplacename" title="${placename}">${placename}</h1>`;
        let wikidataurlprefix = 'https://www.wikidata.org/wiki/';
        let wikipediadaurlprefix = 'https://da.wikipedia.org/w/index.php?title=';
        if (feature.properties["name:etymology:wikidata"]) {
            let wikidataId = feature.properties["name:etymology:wikidata"];
            let wikidatalabel = feature.properties["wikidata_label"];
            let wikibirth = feature.properties["wikidata_dateofbirth"];
            let wikideath = feature.properties["wikidata_dateofdeath"];
            let dateoptions = {
                // day: 'numeric',
                // month: 'short',
                year: 'numeric'
            }
            let wikipediatitleda = feature.properties["wikidata_wikipediatitleda"];
            let wikidatadescription = capitalizeFirstLetter(feature.properties["wikidata_description"] ?? '');
            popupText += `<div class="popupitemname">${wikidatalabel || ''}</div>`;
            if (wikibirth || wikideath) {
                let birthdeathtext = '(';
                if (wikibirth) {
                    birthdeathtext += new Date(wikibirth).toLocaleDateString('da-DK', dateoptions);
                }
                birthdeathtext += ' - ';
                if (wikideath) {
                    birthdeathtext += new Date(wikideath).toLocaleDateString('da-DK', dateoptions);
                }
                birthdeathtext += ')';
                popupText += `<div class="popupbirthdeath">${birthdeathtext}</div>`;
            }
            popupText += `<p>${wikidatadescription}</p>`;
            // Wikidata and Wikipedia links
            popupText += `<p>`;
            if (wikipediatitleda) {
                popupText += `<a href="${wikipediadaurlprefix}${encodeURI(wikipediatitleda)}">Wikipedia-artikel</a> - `;
            }
            popupText += `<a href="${wikidataurlprefix}${wikidataId}" class="wikidataname" data-wikidata="${wikidataId}">Wikidata-emne</a>`;
            popupText += `</p>`;
            if (/^(Q\d+)$/.test(wikidataId)) {
                popupText += `<p class="localsearch"><a href="#${wikidataId}" onclick="doSearch('${wikidataId}'); return false;">Find alle steder opkaldt efter dette emne</a></p>`
            }
        } else if (feature.properties["name:etymology"]) {
            let etymologyText = feature.properties["name:etymology"];
            popupText += `<div class="popupitemname">${etymologyText}</div>`;
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
    async function updateMapData() {
        // :TODO: Only remove old results when new are loaded. This might cause issues if more are loaded simultaneously 
        // remove the old results
        previousResults.remove();
        const nextResults = L.layerGroup().addTo(map);
        previousResults = nextResults;

        let statisticsData = [];

        // only fetch the relevant bbox subset of data
        let iter = flatgeobuf.deserialize('/data/aggregate.fgb', mapBoundingBox());
        for await (let feature of iter) {

            let gender = feature.properties['gender'] || 'none';
            statisticsData[gender] = (statisticsData[gender] ?? 0) + 1;

            const popupText = getPopupText(feature);
            let lineColor = getLineColorFromGender(feature);
            let highlightFeature = (feature.properties["id"] == highlightWayId);
            if (highlightFeature) {
                lineColor = '#cccc00ff';
            }
            const defaultStyle = {
                color: lineColor,
                weight: 7,
                fillOpacity: 0.1,
            };

            let mapFeature = L.geoJSON(feature, {
                style: defaultStyle,
                pointToLayer: function (feature, latlng) {
                    return L.circleMarker(latlng, {
                        radius: 6,
                        fillColor: lineColor,
                        color: "#000",
                        weight: 1,
                        opacity: 1,
                        fillOpacity: 0.8
                    });
                },
                pane: feature.geometry.type === 'Polygon' ? 'polygonsPane' : 'overlayPane'
            }).on({
                'mouseover': function (e) {
                    const layer = e.target;
                    layer.setStyle({
                        color: lineColor,
                        weight: 9,
                        fillOpacity: 0.7,
                    });
                    layer.bringToFront();
                },
                'mouseout': function (e) {
                    const layer = e.target;
                    layer.setStyle(defaultStyle);
                }
            }).bindPopup(popupText, { autoPan: false, className: 'place-popup' })
                .addTo(nextResults);
            if (highlightFeature && highlightWayPopup) {
                mapFeature.openPopup();
                highlightWayPopup = false; // should perhaps only be set if popup is closed?
            }
        }
        // Gender breakdown
        // console.table(Object.entries(statisticsData).sort((a, b) => b[1] - a[1]));
    }
    // if the user is panning around alot, only update once per second max
    updateMapData = _.throttle(updateMapData, 1000);

    // update on startup and on movement
    updateMapLink();
    updateMapData();
    map.on("moveend", () => {
        updateMapLink();
        updateMapData();
    });
    map.on("locationfound", (data) => {
        $(".resulttable").fadeTo("slow", 0.5);
        let coordinates = `${data.latlng.lat},${data.latlng.lng}`;
        map.panTo(data.latlng);
        $.getJSON("lookup.php", { coordinates })
            .done((data) => updateResultTable(data));
    });

});

function updateMapLink() {
    let coordLink = '' + map.getZoom() + '/' + parseFloat(map.getCenter().lat).toFixed(5) + '/' + parseFloat(map.getCenter().lng).toFixed(5);
    $("#copylinktomap").attr('href', '#map=' + coordLink);
}

function panToWayId(latitude, longitude, wayId) {
    highlightWayId = wayId;
    highlightWayPopup = true;
    map.panTo([latitude, longitude]);
}
