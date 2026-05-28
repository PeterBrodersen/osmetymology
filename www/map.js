let map;
let highlightWayId = false;
const _wikidataSourceData = {};
const mapConfig = window.appConfig || {};
document.addEventListener("DOMContentLoaded", async () => {
    const placeConfig = mapConfig.place || {};
    const startLat = Number.isFinite(Number(placeConfig.lat)) ? Number(placeConfig.lat) : 51.5;
    const startLng = Number.isFinite(Number(placeConfig.lng)) ? Number(placeConfig.lng) : 0;
    const startZoom = Number.isFinite(Number(placeConfig.zoom)) ? Number(placeConfig.zoom) : 11;
    const geocodingCountryCode = (placeConfig.geocoding_country_code || 'gb').toString();
    const geocodingCountryName = (placeConfig.geocoding_country_name || 'United Kingdom').toString();

    let minZoom = 11;
    let maxZoom = 19;
    var osmLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        minZoom,
        maxZoom,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });

    var Thunderforest_SpinalMap = L.tileLayer('https://{s}.tile.thunderforest.com/spinal-map/{z}/{x}/{y}.png?apikey=35178872612640c0abf67975149afa20', {
        attribution: '&copy; <a href="https://www.thunderforest.com/">Thunderforest</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        apikey: '35178872612640c0abf67975149afa20',
        minZoom,
        maxZoom
    });
    map = L.map('map', { fullscreenControl: true, layers: [osmLayer] }).setView([startLat, startLng], startZoom);

    map.createPane('polygonsPane');
    map.getPane('polygonsPane').style.zIndex = 350;

    var baseMaps = {
        "OpenStreetMap": osmLayer,
        "Spinal Map": Thunderforest_SpinalMap,
    }
    var geocoderOptions = {
        geocoder: new L.Control.Geocoder.nominatim({
            geocodingQueryParams: {
                "countrycodes": geocodingCountryCode
            }
        }),
        placeholder: `Search for place in ${geocodingCountryName}`
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

    function getPopupText(feature, popupLatLng = null, unitSystem = 'metric') {
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
        let placename = feature.properties["streetname"] ?? '(no name)';
        let etymologyText = feature.properties["name:etymology"];
        let popupText = `<h1 class="popupplacename" title="${placename}">${placename}</h1>`;
        let wikidataset = feature.properties["wikidataset"];
        let wikidataurlprefix = 'https://www.wikidata.org/wiki/';
        let wikipediaenurlprefix = 'https://en.wikipedia.org/w/index.php?title=';
        if (wikidataset) {
            let sections = [];
            const distanceAwayText = getWikidataDistanceAwayText(feature.properties["wikidata_location"], popupLatLng, unitSystem);
            let distanceTextAdded = false;
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
            for (const item of wikidataset) {
                var sectiontext = '';
                let wikidataId = item["itemid"];
                let wikidatalabel = item["label"];
                let wikibirth = item["dateofbirth"];
                let wikideath = item["dateofdeath"];
                let wikipediatitleen = item["wikipediatitleen"];
                let wikidatadescription = capitalizeFirstLetter(item["description"] ?? '');
                sectiontext += `<div class="popupitemname">${wikidatalabel || ''}</div>`;
                if (wikibirth || wikideath) {
                    let birthdeathtext = '(';
                    if (wikibirth) {
                        if (typeof wikibirth === 'string' && wikibirth.trim().endsWith('BC')) {
                            // Handle BC date string, e.g. "0600-01-01 BC"
                            let yearMatch = wikibirth.match(/^0*(\d{1,4})/); // Remove leading zeroes
                            birthdeathtext += (yearMatch ? yearMatch[1] : wikibirth) + ' BC';
                        } else {
                            birthdeathtext += new Date(wikibirth).toLocaleDateString('da-DK', dateoptions);
                        }
                    }
                    birthdeathtext += ' - ';
                    if (wikideath) {
                        if (typeof wikideath === 'string' && wikideath.trim().endsWith('BC')) {
                            let yearMatch = wikideath.match(/^0*(\d{1,4})/); // Remove leading zeroes
                            birthdeathtext += (yearMatch ? yearMatch[1] : wikideath) + ' BC';
                        } else {
                            birthdeathtext += new Date(wikideath).toLocaleDateString('da-DK', dateoptions);
                        }
                    }
                    birthdeathtext += ')';
                    sectiontext += `<div class="popupbirthdeath">${birthdeathtext}</div>`;
                }
                let descriptionParagraphParts = [];
                if (wikidatadescription) {
                    descriptionParagraphParts.push(wikidatadescription);
                }
                if (!distanceTextAdded && distanceAwayText) {
                    const locationLatLng = parseWikidataLocationToLatLng(feature.properties["wikidata_location"]);
                    if (locationLatLng && popupLatLng) {
                        _wikidataSourceData[wikidataId] = { label: wikidatalabel, lat: locationLatLng.lat, lng: locationLatLng.lng };
                        const fromLat = popupLatLng.lat;
                        const fromLng = popupLatLng.lng;
                        const fromWayId = feature.properties["id"];
                        descriptionParagraphParts.push(`(<a href="#" onclick="openWikidataSourcePopup('${wikidataId}', ${fromLat}, ${fromLng}, ${fromWayId}); return false;">${distanceAwayText}</a>)`);
                    } else {
                        descriptionParagraphParts.push(`(${distanceAwayText})`);
                    }
                    distanceTextAdded = true;
                }
                if (descriptionParagraphParts.length > 0) {
                    sectiontext += `<p>${descriptionParagraphParts.join('<br>')}</p>`;
                }
                // Wikidata and Wikipedia links
                sectiontext += `<p>`;
                if (wikipediatitleen) {
                    sectiontext += `<a href="${wikipediaenurlprefix}${encodeURI(wikipediatitleen)}">Wikipedia article</a> - `;
                }
                sectiontext += `<a href="${wikidataurlprefix}${wikidataId}" class="wikidataname" data-wikidata="${wikidataId}">Wikidata item</a>`;
                sectiontext += `</p>`;
                sectiontext += `<p class="localsearch"><a href="#${wikidataId}" onclick="doSearch('${wikidataId}'); return false;">Find all places named after this topic</a></p>`;
                sections.push(sectiontext);
            }
            popupText += sections.map(section => `<div>${section}</div>`).join('\n');
            if (!distanceTextAdded && distanceAwayText) {
                popupText += `<p>(${distanceAwayText})</p>`;
            }
        } else if (etymologyText) {
            popupText += `<div class="popupitemname">${etymologyText}</div>`;
        }
        let osmurl = (feature.properties["sampleobject_id"] > 0 ? osmURLs[feature.properties["geomtype"]] : osmURLs.relation) + Math.abs(feature.properties["sampleobject_id"]);
        let mapcompleteurl = (feature.properties["sampleobject_id"] > 0 ? mapCompleteEtymologyURLs[feature.properties["geomtype"]] : mapCompleteEtymologyURLs.relation) + Math.abs(feature.properties["sampleobject_id"]);
        popupText += `<div><a href="${osmurl}" title="See the place on OpenStreetMap.org"><img src="media/openstreetmap_30.png" width="30" height="30" alt="OpenStreetMap Logo"></a> <a href="${mapcompleteurl}" title="Edit the place on MapComplete"><img src="media/mapcomplete.svg" width="30" height="30" alt="MapComplete Logo"></a></div>`;
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
        let iter = flatgeobuf.deserialize('/data/names.fgb', mapBoundingBox(), false, true);
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
                    const popupLatLng = e.popup && e.popup.getLatLng ? e.popup.getLatLng() : null;
                    e.popup.setContent(getPopupText(feature, popupLatLng));
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
            .bindPopup(`You are within ${Math.round(radius).toLocaleString()} meters of this point`).openPopup();
        const locationCircle = L.circle(data.latlng, radius).addTo(map);
        $.getJSON("lookup.php", { coordinates })
            .fail((jqxhr, textStatus, error) => updateResultTableError(error))
            .done((data) => updateResultTable(data));
    });
    map.on('locationerror', (e) => {
        $("#result").html('Cannot find your position: ' + e.message);
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

function openWikidataSourcePopup(wikidataId, fromLat, fromLng, fromWayId) {
    const data = _wikidataSourceData[wikidataId];
    if (!data) return;
    const locLatLng = L.latLng(data.lat, data.lng);
    map.panTo(locLatLng);
    const popup = L.popup()
        .setLatLng(locLatLng)
        .setContent(`<strong>${data.label || wikidataId}</strong><br>Loading places...`)
        .openOn(map);
    fetch(`lookup.php?search=${encodeURIComponent(wikidataId)}`)
        .then(r => r.json())
        .then(places => {
            let html = `<strong>${data.label || wikidataId}</strong> <a href="https://www.wikidata.org/wiki/${wikidataId}" class="wikidataname" data-wikidata="${wikidataId}">[Wikidata]</a>`;
            if (places && places.length > 0) {
                html += '<ul style="padding-left:1em;margin:.3em 0;list-style:none">';
                for (const row of places) {
                    html += `<li><span onclick="panToWayId(${row.centroid_onfeature_latitude}, ${row.centroid_onfeature_longitude}, ${row.id});" style="cursor:pointer">📍</span> ${row.streetname ?? ''}${row.areaname ? ` (${row.areaname})` : ''}</li>`;
                }
                html += '</ul>';
            } else {
                html += '<p>No places found.</p>';
            }
            html += `<p><a href="#" onclick="panToWayId(${fromLat}, ${fromLng}, ${fromWayId}); return false;">← Back</a></p>`;
            popup.setContent(html);
        })
        .catch(() => {
            popup.setContent(`<strong>${data.label || wikidataId}</strong><br>Error loading places.`);
        });
}

function getWikidataDistanceAwayText(wikidataLocation, fromLatLng, unitSystem = 'metric') {
    if (!wikidataLocation || !fromLatLng) {
        return null;
    }
    const locationLatLng = parseWikidataLocationToLatLng(wikidataLocation);
    if (!locationLatLng) {
        return null;
    }
    const distanceInMeters = fromLatLng.distanceTo(locationLatLng);
    return formatDistanceAway(distanceInMeters, unitSystem);
}

function parseWikidataLocationToLatLng(wikidataLocation) {
    if (!wikidataLocation) {
        return null;
    }

    // GeoJSON-like point object: { type: "Point", coordinates: [lon, lat] }
    if (wikidataLocation.type === 'Point' && Array.isArray(wikidataLocation.coordinates) && wikidataLocation.coordinates.length >= 2) {
        return L.latLng(Number(wikidataLocation.coordinates[1]), Number(wikidataLocation.coordinates[0]));
    }

    // Some exporters may return a WKT string such as "POINT(lon lat)"
    if (typeof wikidataLocation === 'string') {
        try {
            const parsedLocation = JSON.parse(wikidataLocation);
            if (parsedLocation && parsedLocation.type === 'Point' && Array.isArray(parsedLocation.coordinates) && parsedLocation.coordinates.length >= 2) {
                return L.latLng(Number(parsedLocation.coordinates[1]), Number(parsedLocation.coordinates[0]));
            }
        } catch (e) {
            // Not JSON, continue and try WKT parsing.
        }

        const pointMatch = wikidataLocation.match(/POINT\s*\(\s*([-\d.]+)\s+([-\d.]+)\s*\)/i);
        if (pointMatch) {
            return L.latLng(Number(pointMatch[2]), Number(pointMatch[1]));
        }
    }

    return null;
}

function formatDistanceAway(distanceInMeters, unitSystem = 'metric') {
    if (!Number.isFinite(distanceInMeters)) {
        return null;
    }

    if (unitSystem === 'imperial') {
        const feetPerMeter = 3.280839895;
        const milesPerMeter = 0.000621371192;
        const distanceInFeet = distanceInMeters * feetPerMeter;
        const distanceInMiles = distanceInMeters * milesPerMeter;

        if (distanceInMiles < 1) {
            const roundedFeet = Math.round(distanceInFeet);
            return `${roundedFeet.toLocaleString()} ${roundedFeet === 1 ? 'foot' : 'feet'} away`;
        }

        if (distanceInMiles > 100) {
            const roundedMiles = Math.round(distanceInMiles);
            return `${roundedMiles.toLocaleString()} ${roundedMiles === 1 ? 'mile' : 'miles'} away`;
        }

        const roundedMiles = Math.round(distanceInMiles * 10) / 10;
        return `${roundedMiles.toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 })} miles away`;
    }

    if (distanceInMeters < 1000) {
        const roundedMeters = Math.round(distanceInMeters);
        return `${roundedMeters.toLocaleString()} ${roundedMeters === 1 ? 'meter' : 'meters'} away`;
    }

    const distanceInKilometers = distanceInMeters / 1000;
    if (distanceInKilometers > 100) {
        const roundedKilometers = Math.round(distanceInKilometers);
        return `${roundedKilometers.toLocaleString()} ${roundedKilometers === 1 ? 'kilometer' : 'kilometers'} away`;
    }

    const roundedKilometers = Math.round(distanceInKilometers * 10) / 10;
    return `${roundedKilometers.toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 })} kilometers away`;
}
