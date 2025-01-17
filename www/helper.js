let requestCount = 0;
let lastinputname = '';
let lastinputlabel = '';
let wikidata = {};
let languages = ['da', 'en', 'sv', 'nb', 'de', 'es', 'fr', 'fi', 'is'];
let currentCount = 0;

$(function () {
  $("#namefind").on("keyup", () => {
    let inputname = $("#namefind").val();
    if (inputname == lastinputname) { // don't request for random key presses such as shift
      return;
    }
    lastinputname = inputname;

    // if (inputname.length < 3) {
    if (!(/^(Q\d+|.{3,})$/).test(inputname)) {
      $("#result").html('');
      return;
    }
    $("#copylink a").show().attr('href', '#' + inputname);
    $(".resulttable").fadeTo("slow", 0.5);
    $.getJSON("lookup.php", { search: inputname })
      .fail((jqxhr, textStatus, error) => updateResultTableError(error))
      .done((data) => updateResultTable(data));
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
      console.log("Selected: " + ui.item.value + " aka " + ui.item.itemid);
      doSearch(ui.item.itemid);
    }
    })
    .autocomplete("instance")._renderItem = function (ul, item) {
      var showname = item.name;
      if (!item.name.toLowerCase().startsWith(this.term.toLowerCase().trim() )) {
        showname = item.name + ' (<em>' + item.label + '</em>)';
      }

      let optionHTML = `<div class="autoitemblock" title="${item.label}">`;
      optionHTML += `<span class="autoitemname">${showname}</span>`;
      optionHTML += `<div class="autoitemdetails">`;
      if (item.description) {
        optionHTML += `<span class="autoitemdescription">${item.description}</span><br>`;
      }
      optionHTML += `<span class="autoitemcount">${item.placecount} ${item.placecount == 1 ? "sted" : "steder"} </span>`;
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
  $("#copylink a").on("click", () => {
    let url = $("#copylink a").prop('href');
    window.location.hash = url;
    navigator.clipboard.writeText(url);
    $(this).css('background-color', 'yellow');

    $("#copylink a").animate({ backgroundColor: 'yellow' }, 300).animate({ backgroundColor: 'white' }, 300);
  });

  $("#getposition").on("click", () => { // :TODO: Indicate a location search is going on
    $("#result").html('Finder din position - et øjeblik ...');
    // map.locate({ enableHighAccuracy: true });
    map.locate();
  });

  $("#copylinktomap").on("click", () => {
    let url = $("#copylinktomap").prop('href');
    window.location.hash = url;
    navigator.clipboard.writeText(url);
    $(this).css('background-color', 'yellow');

    $("#copylinktomap").animate({ backgroundColor: 'yellow' }, 300).animate({ backgroundColor: 'white' }, 300);
  });

  $("#showplacesinmapview").on("click", () => {
    const bounds = map.getBounds();
    let bbox = `${bounds.getSouth()},${bounds.getWest()},${bounds.getNorth()},${bounds.getEast()}`;
    $.getJSON("lookup.php", { bbox })
      .fail((jqxhr, textStatus, error) => updateResultTableError(error))
      .done((data) => updateResultTable(data));

  });

  // Start if hash fragment is present
  if (window.location.hash.length > 1) {
    // should be moved to map.js startup instead of starting a location that we immediately move away from
    let hash = decodeURIComponent(window.location.hash.substring(1));
    const regex = /^map=(\d+)\/(\d+(?:\.\d+)?)\/(\d+(?:\.\d+)?)$/;
    const match = hash.match(regex);
    if (match && map.setView) {
      map.setView(L.latLng(match[2], match[3]), match[1]);
    } else {
      doSearch(hash);
    }
  }
  getStats();
});

function getStats() {
  $.getJSON('data/stats.json')
    .done((data) => {
      if (data) {
        $('.stats #totalroads').text(data.totalroads.toLocaleString());
        $('.stats #uniquenamedroads').text(data.uniquenamedroads.toLocaleString());
        $('.stats #uniqueetymologywikidata').text(data.uniqueetymologywikidata.toLocaleString());
        $('.stats #importfiletime').text(new Date(data.importfiletime * 1000).toLocaleDateString());
      }
    }
    );
}

function doSearch(searchword) {
  $("#namefind").val(searchword).trigger('keyup');
}

function updateWikidataLabels(itemList) {
  itemList = [...new Set(itemList)];
  let shortList = [];
  for (itemId of itemList) {
    if (!(/^Q\d+$/).test(itemId)) { // Must match Q + digits
      continue;
    }
    if (wikidata[itemId]) { // already set
      continue;
    }
    shortList.push(itemId);
  }
  if (shortList.length > 0) {
    getWikidataItems(shortList);
  } else { // No new items
    updateWikidataHTML();
  }
  return true;
}

