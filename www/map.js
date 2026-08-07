let map;
let highlightLocationId = false;
const _wikidataSourceData = {};
const mapConfig = window.appConfig || {};
const mapI18n = window.appI18n;

function mapTranslate(key, params) {
    return mapI18n.t(key, params);
}

function mapTranslatePlural(key, count, params) {
    return mapI18n.tp(key, count, params);
}

document.addEventListener("DOMContentLoaded", async () => {
    if (mapI18n && typeof mapI18n.ensureTranslationsLoaded === 'function') {
        try {
            await mapI18n.ensureTranslationsLoaded();
        } catch (error) {
            console.warn('Could not load translations before map setup', error);
        }
    }

    const placeConfig = mapConfig.place || {};
    const startLat = Number.isFinite(Number(placeConfig.lat)) ? Number(placeConfig.lat) : 51.5;
    const startLng = Number.isFinite(Number(placeConfig.lng)) ? Number(placeConfig.lng) : 0;
    const startZoom = Number.isFinite(Number(placeConfig.zoom)) ? Number(placeConfig.zoom) : 11;
    const geocodingCountryCode = (placeConfig.geocoding_country_code || 'gb').toString();
    const geocodingCountryName = (placeConfig.geocoding_country_name || 'United Kingdom').toString();
    const minZoom = Number.isFinite(Number(placeConfig.minZoom)) ? Number(placeConfig.minZoom) : 11;
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

    function getBaseMaps() {
        return {
            [mapTranslate('map.baseLayers.openStreetMap')]: osmLayer,
            [mapTranslate('map.baseLayers.spinalMap')]: Thunderforest_SpinalMap,
        };
    }

    let layerControl = null;
    let geocoderControl = null;

    function createGeocoderControl() {
        return L.Control.geocoder({
            geocoder: new L.Control.Geocoder.nominatim({
                geocodingQueryParams: {
                    "countrycodes": geocodingCountryCode
                }
            }),
            placeholder: mapTranslate('map.searchPlaceInCountry', { country: geocodingCountryName })
        });
    }

    function rebuildTopRightControls() {
        if (layerControl) {
            map.removeControl(layerControl);
        }
        if (geocoderControl) {
            map.removeControl(geocoderControl);
        }

        // Keep a stable control order across startup and language changes.
        layerControl = L.control.layers(getBaseMaps()).addTo(map);
        geocoderControl = createGeocoderControl().addTo(map);
    }

    rebuildTopRightControls();
    L.control.scale().addTo(map);

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
        let placename = feature.properties["streetname"] ?? mapTranslate('map.noName');
        let etymologyText = feature.properties["name:etymology"];
        let popupText = `<h1 class="popupplacename" title="${placename}">${placename}</h1>`;
        let wikidataset = feature.properties["wikidataset"];
        let wikidataurlprefix = 'https://www.wikidata.org/wiki/';
        let wikipediaenurlprefix = 'https://en.wikipedia.org/w/index.php?title=';
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
            for (const item of wikidataset) {
                var sectiontext = '';
                let wikidataId = item["itemid"];
                let wikidatalabel = item["label"];
                let wikibirth = item["dateofbirth"];
                let wikibirthprecision = item["dateofbirth_precision"];
                let wikideath = item["dateofdeath"];
                let wikideathprecision = item["dateofdeath_precision"];
                let wikipediatitleen = item["wikipediatitleen"];
                let wikidatadescription = capitalizeFirstLetter(item["description"] ?? '');
                sectiontext += `<div class="popupitemname">${wikidatalabel || ''}</div>`;
                if (wikibirth || wikideath) {
                    let birthdeathtext = '(';
                    if (wikibirth) {
                        birthdeathtext += formatWikidataDateByPrecision(wikibirth, wikibirthprecision, dateoptions);
                    }
                    birthdeathtext += ' - ';
                    if (wikideath) {
                        birthdeathtext += formatWikidataDateByPrecision(wikideath, wikideathprecision, dateoptions);
                    }
                    birthdeathtext += ')';
                    sectiontext += `<div class="popupbirthdeath">${birthdeathtext}</div>`;
                }
                let descriptionParagraphParts = [];
                if (wikidatadescription) {
                    descriptionParagraphParts.push(wikidatadescription);
                }
                const itemDistanceAwayText = getWikidataDistanceAwayText(item["wikidata_location"], popupLatLng, unitSystem);
                if (itemDistanceAwayText) {
                    const locationLatLng = parseWikidataLocationToLatLng(item["wikidata_location"]);
                    if (locationLatLng && popupLatLng) {
                        _wikidataSourceData[wikidataId] = { label: wikidatalabel, lat: locationLatLng.lat, lng: locationLatLng.lng };
                        const fromLat = popupLatLng.lat;
                        const fromLng = popupLatLng.lng;
                        const fromWayId = feature.properties["id"];
                        descriptionParagraphParts.push(`(<a href="#" onclick="openWikidataSourcePopup('${wikidataId}', ${fromLat}, ${fromLng}, ${fromWayId}); return false;">${itemDistanceAwayText}</a>)`);
                    } else {
                        descriptionParagraphParts.push(`(${itemDistanceAwayText})`);
                    }
                }
                if (descriptionParagraphParts.length > 0) {
                    sectiontext += `<p>${descriptionParagraphParts.join('<br>')}</p>`;
                }
                // Wikidata and Wikipedia links
                sectiontext += `<p>`;
                if (wikipediatitleen) {
                    sectiontext += `<a href="${wikipediaenurlprefix}${encodeURI(wikipediatitleen)}">${mapTranslate('common.wikipediaArticle')}</a> - `;
                }
                sectiontext += `<a href="${wikidataurlprefix}${wikidataId}" class="wikidataname" data-wikidata="${wikidataId}">${mapTranslate('common.wikidataItem')}</a>`;
                sectiontext += `</p>`;
                sectiontext += `<p class="localsearch"><a href="#${wikidataId}" onclick="doSearch('${wikidataId}'); return false;">${mapTranslate('common.findPlacesForTopic')}</a></p>`;
                sections.push(sectiontext);
            }
            popupText += sections.map(section => `<div>${section}</div>`).join('\n');
        } else if (etymologyText) {
            popupText += `<div class="popupitemname">${etymologyText}</div>`;
        }
        let osmurl = (feature.properties["sampleobject_id"] > 0 ? osmURLs[feature.properties["geomtype"]] : osmURLs.relation) + Math.abs(feature.properties["sampleobject_id"]);
        let mapcompleteurl = (feature.properties["sampleobject_id"] > 0 ? mapCompleteEtymologyURLs[feature.properties["geomtype"]] : mapCompleteEtymologyURLs.relation) + Math.abs(feature.properties["sampleobject_id"]);
        popupText += `<div><a href="${osmurl}" title="${mapTranslate('common.openStreetMapTitle')}"><img src="media/openstreetmap_30.png" width="30" height="30" alt="${mapTranslate('common.openStreetMapLogoAlt')}"></a> <a href="${mapcompleteurl}" title="${mapTranslate('common.mapCompleteTitle')}"><img src="media/mapcomplete.svg" width="30" height="30" alt="${mapTranslate('common.mapCompleteLogoAlt')}"></a></div>`;
        return popupText;
    }

    function getLineColorFromGender(feature) {
        let lineColor = '#00cc0099';
        if (feature.properties['gender'] == 'male') {
            lineColor = '#2244ff99';
        } else if (feature.properties['gender'] == 'female') {
            lineColor = '#ff000099';
        } else if (feature.properties['gender'] == 'mixed') {
            lineColor = '#ff00ff99';
        }
        return lineColor;
    }

    // track the previous results so we can remove them when adding new results
    // :TODO: Show spinner when loading
    let previousResults = L.layerGroup().addTo(map);
    async function updateMapData() {
        // :TODO: Only remove old results when new are loaded. This might cause issues if more are loaded simultaneously
        // remove the old results
        let previousHighlightLocationId = highlightLocationId;
        previousResults.remove();
        const nextResults = L.layerGroup().addTo(map);
        previousResults = nextResults;
        highlightLocationId = previousHighlightLocationId;

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
            let highlightFeature = (feature.properties["id"] == highlightLocationId);
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
                pane: (feature.geometry.type === 'Polygon' || feature.geometry.type === 'MultiPolygon') ? 'polygonsPane' : 'overlayPane'
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
                    highlightLocationId = feature.properties["id"];
                    const popupLatLng = e.popup && e.popup.getLatLng ? e.popup.getLatLng() : null;
                    e.popup.setContent(getPopupText(feature, popupLatLng));
                    e.target.setStyle({ color: highlightColor });
                },
                'popupclose': function (e) {
                    highlightLocationId = false;
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
        $("#result").html(mapTranslate('common.loadingNearbyPlaces'));
        let coordinates = `${data.latlng.lat},${data.latlng.lng}`;
        map.panTo(data.latlng);
        const radius = data.accuracy / 2;
        const locationMarker = L.marker(data.latlng).addTo(map)
            .bindPopup(mapTranslatePlural('common.youAreWithinMeters', Math.round(radius), { count: mapI18n.formatNumber(Math.round(radius)) })).openPopup();
        const locationCircle = L.circle(data.latlng, radius).addTo(map);
        $.getJSON("lookup.php", { coordinates })
            .fail((jqxhr, textStatus, error) => updateResultTableError(error))
            .done((data) => updateResultTable(data));
    });
    map.on('locationerror', (e) => {
        $("#result").html(mapTranslate('common.cannotFindPosition', { message: e.message }));
        console.log(e);
    });

    document.addEventListener('app:languagechange', () => {
        rebuildTopRightControls();
        map.closePopup();
    });
});

