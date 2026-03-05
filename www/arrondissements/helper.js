$(function () {
    window.onhashchange = hashChanged;
    getStats();
    getArrondissementStats();
    if (window.location.hash.length > 1) {
        hashChanged();
    }
});

function getStats() {
    $.getJSON('/data/stats.json')
        .done((data) => {
            if (data) {
                $('#importfiletime').text(new Date(data.importfiletime * 1000).toLocaleDateString());
            }
        }
        );
}

function getArrondissementStats() {
    $.getJSON('/data/arrondissements.json')
        .done((data) => {
            if (data) {
                let tbodyhtml = '';

                for (arrondissement of data.etymologystats.arrondissements) {
                    tbodyhtml += `
                    <tr>
                    <td class="numeric">${arrondissement.arrondissement_code}</td>
                    <td data-arrondissementcode="${arrondissement.arrondissement_code}"><a href="#${arrondissement.arrondissement_code}">${arrondissement.arrondissement_name}</a></td>
                    <td class="numeric">${arrondissement.unique_human_female_topic}</td>
                    <td class="numeric">${arrondissement.unique_human_male_topic}</td>
                    <td class="percentage-cell" data-female-percentage="${arrondissement.human_female_percentage}" data-male-percentage="${arrondissement.human_male_percentage}" style="--female-percentage:${arrondissement.human_female_percentage}; --male-percentage:${arrondissement.human_male_percentage};">
                        ${arrondissement.human_female_percentage.toFixed(0)} / ${arrondissement.human_male_percentage.toFixed(0)}
                    </td>
                    <td class="numeric">${arrondissement.unique_female_topic}</td>
                    <td class="numeric">${arrondissement.unique_male_topic}</td>
                    <td class="percentage-cell" data-female-percentage="${arrondissement.female_percentage}" data-male-percentage="${arrondissement.male_percentage}" style="--female-percentage:${arrondissement.female_percentage}; --male-percentage:${arrondissement.male_percentage};">
                        ${arrondissement.female_percentage.toFixed(0)} / ${arrondissement.male_percentage.toFixed(0)}
                    </td>
                    </tr>
                    `;
                }

                let tfoothtml = `
                    <tr>
                    <th></th>
                    <th>All arrondissements</th>
                    <th class="numeric">${data.etymologystats.total.unique_human_female_topic}</th>
                    <th class="numeric">${data.etymologystats.total.unique_human_male_topic}</th>
                    <th class="percentage-cell" data-female-percentage="${data.etymologystats.total.human_female_percentage}" data-male-percentage="${data.etymologystats.total.human_male_percentage}" style="--female-percentage:${data.etymologystats.total.human_female_percentage}; --male-percentage:${data.etymologystats.total.human_male_percentage};">
                        ${data.etymologystats.total.human_female_percentage.toFixed(0)} / ${data.etymologystats.total.human_male_percentage.toFixed(0)}
                    </th>
                    <th class="numeric">${data.etymologystats.total.unique_female_topic}</th>
                    <th class="numeric">${data.etymologystats.total.unique_male_topic}</th>
                    <th class="percentage-cell" data-female-percentage="${data.etymologystats.total.female_percentage}" data-male-percentage="${data.etymologystats.total.male_percentage}" style="--female-percentage:${data.etymologystats.total.female_percentage}; --male-percentage:${data.etymologystats.total.male_percentage};">
                        ${data.etymologystats.total.female_percentage.toFixed(0)} / ${data.etymologystats.total.male_percentage.toFixed(0)}
                    </th>
                    </tr>
                `;
                $('#arrondissementstats tbody').append(tbodyhtml);
                $('#arrondissementstats tfoot').append(tfoothtml);
            }
            $("#arrondissementstats").tablesorter();

            // Highlight row if hash is present
            if (window.location.hash.length > 1) {
                let hash = decodeURIComponent(window.location.hash.substring(1));
                hash = parseInt(hash);
                if (hash) {
                    $(`#arrondissementstats tbody tr td[data-arrondissementcode="${hash}"]`).parent().addClass('active');
                }
            }
        }
        );
}

function getSingleArrondissementStats(arrondissement_code) {
    // Remove highlight from previously clicked row
    $('#arrondissementstats tbody tr').removeClass('active');

    // Highlight the clicked row
    $(`#arrondissementstats tbody tr td[data-arrondissementcode="${arrondissement_code}"]`).parent().addClass('active');

    let code = arrondissement_code;
    let jsonurl = `/data/arrondissements/${code}.json`;
    $.getJSON(jsonurl)
        .done((data) => {
            if (data) {
                let html = `<thead><tr><th colspan="2">${data.arrondissement_name}</th></tr></thead><tbody>`;
                let lastgender = '';
                for (item of data.items) {
                    if (lastgender != item.gender) {
                        let label = '';
                        let genderClass = '';
                        if (item.gender == 'female') {
                            label = 'Women';
                            genderClass = 'female-color';
                        } else {
                            label = 'Men';
                            genderClass = 'male-color';
                        }
                        html += `<tr><th colspan="2" class="${genderClass}">${label}</th></tr>`;
                        lastgender = item.gender;
                    }
                    let symbol = '';
                    if (item.is_human) {
                        symbol += '<span title="Real person">🧑</span>';
                    }
                    let ways = item.ways.replaceAll(';', '<br>');
                    // Escape HTML
                    html += `
                    <tr>
                    <td>${symbol} <a href="/#${item.wikidata_item}">${item.personname}</a><br><div class="persondetails">${item.description ?? ''}</div></td>
                    <td>${ways}</td>
                    </tr>
                    `;
                }
                if (data.items.length == 0) {
                    html += `<tr><td colspan="2" style="font-style: italic">No registered roads in the arrondissement are named after people</td></tr>`;
                }
                html += `</tbody>`;
                $('#singlearrondissement').html(html);
                // location.hash = code;
                // scroll to table
                $('html, body').animate({
                    scrollTop: $("#singlearrondissement").offset().top
                }, 500);
            }
        }
        );
}

function hashChanged() {
    let hash = decodeURIComponent(window.location.hash.substring(1));
    hash = parseInt(hash);
    if (hash) {
        getSingleArrondissementStats(hash);
        return true;
    } else {
        $('#singlearrondissement').html('');
        return false;
    }
}
