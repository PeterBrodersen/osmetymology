let requestCount = 0;
let lastinputname = '';
let lastinputlabel = '';
let currentCount = 0;
let lastResultState = null;
const helperConfig = window.appConfig || {};
const i18n = window.appI18n;

function createMapViewHash(hashName, mapInstance) {
  const center = mapInstance.getCenter();
  return `#${hashName}=${mapInstance.getZoom()}/${center.lat.toFixed(5)}/${center.lng.toFixed(5)}`;
}

function translate(key, params) {
  return i18n.t(key, params);
}

function translatePlural(key, count, params) {
  return i18n.tp(key, count, params);
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function getWikidataPresentation(item) {
  const configuredLanguages = Array.isArray(helperConfig.wikidataLanguages) ? helperConfig.wikidataLanguages : [];
  const currentLocale = i18n && typeof i18n.getLocale === 'function' ? i18n.getLocale() : '';
  const languageCandidates = [...new Set([currentLocale, currentLocale.split('-')[0], ...configuredLanguages].filter(Boolean))];
  let label = null;
  let description = null;
  let wikipediaUrl = null;

  for (const language of languageCandidates) {
    const labelValue = item.labels?.[language]?.value ?? item.labels?.[language];
    const descriptionValue = item.descriptions?.[language]?.value ?? item.descriptions?.[language];
    const sitelink = item.sitelinks?.[`${language}wiki`];
    if (!label && labelValue) {
      label = labelValue;
    }
    if (!description && descriptionValue) {
      description = descriptionValue;
    }
    if (!wikipediaUrl && sitelink?.title) {
      if (language !== 'mul') {
        wikipediaUrl = `https://${language}.wikipedia.org/wiki/${encodeURIComponent(sitelink.title.replaceAll(' ', '_'))}`;
      }
    }
    if (label && description && wikipediaUrl) {
      break;
    }
  }

  return {
    label: label ?? item.label ?? '',
    description: description ?? item.description ?? '',
    wikipediaUrl: wikipediaUrl ?? item.wikipedia ?? null,
  };
}

$(function () {

  const externalUrls = helperConfig.external_urls || {};
  const avoidGenderUrl = (externalUrls.avoid_gender || '').trim();
  const avoidGenderExampleUrl = (externalUrls.avoid_gender_example || '').trim();
  if (avoidGenderUrl || avoidGenderExampleUrl) {
    $("#osrm_gender").css('display', 'list-item');
    if (avoidGenderUrl) {
      $("#avoid_gender").attr('href', avoidGenderUrl);
    }
    if (avoidGenderExampleUrl) {
      $("#avoid_gender_example").attr('href', avoidGenderExampleUrl);
    }
  }

  let namefindTimeout = null;
  let namefindController = null;
  let latestRequestId = 0;

  $("#namefind").on("keyup", () => {
    let inputname = $("#namefind").val();
    if (inputname == lastinputname) { // don't request for random key presses such as shift
      return;
    }
    lastinputname = inputname;

    if (!(/^(Q\d+|.{3,})$/).test(inputname)) {
      $("#result").html('');
      return;
    }

    // Debounce: clear previous timeout
    if (namefindTimeout) clearTimeout(namefindTimeout);

    namefindTimeout = setTimeout(() => {
      // Abort previous request if still running
      if (namefindController) {
        namefindController.abort();
      }
      namefindController = new AbortController();
      const requestId = ++latestRequestId;

      $("#copylink a").show().attr('href', '#' + inputname);
      $(".resulttable").fadeTo("slow", 0.5);

      fetch(`lookup.php?search=${encodeURIComponent(inputname)}`, { signal: namefindController.signal })
        .then(response => {
          if (!response.ok) throw new Error(response.statusText);
          return response.json();
        })
        .then(data => {
          // Only process if this is the latest request
          if (requestId === latestRequestId) {
            updateResultTable(data);
          }
        })
        .catch(error => {
          if (error.name === 'AbortError') return;
          updateResultTableError(error);
        });
    }, 300);
  });

  // $("#itemfind").on("keyup", () => {
  //   let inputlabel = $("#itemfind").val();
  //   if (inputlabel == lastinputlabel) { // don't request for random key presses such as shift
  //     return;
  //   }
  //   lastinputlabel = inputlabel;

  //   if (!(/^.{3,}$/).test(inputlabel)) {
  //     $("#result").html('');
  //     return;
  //   }
  //   $(".resulttable").fadeTo("slow", 0.5);
  //   $.getJSON("lookup.php", { itemname: inputlabel })
  //     .fail((jqxhr, textStatus, error) => updateResultTableError(error))
  //   // .done((data) => updateResultTable(data));
  // });

  $("#itemfind").autocomplete({
    // source: 'auto.php',
    source: 'lookup.php',
    minLength: 2,
    delay: 100,
    select: function (event, ui) {
      doSearch(ui.item.itemid);
    }
  })
    .autocomplete("instance")._renderItem = function (ul, item) {
      var showname = item.label;
      var llabel = item.label.toLowerCase();
      var lalias = item.alias.toLowerCase();
      var lterm = this.term.toLowerCase().trim();
      if (!llabel.startsWith(lalias) && !llabel.startsWith(lterm)) {
        // if (!item.name.toLowerCase().startsWith(this.term.toLowerCase().trim())) {
        showname += ' (<em>' + item.alias + '</em>)';
      }

      let optionHTML = `<div class="autoitemblock" title="${item.label}">`;
      optionHTML += `<span class="autoitemname">${showname}</span>`;
      optionHTML += `<div class="autoitemdetails">`;
      if (item.description) {
        optionHTML += `<span class="autoitemdescription">${item.description}</span><br>`;
      }
      optionHTML += `<span class="autoitemcount">${translatePlural('common.placeCount', item.placecount, { count: i18n.formatNumber(item.placecount) })} </span>`;
      optionHTML += `</div></div>`;
      // .autocomplete("instance")._renderItem = function (ul, item) {
      //   let optionHTML = `<div class="autoitemblock">`;
      //   optionHTML += `<span class="autoitemname">${item.name}</span>`;
      //   optionHTML += `<div style="display: flex; align-items: center;">`;
      //   // Cirklen er nogle gange en ellipse!
      //   optionHTML += `<div style="border-radius: 50%; background: #9ee; text-align: center; box-sizing: border-box; width: 25px; height: 25px; padding: 5px; text-align: center; justify-content: center; align-items: center; line-height: 17px; font-family: sans-serif;"><span title="${item.placecount} ${item.placecount == 1 ? "sted" : "steder"}">${item.placecount}</div><div style="flex-grow: 1; padding-left: 20px;">${item.description ?? ''}</div>`;
      //   optionHTML += `</div></div>`;
      //   // optionHTML += `<div class="autoitemdetails">`;
      //   // if (item.description) {
      //   //   optionHTML += `<span class="autoitemdescription">${item.description}</span><br>`;
      //   // }
      //   // optionHTML += `<span class="autoitemcount">${item.placecount} ${item.placecount == 1 ? "sted" : "steder"} </span>`;
      //   // optionHTML += `</div></div>`;
      return $("<li>")
        .append(optionHTML)
        .appendTo(ul);
    }

  // copy function
  $("#copylink a").on("click", (event) => {
    event.preventDefault();

    let url = $("#copylink a").prop('href');
    navigator.clipboard.writeText(url);
    window.location.hash = new URL(url, window.location.href).hash;

    $("#copylink a").animate({ backgroundColor: 'yellow' }, 300).animate({ backgroundColor: 'white' }, 300);
  });

  $("#getposition").on("click", (event) => { // :TODO: Indicate a location search is going on
    event.preventDefault();
    $("#result").html(translate('common.acquiringPosition'));
    // map.locate({ enableHighAccuracy: true });
    map.locate();
  });

  $("#copylinktomap").on("click", () => {
    let url = $("#copylinktomap").prop('href');
    navigator.clipboard.writeText(url);
    $(this).css('background-color', 'yellow');

    $("#copylinktomap").animate({ backgroundColor: 'yellow' }, 300).animate({ backgroundColor: 'white' }, 300);
  });

  $("#showplacesinmapview").on("click", (event) => {
    const placesHash = createMapViewHash('places', map);
    $(event.currentTarget).attr('href', placesHash);
    event.preventDefault();

    if (window.location.hash === placesHash) {
      handleHashChange();
    } else {
      window.location.hash = placesHash;
    }

  });

  function navigateToMapHash(hashMatch, afterNavigation) {
    const setMapViewFromHash = () => {
      if (typeof map === 'undefined' || !map || typeof map.setView !== 'function') {
        return false;
      }

      map.setView(
        L.latLng(hashMatch[2], hashMatch[3]),
        hashMatch[1],
        { animate: false }
      );

      if (afterNavigation) {
        afterNavigation();
      }

      return true;
    };

    if (!setMapViewFromHash()) {
      document.addEventListener('app:mapready', setMapViewFromHash, { once: true });
    }
  }

  function handleHashChange() {
    if (window.location.hash.length <= 1) {
      return;
    }

    let hash = decodeURIComponent(window.location.hash.substring(1));
    const locationMatch = hash.match(/^location=(\d+)$/);
    const mapMatch = hash.match(/^map=(\d+)\/(-?\d+(?:\.\d+)?)\/(-?\d+(?:\.\d+)?)$/);
    const placesMatch = hash.match(/^places=(\d+)\/(-?\d+(?:\.\d+)?)\/(-?\d+(?:\.\d+)?)$/);
    if (locationMatch) {
      panToLocationHash(locationMatch[1]);
    } else if (mapMatch) {
      navigateToMapHash(mapMatch);
    } else if (placesMatch) {
      navigateToMapHash(placesMatch, () => {
        const bounds = map.getBounds();
        const bbox = `${bounds.getSouth()},${bounds.getWest()},${bounds.getNorth()},${bounds.getEast()}`;
        loadPlacesInMapView(bbox);
      });
    } else {
      doSearch(hash);
    }
  }

  window.addEventListener('hashchange', handleHashChange);
  handleHashChange();

  document.addEventListener('app:languagechange', () => {
    rerenderLastResultState();
  });
});

function loadPlacesInMapView(bbox) {
  $.getJSON("lookup.php", { bbox })
    .fail((jqxhr, textStatus, error) => updateResultTableError(error))
    .done((data) => updateResultTable(data));
}

function doSearch(searchword) {
  $("#namefind").val(searchword).trigger('keyup');
}

function panToLocationHash(locationId) {
  $.getJSON("lookup.php", { locationid: locationId })
    .fail((jqxhr, textStatus, error) => updateResultTableError(error))
    .done((data) => {
      updateResultTable(data);
      if (!data || data.length === 0) {
        return;
      }
      const row = data[0];
      panToLocationId(row['centroid_onfeature_latitude'], row['centroid_onfeature_longitude'], row['id']);
    });
}

function updateResultTable(data) {
  lastResultState = { type: 'data', payload: data };
  requestCount++;
  currentCount = requestCount;
  if (data && data.length > 0) {
    let newtable = $("#tabletemplate").contents().clone();
    let wikidataurlprefix = 'https://www.wikidata.org/wiki/';
    for (row of data) {
      const latitude = Number(row['centroid_onfeature_latitude']);
      const longitude = Number(row['centroid_onfeature_longitude']);
      const locationId = Number(row['id']);
      var mapTohtml = `<span onclick="panToLocationId(${Number.isFinite(latitude) ? latitude : 0}, ${Number.isFinite(longitude) ? longitude : 0}, ${Number.isFinite(locationId) ? locationId : 0});">📍</span>`;
      var streetname = row['streetname'] ?? '';
      var streetnamehtml = escapeHtml(streetname);
      // if (row['sampleway_id']) {
      //   streetnamehtml = `<a href="https://www.openstreetmap.org/way/${row['sampleway_id']}">${streetnamehtml}</a>`;
      // }
      var areaname = escapeHtml(row['areaname'] ? row['areaname'] : translate('common.noArea'));
      var wikidatalinkhtml = '';
      var wikidataset = row['wikidataset'] ?? [];
      let nameEtymologyText = row['name:etymology'];
      let featureTypeLabel = translate(`featureTypes.${row['featuretype']}`);
      if (featureTypeLabel === `featureTypes.${row['featuretype']}`) {
        featureTypeLabel = capitalizeFirstLetter(row['featuretype']);
      }
      let featureType = `<span title="${escapeHtml(featureTypeLabel)}">${getFeatureTypeIcon(row['featuretype'])}</span>`;
      let topics = [];
      let descriptions = [];
      if (wikidataset.length > 0) {
        for (let item of wikidataset) {
          const presentation = getWikidataPresentation(item);
          const itemId = String(item.itemid ?? '');
          const escapedItemId = escapeHtml(itemId);
          const itemIdForJavascript = escapeHtml(JSON.stringify(itemId));
          const wikipediaLabel = escapeHtml(translate('common.wikipediaArticle'));
          const wikipediaUrl = escapeHtml(presentation.wikipediaUrl);
          const wikipediaLink = presentation.wikipediaUrl ? `<span class="topicwikipedia"><a href="${wikipediaUrl}" title="${wikipediaLabel}" aria-label="${wikipediaLabel}">${wikipediaLabel}</a></span>` : '';
          const wikidataBadge = escapeHtml(translate('common.wikidataBadge'));
          const wikidataLink = `<span class="topicwikidata"><a href="${wikidataurlprefix}${escapedItemId}" class="wikidataname" data-wikidata="${escapedItemId}">${wikidataBadge}</a></span>`;
          const topicTitle = escapeHtml(translate('common.findPlacesForTopicTitle'));
          const topicLabel = escapeHtml(presentation.label || translate('common.updating'));
          var wikidatalinkhtml = `<span class="topicrow"><a href="#${escapedItemId}" onclick="doSearch(${itemIdForJavascript}); return false;" title="${topicTitle}">${topicLabel}</a><span class="topiclinks">${wikipediaLink}${wikidataLink}</span></span>`;
          topics.push(wikidatalinkhtml);
          descriptions.push(escapeHtml(presentation.description));
        }
      }
      if (nameEtymologyText && nameEtymologyText != row['wikilabel']) {
        let extraDescription = '';
        if (wikidataset.length > 0) {
          extraDescription += '<br>';
        }
        extraDescription += `<em>${escapeHtml(nameEtymologyText)}</em>`;
        descriptions.push(extraDescription);
      }

      let topichtml = topics.join('')
      let descriptionhtml = descriptions.join('<br>')
      newtable.append(`<tr valign="top"><td class="mapToLink">${mapTohtml}</td><td class="featuretype">${featureType}</td><td>${streetnamehtml}</td><td>${areaname}</td><td>${topichtml}</td><td>${descriptionhtml}</td></tr>`);
    }
    // console.log('Current: ' + currentCount + ', request: ' + requestCount);
    $("#result").html(newtable);
    if (i18n && typeof i18n.applyTranslations === 'function') {
      i18n.applyTranslations(document.getElementById('result'));
    }
  } else {
    $("#result").html(translate('home.noRegisteredPlaces'));
  }
}

function getFeatureTypeIcon(featuretype) {
  let icon = '';
  let icons = {
    'museum': '🖼️',
    'way': '🛣️',
    'artwork': '🗿',
    'office': '🏢',
    'pedestrian': '🚶',
    'building': '🏠',
    'place': '🏙️',
    'park': '🌳',
    'water': '🌊',
    'wood': '🌲',
    'place_of_worship': '🛐',
    'square': '🔳',
    'equestrian': '🐎',
    'parking': '🅿️',
    'school': '🏫',
    'bridge': '🌉',
    'sport': '🏟️',
    'power': '⚡',
    'castle': '🏰',
    'aeroway': '✈️',
    'zoo': '🦁',
    'hospital': '🏥',
    'university': '🎓',
    'alcohol': '🍺',
    'power': '⚡',
    'harbour': '🛥️',
    'theatre': '🎭',
    'cinema': '🎬',
    'library': '📚',
    'playground': '🛝',
    'cemetery': '🪦',
    'prison': '👮',
    'theme_park': '🎢',
    'bakery': '🍞',
    'shop': '🛍️',
    'kindergarten': '👶',
    'hotel': '🏨',
    'historic': '⌘',
  }
  if (icons[featuretype]) {
    icon = icons[featuretype];
  } else {
    icon = '🌍';
  }
  return icon;
}

function updateResultTableError(error) {
  console.log(error);
  lastResultState = { type: 'error', payload: String(error) };
  $("#result").html(translate('common.errorPrefix', { error }));
}

function rerenderLastResultState() {
  if (!lastResultState) {
    return;
  }

  if (lastResultState.type === 'data') {
    updateResultTable(lastResultState.payload);
    return;
  }

  if (lastResultState.type === 'error') {
    $("#result").html(translate('common.errorPrefix', { error: lastResultState.payload }));
  }
}
