$(function () {
    window.onhashchange = hashChanged;
    getStats();
    getAreaStats();
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

function getAreaStats() {
    $.getJSON('/data/areas.json')
        .done((data) => {
            if (data) {
                let tbodyhtml = '';

                for (area of data.etymologystats.areas) {
                    tbodyhtml += `
                    <tr>
                    <td class="numeric">${area.area_code}</td>
                    <td data-areacode="${area.area_code}"><a href="#${area.area_code}">${area.area_name}</a></td>
                    <td class="numeric">${area.unique_human_female_topic}</td>
                    <td class="numeric">${area.unique_human_male_topic}</td>
                    <td class="percentage-cell" data-female-percentage="${area.human_female_percentage}" data-male-percentage="${area.human_male_percentage}" style="--female-percentage:${area.human_female_percentage}; --male-percentage:${area.human_male_percentage};">
                        ${area.human_female_percentage.toFixed(0)} / ${area.human_male_percentage.toFixed(0)}
                    </td>
                    <td class="numeric">${area.unique_female_topic}</td>
                    <td class="numeric">${area.unique_male_topic}</td>
                    <td class="percentage-cell" data-female-percentage="${area.female_percentage}" data-male-percentage="${area.male_percentage}" style="--female-percentage:${area.female_percentage}; --male-percentage:${area.male_percentage};">
                        ${area.female_percentage.toFixed(0)} / ${area.male_percentage.toFixed(0)}
                    </td>
                    </tr>
                    `;
                }

                let tfoothtml = `
                    <tr>
                    <th></th>
                    <th>All areas</th>
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
                $('#areastats tbody').append(tbodyhtml);
                $('#areastats tfoot').append(tfoothtml);
            }
            $("#areastats").tablesorter();

            // Highlight row if hash is present
            if (window.location.hash.length > 1) {
                let hash = parseInt(window.location.hash.substring(1), 10);
                $(`#areastats tbody tr td[data-areacode="${hash}"]`).parent().addClass('active');
            }
        }
        );
}

function getSingleAreaStats(area_code) {
    // Remove highlight from previously clicked row
    $('#areastats tbody tr').removeClass('active');

    // Highlight the clicked row
    $(`#areastats tbody tr td[data-areacode="${area_code}"]`).parent().addClass('active');

    let code = area_code;
    let jsonurl = `/data/areas/${code}.json`;
    $.getJSON(jsonurl)
        .done((data) => {
            if (data) {
                let html = `<thead><tr><th colspan="2">${data.area_name}</th></tr></thead><tbody>`;
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
                    html += `<tr><td colspan="2" style="font-style: italic">No registered roads in the area are named after people</td></tr>`;
                }
                html += `</tbody>`;
                $('#singlearea').html(html);
                // location.hash = code;
                // scroll to table
                $('html, body').animate({
                    scrollTop: $("#singlearea").offset().top
                }, 500);
            }
        }
        );
}

function hashChanged() {
    if (window.location.hash.length > 1) {
        getSingleAreaStats(parseInt(window.location.hash.substring(1), 10));
        return true;
    } else {
        $('#singlearea').html('');
        return false;
    }
}
