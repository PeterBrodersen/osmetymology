$(function () {
    window.onhashchange = hashChanged;
    getStats();
    getBoroughStats();
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

function getBoroughStats() {
    $.getJSON('/data/boroughs.json')
        .done((data) => {
            if (data) {
                let tbodyhtml = '';

                for (borough of data.etymologystats.boroughs) {
                    tbodyhtml += `
                    <tr>
                    <td class="numeric">${borough.borough_code}</td>
                    <td data-boroughcode="${borough.borough_code}"><a href="#${borough.borough_code}">${borough.borough_name}</a></td>
                    <td class="numeric">${borough.unique_human_female_topic}</td>
                    <td class="numeric">${borough.unique_human_male_topic}</td>
                    <td class="percentage-cell" data-female-percentage="${borough.human_female_percentage}" data-male-percentage="${borough.human_male_percentage}" style="--female-percentage:${borough.human_female_percentage}; --male-percentage:${borough.human_male_percentage};">
                        ${borough.human_female_percentage.toFixed(0)} / ${borough.human_male_percentage.toFixed(0)}
                    </td>
                    <td class="numeric">${borough.unique_female_topic}</td>
                    <td class="numeric">${borough.unique_male_topic}</td>
                    <td class="percentage-cell" data-female-percentage="${borough.female_percentage}" data-male-percentage="${borough.male_percentage}" style="--female-percentage:${borough.female_percentage}; --male-percentage:${borough.male_percentage};">
                        ${borough.female_percentage.toFixed(0)} / ${borough.male_percentage.toFixed(0)}
                    </td>
                    </tr>
                    `;
                }

                let tfoothtml = `
                    <tr>
                    <th></th>
                    <th>All boroughs</th>
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
                $('#boroughstats tbody').append(tbodyhtml);
                $('#boroughstats tfoot').append(tfoothtml);
            }
            $("#boroughstats").tablesorter();

            // Highlight row if hash is present
            if (window.location.hash.length > 1) {
                let hash = decodeURIComponent(window.location.hash.substring(1));
                hash = parseInt(hash);
                if (hash) {
                    $(`#boroughstats tbody tr td[data-boroughcode="${hash}"]`).parent().addClass('active');
                }
            }
        }
        );
}

function getSingleBoroughStats(borough_code) {
    // Remove highlight from previously clicked row
    $('#boroughstats tbody tr').removeClass('active');

    // Highlight the clicked row
    $(`#boroughstats tbody tr td[data-boroughcode="${borough_code}"]`).parent().addClass('active');

    let code = borough_code;
    let jsonurl = `/data/boroughs/${code}.json`;
    $.getJSON(jsonurl)
        .done((data) => {
            if (data) {
                let html = `<thead><tr><th colspan="2">${data.borough_name}</th></tr></thead><tbody>`;
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
                    html += `<tr><td colspan="2" style="font-style: italic">No registered roads in the borough are named after people</td></tr>`;
                }
                html += `</tbody>`;
                $('#singleborough').html(html);
                // location.hash = code;
                // scroll to table
                $('html, body').animate({
                    scrollTop: $("#singleborough").offset().top
                }, 500);
            }
        }
        );
}

function hashChanged() {
    let hash = decodeURIComponent(window.location.hash.substring(1));
    hash = parseInt(hash);
    if (hash) {
        getSingleBoroughStats(hash);
        return true;
    } else {
        $('#singleborough').html('');
        return false;
    }
}