async function getWikidataItems(itemIdsAll) {
  let itemLimit = 50; // max limit for wbgetentities in Wikidata API call
  itemArr = [];
  // save in chucks
  for (let i = 0; i < itemIdsAll.length; i += itemLimit) {
    const chunk = itemIdsAll.slice(i, i + itemLimit);
    itemArr.push(chunk);
  }
  //  itemArr = [itemArr[0]];
  for (itemIds of itemArr) {
    // Perform AJAX request to fetch Wikidata item
    await $.ajax({
      url: 'https://www.wikidata.org/w/api.php',
      data: {
        action: 'wbgetentities',
        // ids: itemId,
        ids: itemIds.join('|'), // Join IDs with pipe (|) separator
        format: 'json',
        origin: '*' // This is required for CORS
      },
      dataType: 'json',
      success: function (data) {
        // Process the response
        $.each(itemIds, function (index, itemId) {
          wikidata[itemId] = data.entities[itemId];
        });
        updateWikidataHTML();
      },
      error: function (xhr, status, error) {
        console.error('Error fetching data: ', error);
      }
    });
  }
  return true;
}

function updateWikidataHTML() {
  $(".wikidataname").each(function () { // Update labels
    let element = $(this);
    let itemId = element.data('wikidata');
    let label = '';
    if (itemId && wikidata[itemId]) {
      for (language of languages) { // use first accepted language
        label = wikidata[itemId].labels[language]?.value;
        if (label) {
          break;
        }
      }
    }
    if (label) {
      element.html(label);
    }
  });
  $(".wikidatadescription").each(function () { // Update descriptions
    let element = $(this);
    let itemId = element.data('wikidata');
    let description = '';
    if (itemId && wikidata[itemId]) {
      for (language of languages) {
        description = wikidata[itemId].descriptions[language]?.value;
        if (description) {
          break;
        }
      }
    }
    if (description) {
      element.html(description);
    }
  });
  return true;
}

/*
addEventListener("hashchange", (event) => {
  var starttext = decodeURIComponent(window.location.hash.substring(1))
  doSearch(starttext);
});
*/

function updateResultTable(data) {
  requestCount++;
  currentCount = requestCount;
  if (data && data.length > 0) {
    let newtable = $("#tabletemplate").contents().clone();
    let wikidataurlprefix = 'https://www.wikidata.org/wiki/';
    let wikidataitems = [];
    for (row of data) {
      var mapTohtml = `<span onclick="panToWayId(${row['centroid_latitude']}, ${row['centroid_longitude']}, ${row['id']});">📍</span>`;
      var streetname = row['streetname'] ?? '';
      var streetnamehtml = streetname;
      // if (row['sampleway_id']) {
      //   streetnamehtml = `<a href="https://www.openstreetmap.org/way/${row['sampleway_id']}">${streetnamehtml}</a>`;
      // }
      var municipalityname = row['municipalityname'] ?? '';
      var wikidatalinkhtml = '';
      var wikidatadescriptionhtml = '';
      let wikidataId = row['name:etymology:wikidata'];
      let featureType = `<span title="${capitalizeFirstLetter(row['featuretype'])}">${getFeatureTypeIcon(row['featuretype'])}</span>`;
      let hasSingleWikidataItem = /^(Q\d+)$/.test(wikidataId);
      let hasMultipleWikidataItems = /^(Q\d+\s*(;\s*Q\d+)+)$/.test(wikidataId);
      if (hasSingleWikidataItem) {
        var wikidatalinkhtml = `<a href="#${wikidataId}" onclick="doSearch('${wikidataId}'); return false;">${row['wikilabel']}</a> ` +
          `<sup><a href="${wikidataurlprefix}${wikidataId}" class="wikidataname" data-wikidata="${wikidataId}">[Wikidata]</a></sup>`;
        wikidataitems.push(wikidataId);
        var wikidatadescriptionhtml = `<span class="wikidatadescription" data-wikidata="${wikidataId}">${row['wikidescription'] ?? ''}</span>`;
      } else if (hasMultipleWikidataItems) {
        var wikidatalinkhtml = `Wikidata-emne: `;
        let countItems = 0;
        for (let wikidataSingleId of wikidataId.split(/\s*;\s*/)) {
          countItems++;
          wikidatalinkhtml += `<a href="${wikidataurlprefix}${wikidataSingleId}" class="wikidataname" data-wikidata="${wikidataSingleId}">[${countItems}]</a> `;
        }
        if (row['name:etymology']) {
          var wikidatadescriptionhtml = `<span>${row['name:etymology']}</span>`;
        }
      } else if (row['name:etymology']) {
        var wikidatadescriptionhtml = `<span>${row['name:etymology']}</span>`;
      }
      // :TODO: Escape HTML; there ought not to be tags in the result, but better safe than sorry ...
      //        E.g. create as jquery DOM and add text with .text()
      newtable.append(`<tr><td class="mapToLink">${mapTohtml}</td><td class="featuretype">${featureType}</td><td>${streetnamehtml}</td><td>${municipalityname}</td><td>${wikidatalinkhtml}</td><td>${wikidatadescriptionhtml}</td></tr>`);
    }
    // console.log('Current: ' + currentCount + ', request: ' + requestCount);
    // updateWikidataLabels(wikidataitems);
    $("#result").html(newtable);
  } else {
    $("#result").html('Intet resultat!');
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
  $("#result").html('Fejl: ' + error);
}