function updateMapLink() {
    let coordLink = '' + map.getZoom() + '/' + parseFloat(map.getCenter().lat).toFixed(5) + '/' + parseFloat(map.getCenter().lng).toFixed(5);
    $("#copylinktomap").attr('href', '#map=' + coordLink);
}

function panToLocationId(latitude, longitude, locationId) {
    highlightLocationId = locationId;
    map.panTo([latitude, longitude]);
}

function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

function openWikidataSourcePopup(wikidataId, fromLat, fromLng, fromLocationId) {
    const data = _wikidataSourceData[wikidataId];
    if (!data) return;
    const locLatLng = L.latLng(data.lat, data.lng);
    map.panTo(locLatLng);
    const popup = L.popup()
        .setLatLng(locLatLng)
        .setContent(`<strong>${data.label || wikidataId}</strong><br>${mapTranslate('common.loadingPlaces')}`)
        .openOn(map);
    fetch(`lookup.php?search=${encodeURIComponent(wikidataId)}`)
        .then(r => r.json())
        .then(places => {
            let html = `<strong>${data.label || wikidataId}</strong> <a href="https://www.wikidata.org/wiki/${wikidataId}" class="wikidataname" data-wikidata="${wikidataId}">${mapTranslate('common.wikidataBadge')}</a>`;
            if (places && places.length > 0) {
                html += '<ul style="padding-left:1em; margin:.3em 0; list-style:none; overflow: auto; max-height: 300px; scrollbar-width: thin; scrollbar-gutter: stable; white-space: nowrap;">';
                for (const row of places) {
                    html += `<li style="overflow: hidden; text-overflow: ellipsis;"><span onclick="panToLocationId(${row.centroid_onfeature_latitude}, ${row.centroid_onfeature_longitude}, ${row.id});" style="cursor:pointer">📍</span> ${row.streetname ?? ''}${row.areaname ? ` (${row.areaname})` : ''}</li>`;
                }
                html += '</ul>';
            } else {
                html += `<p>${mapTranslate('common.noPlacesFound')}</p>`;
            }
            html += `<p><a href="#" onclick="panToLocationId(${fromLat}, ${fromLng}, ${fromLocationId}); return false;">← ${mapTranslate('common.back')}</a></p>`;
            popup.setContent(html);
        })
        .catch(() => {
            popup.setContent(`<strong>${data.label || wikidataId}</strong><br>${mapTranslate('common.errorLoadingPlaces')}`);
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
            return mapTranslatePlural('common.distance.foot', roundedFeet, { count: mapI18n.formatNumber(roundedFeet) });
        }

        if (distanceInMiles > 100) {
            const roundedMiles = Math.round(distanceInMiles);
            return mapTranslatePlural('common.distance.mile', roundedMiles, { count: mapI18n.formatNumber(roundedMiles) });
        }

        const roundedMiles = Math.round(distanceInMiles * 10) / 10;
        return mapTranslatePlural('common.distance.mile', roundedMiles, { count: mapI18n.formatNumber(roundedMiles, undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) });
    }

    if (distanceInMeters < 1000) {
        const roundedMeters = Math.round(distanceInMeters);
        return mapTranslatePlural('common.distance.meter', roundedMeters, { count: mapI18n.formatNumber(roundedMeters) });
    }

    const distanceInKilometers = distanceInMeters / 1000;
    if (distanceInKilometers > 100) {
        const roundedKilometers = Math.round(distanceInKilometers);
        return mapTranslatePlural('common.distance.kilometer', roundedKilometers, { count: mapI18n.formatNumber(roundedKilometers) });
    }

    const roundedKilometers = Math.round(distanceInKilometers * 10) / 10;
    return mapTranslatePlural('common.distance.kilometer', roundedKilometers, { count: mapI18n.formatNumber(roundedKilometers, undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) });
}

