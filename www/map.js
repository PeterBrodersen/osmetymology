let map;
let highlightWayId = false;
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

    function getPopupText(feature) {
        // :TODO: URLs probably don't support relations at the moment
        let osmURLs = {
            point: 'https://www.openstreetmap.org/node/',
            line: 'https://www.openstreetmap.org/way/',
            polygon: 'https://www.openstreetmap.org/way/',
            relation: 'https://www.openstreetmap.org/relation/',
        }
        let mapCompleteEtymologyURLs = {
            point: 'https://mapcomplete.org/etymology.html#node/',
            line: 'https://mapcomplete.org/etymology.html#way/',
            polygon: 'https://mapcomplete.org/etymology.html#way/',
            relation: 'https://mapcomplete.org/etymology.html#relation/'
        }
        let placename = feature.properties["streetname"] ?? '(uden navn)';
        let etymologyText = feature.properties["name:etymology"];
        let popupText = `<h1 class="popupplacename" title="${placename}">${placename}</h1>`;
        let wikidataset = feature.properties["wikidataset"];
        let wikidataurlprefix = 'https://www.wikidata.org/wiki/';
        let wikipediadaurlprefix = 'https://da.wikipedia.org/w/index.php?title=';
        if (wikidataset) {
            let sections = [];
            let dateoptions = {
                // day: 'numeric',
                // month: 'short',
                year: 'numeric'
            }
            let wikilabel = feature.properties["wikilabel"];
            if (typeof wikidataset === 'string') {
                try {
                    wikidataset = JSON.parse(wikidataset);
                } catch (e) {
                    wikidataset = [];
                }
            }
            if (etymologyText && etymologyText != wikilabel) {
                popupText += `<p><em>${etymologyText}</em></p>`;
            }
            for (item of wikidataset) {
                var sectiontext = '';
                let wikidataId = item["itemid"];
                let wikidatalabel = item["label"];
                let wikibirth = item["dateofbirth"];
                let wikideath = item["dateofdeath"];
                let wikipediatitleda = item["wikipediatitleda"];
                let wikidatadescription = capitalizeFirstLetter(item["description"] ?? '');
                sectiontext += `<div class="popupitemname">${wikidatalabel || ''}</div>`;
                if (wikibirth || wikideath) {
                    let birthdeathtext = '(';
                    if (wikibirth) {
                        if (typeof wikibirth === 'string' && wikibirth.trim().endsWith('BC')) {
                            // Handle BC date string, e.g. "0600-01-01 BC"
                            let year = wikibirth.match(/^(\d{1,4})/);
                            birthdeathtext += (year ? year[1] : wikibirth) + ' f.Kr.';
                        } else {
                            birthdeathtext += new Date(wikibirth).toLocaleDateString('da-DK', dateoptions);
                        }
                    }
                    birthdeathtext += ' - ';
                    if (wikideath) {
                        if (typeof wikideath === 'string' && wikideath.trim().endsWith('BC')) {
                            let year = wikideath.match(/^(\d{1,4})/);
                            birthdeathtext += (year ? year[1] : wikideath) + ' f.Kr.';
                        } else {
                            birthdeathtext += new Date(wikideath).toLocaleDateString('da-DK', dateoptions);
                        }
                    }
                    birthdeathtext += ')';
                    sectiontext += `<div class="popupbirthdeath">${birthdeathtext}</div>`;
                }
                if (wikidatadescription) {
                    sectiontext += `<p>${wikidatadescription}</p>`;
                }
                // Wikidata and Wikipedia links
                sectiontext += `<p>`;
                if (wikipediatitleda) {
                    sectiontext += `<a href="${wikipediadaurlprefix}${encodeURI(wikipediatitleda)}">Wikipedia-artikel</a> - `;
                }
                sectiontext += `<a href="${wikidataurlprefix}${wikidataId}" class="wikidataname" data-wikidata="${wikidataId}">Wikidata-emne</a>`;
                sectiontext += `</p>`;
                sectiontext += `<p class="localsearch"><a href="#${wikidataId}" onclick="doSearch('${wikidataId}'); return false;">Find alle steder opkaldt efter dette emne</a></p>`;
                sections.push(sectiontext);
            }
            popupText += sections.map(section => `<div>${section}</div>`).join('\n');
        } else if (etymologyText) {
            popupText += `<div class="popupitemname">${etymologyText}</div>`;
        }
        let osmurl = (feature.properties["sampleobject_id"] > 0 ? osmURLs[feature.properties["geomtype"]] : osmURLs.relation) + Math.abs(feature.properties["sampleobject_id"]);
        let mapcompleteurl = (feature.properties["sampleobject_id"] > 0 ? mapCompleteEtymologyURLs[feature.properties["geomtype"]] : mapCompleteEtymologyURLs.relation) + Math.abs(feature.properties["sampleobject_id"]);
        popupText += `<div><a href="${osmurl}" title="Se stedet på OpenStreetMap.org"><img src="media/openstreetmap_30.png" width="30" height="30" alt="OpenStreetMap Logo"></a> <a href="${mapcompleteurl}" title="Ret stedet på MapComplete"><img src="media/mapcomplete.svg" width="30" height="30" alt="MapComplete Logo"></a></div>`;
        return popupText;
    }

    function getLineColorFromGender(feature) {
        let lineColor = '#00cc0099';
        if (feature.properties['gender'] == 'male') {
            lineColor = '#2244ff99';
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
        let previousHighlightWayId = highlightWayId; // Store the current highlightWayId
        previousResults.remove();
        const nextResults = L.layerGroup().addTo(map);
        previousResults = nextResults;
        highlightWayId = previousHighlightWayId; // Restore the highlightWayId

        let statisticsData = [];

        // only fetch the relevant bbox subset of data
        let iter = flatgeobuf.deserialize('/data/navne.fgb', mapBoundingBox(), false, true);
        for await (let feature of iter) {

            let hasWikidata = feature.properties["name:etymology:wikidata"];
            let gender = feature.properties['gender'] || 'none';
            statisticsData[gender] = (statisticsData[gender] ?? 0) + 1;

            const popupText = getPopupText(feature);
            let lineColor = getLineColorFromGender(feature);
            let highlightColor = '#cccc00ff';
            let highlightFeature = (feature.properties["id"] == highlightWayId);
            if (highlightFeature) {
                lineColor = highlightColor;
            }
            let defaultStyle = {
                color: lineColor,
                weight: 7,
                fillOpacity: 0.1,
            };
            if (!hasWikidata) {
                defaultStyle.dashArray = '9, 11';
            }

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
                        weight: 9,
                        fillOpacity: 0.7,
                    });
                    layer.bringToFront();
                },
                'mouseout': function (e) {
                    const layer = e.target;
                    layer.setStyle({ weight: 7, fillOpacity: 0.1 });
                },
                'popupopen': function (e) {
                    highlightWayId = feature.properties["id"];
                    e.target.setStyle({ color: highlightColor });
                },
                'popupclose': function (e) {
                    highlightWayId = false;
                    e.target.setStyle({ color: getLineColorFromGender(feature) });
                }
            }).bindPopup(popupText, { autoPan: false, className: 'place-popup' })
                .addTo(nextResults);
            if (highlightFeature) {
                mapFeature.openPopup();
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
    map.on('locationfound', (data) => {
        // $(".resulttable").fadeTo("slow", 0.5);
        $("#result").html('Henter nærmeste steder ...');
        let coordinates = `${data.latlng.lat},${data.latlng.lng}`;
        map.panTo(data.latlng);
        const radius = data.accuracy / 2;
        const locationMarker = L.marker(data.latlng).addTo(map)
            .bindPopup(`Du er inden for ${Math.round(radius).toLocaleString()} meter af dette punkt`).openPopup();
        const locationCircle = L.circle(data.latlng, radius).addTo(map);
        $.getJSON("lookup.php", { coordinates })
            .fail((jqxhr, textStatus, error) => updateResultTableError(error))
            .done((data) => updateResultTable(data));
    });
    map.on('locationerror', (e) => {
        $("#result").html('Kan ikke finde din position: ' + e.message);
        console.log(e);
    });
});

function updateMapLink() {
    let coordLink = '' + map.getZoom() + '/' + parseFloat(map.getCenter().lat).toFixed(5) + '/' + parseFloat(map.getCenter().lng).toFixed(5);
    $("#copylinktomap").attr('href', '#map=' + coordLink);
}

function panToWayId(latitude, longitude, wayId) {
    highlightWayId = wayId;
    map.panTo([latitude, longitude]);
}

function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}
