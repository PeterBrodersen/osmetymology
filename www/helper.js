let requestCount = 0;
let lastinputname = '';
let wikidata = {};
let languages = ['da','en','sv','nb','de','es','fr','fi','is'];

$(function () {
  $( "#namefind" ).on( "keyup", function() {
    requestCount++;
    let inputname = $( "#namefind" ).val();
    if (inputname == lastinputname) { // don't request for random key presses such as shift
      return;
    }
    if (inputname.length < 3) {
      return;
    }
    lastinputname = inputname;
    $.getJSON( "lookup.php", { streetname: inputname} )
      .done(function (data)  {
        if (data && data.length > 0) {
          let newtable = $("#tabletemplate").contents().clone();
          let wikidataurlprefix = 'https://www.wikidata.org/wiki/';
          let wikidataitems = [];
          for (row of data) {
            var streetname = row['streetname'] ?? '';
            var streetnamehtml = streetname;
            if (row['sampleway_id']) {
              streetnamehtml = `<a href="https://www.openstreetmap.org/way/${row['sampleway_id']}">${streetnamehtml}</a>`;
            }
            var municipalityname = row['municipalityname'] ?? '';
            var wikidatalinkhtml = '';
            var wikidatadescriptionhtml = '';
            if ( row['name:etymology:wikidata'] ) {
              var wikidatalinkhtml = `<a href="${wikidataurlprefix}${row['name:etymology:wikidata']}" class="wikidataname" data-wikidata="${row['name:etymology:wikidata']}">${row['name:etymology:wikidata']}</a>`;
              wikidataitems.push(row['name:etymology:wikidata']);
              var wikidatadescriptionhtml = `<span class="wikidatadescription" data-wikidata="${row['name:etymology:wikidata']}"></span>`;
            } else if (row['name:etymology']) {
              var wikidatadescriptionhtml = `<span>${row['name:etymology']}</span>`;
            }
            // :TODO: Escape HTML; there ought not to be tags in the result, but better safe than sorry ...
            newtable.append("<tr><td>" + streetnamehtml + "</td><td>" + municipalityname + "</td><td>" + wikidatalinkhtml + "</td><td>" + wikidatadescriptionhtml + "</td></tr>");
          }
          console.log(data);
          $("#result").html(newtable);
          updateWikidataLabels(wikidataitems);
        } else {
          $("#result").html('Intet resultat!');
        }
      });
  } );
});

function updateWikidataLabels(itemList) {
  itemList = [...new Set(itemList)];
  let shortList = [];
  for (itemId of itemList) {
    if (! /^Q\d+$/.test(itemId) ) { // Must match Q + digits
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

function getWikidataItems(itemIds) { // Read single Wikidata Item; should probably fetch everyone at once by using | as separator
  // Perform AJAX request to fetch Wikidata item
  $.ajax({
      url: 'https://www.wikidata.org/w/api.php',
      data: {
          action: 'wbgetentities',
          // ids: itemId,
          ids: itemIds.join('|'), // Join IDs with pipe (|) separator
          format: 'json',
          origin: '*' // This is required for CORS
      },
      dataType: 'json',
      success: function(data) {
          // Process the response
          $.each(itemIds, function(index, itemId) {
            wikidata[itemId] = data.entities[itemId];
        });
        updateWikidataHTML();
      },
      error: function(xhr, status, error) {
          console.error('Error fetching data: ', error);
      }
  });
  return true;
}

function updateWikidataHTML() {
  $(".wikidataname").each( function() { // Update labels
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
  $(".wikidatadescription").each( function() { // Update descriptions
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