function formatWikidataDateByPrecision(rawDate, precisionRaw, yearOnlyOptions = { year: 'numeric' }) {
    if (!rawDate) {
        return '';
    }

    const parsed = parseWikidataDateValue(rawDate);
    const precision = Number.isFinite(Number(precisionRaw)) ? Number(precisionRaw) : 9;
    const monthYearOptions = { year: 'numeric', month: 'short' };
    const fullDateOptions = { year: 'numeric', month: 'short', day: 'numeric' };

    if (precision >= 11) {
        return formatWikidataDateWithEra(parsed, fullDateOptions);
    }

    if (precision === 10) {
        return formatWikidataDateWithEra(parsed, monthYearOptions);
    }

    if (precision === 9) {
        return formatWikidataDateWithEra(parsed, yearOnlyOptions);
    }

    const yearNumber = parsed.year;
    if (!Number.isFinite(yearNumber)) {
        return String(rawDate);
    }

    if (precision === 8) {
        const decadeStart = Math.floor(yearNumber / 10) * 10;
        const decadeLabel = mapTranslate('common.timePrecision.decade', {
            startYear: mapI18n.formatNumber(decadeStart)
        });
        return parsed.isBC ? `${decadeLabel} ${mapTranslate('common.bc')}` : decadeLabel;
    }

    if (precision === 7) {
        const centuryNumber = Math.ceil(yearNumber / 100);
        const centuryLabel = mapTranslate('common.timePrecision.century', {
            count: mapI18n.formatNumber(centuryNumber)
        });
        return parsed.isBC ? `${centuryLabel} ${mapTranslate('common.bc')}` : centuryLabel;
    }

    const millenniumNumber = Math.ceil(yearNumber / 1000);
    const millenniumLabel = mapTranslate('common.timePrecision.millennium', {
        count: mapI18n.formatNumber(millenniumNumber)
    });
    return parsed.isBC ? `${millenniumLabel} ${mapTranslate('common.bc')}` : millenniumLabel;
}

function formatWikidataDateWithEra(parsedDate, options) {
    if (parsedDate.date && !parsedDate.isBC) {
        return mapI18n.formatDate(parsedDate.date, options);
    }

    const year = Number.isFinite(parsedDate.year) ? mapI18n.formatNumber(parsedDate.year) : String(parsedDate.raw || '');
    if (options && options.day && Number.isFinite(parsedDate.day) && Number.isFinite(parsedDate.month)) {
        const fullDateText = `${parsedDate.day}-${parsedDate.month}-${year}`;
        return parsedDate.isBC ? `${fullDateText} ${mapTranslate('common.bc')}` : fullDateText;
    }

    if (options && options.month && Number.isFinite(parsedDate.month)) {
        const monthYearText = `${parsedDate.month}-${year}`;
        return parsedDate.isBC ? `${monthYearText} ${mapTranslate('common.bc')}` : monthYearText;
    }

    return parsedDate.isBC ? `${year} ${mapTranslate('common.bc')}` : year;
}

function parseWikidataDateValue(rawDate) {
    const parsed = {
        raw: rawDate,
        isBC: false,
        year: NaN,
        month: NaN,
        day: NaN,
        date: null,
    };

    if (rawDate instanceof Date && !Number.isNaN(rawDate.getTime())) {
        parsed.year = rawDate.getUTCFullYear();
        parsed.month = rawDate.getUTCMonth() + 1;
        parsed.day = rawDate.getUTCDate();
        parsed.date = rawDate;
        return parsed;
    }

    if (typeof rawDate !== 'string') {
        return parsed;
    }

    const trimmed = rawDate.trim();
    const isBC = trimmed.toUpperCase().endsWith('BC');
    parsed.isBC = isBC;
    const cleaned = isBC ? trimmed.replace(/\s*BC\s*$/i, '') : trimmed;
    const match = cleaned.match(/^[-+]?0*(\d{1,6})(?:-(\d{2})-(\d{2}))?$/);
    if (match) {
        parsed.year = Number(match[1]);
        if (match[2]) {
            parsed.month = Number(match[2]);
        }
        if (match[3]) {
            parsed.day = Number(match[3]);
        }
        if (!isBC) {
            const parsedDate = new Date(cleaned);
            if (!Number.isNaN(parsedDate.getTime())) {
                parsed.date = parsedDate;
            }
        }
        return parsed;
    }

    const fallbackDate = new Date(trimmed);
    if (!Number.isNaN(fallbackDate.getTime())) {
        parsed.year = fallbackDate.getUTCFullYear();
        parsed.month = fallbackDate.getUTCMonth() + 1;
        parsed.day = fallbackDate.getUTCDate();
        parsed.date = fallbackDate;
    }
    return parsed;
}
