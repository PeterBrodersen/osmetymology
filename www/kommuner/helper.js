$(function () {
    window.onhashchange = hashChanged;
    getStats();
    getMunicipalityStats();
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

function getMunicipalityStats() {
    $.getJSON('/data/municipalities.json')
        .done((data) => {
            if (data) {
                let tbodyhtml = '';

                for (municipality of data.etymologystats.municipalities) {
                    tbodyhtml += `
                    <tr>
                    <td class="numeric">${municipality.municipality_code}</td>
                    <td data-municipalitycode="${municipality.municipality_code}"><a href="#" onclick="getSingleMunicipalityStats(parentElement.dataset.municipalitycode); return false;">${municipality.municipality_name}</a></td>
                    <td class="numeric">${municipality.unique_human_female_topic}</td>
                    <td class="numeric">${municipality.unique_human_male_topic}</td>
                    <td class="numeric">${municipality.human_female_percentage.toFixed(0)}</td>
                    <td class="numeric">${municipality.human_male_percentage.toFixed(0)}</td>
                    <td class="numeric">${municipality.unique_female_topic}</td>
                    <td class="numeric">${municipality.unique_male_topic}</td>
                    <td class="numeric">${municipality.female_percentage.toFixed(0)}</td>
                    <td class="numeric">${municipality.male_percentage.toFixed(0)}</td>
                    </tr>
                    `;
                }

                let tfoothtml = `
                    <tr>
                    <th></th>
                    <th>Alle kommuner</th>
                    <th class="numeric">${data.etymologystats.total.unique_human_female_topic}</th>
                    <th class="numeric">${data.etymologystats.total.unique_human_male_topic}</th>
                    <th class="numeric">${data.etymologystats.total.human_female_percentage.toFixed(0)}</th>
                    <th class="numeric">${data.etymologystats.total.human_male_percentage.toFixed(0)}</th>
                    <th class="numeric">${data.etymologystats.total.unique_female_topic}</th>
                    <th class="numeric">${data.etymologystats.total.unique_male_topic}</th>
                    <th class="numeric">${data.etymologystats.total.female_percentage.toFixed(0)}</th>
                    <th class="numeric">${data.etymologystats.total.male_percentage.toFixed(0)}</th>
                    </tr>
                `;
                $('#municipalitystats tbody').html(tbodyhtml);
                $('#municipalitystats tfoot').html(tfoothtml);
            }
            $("#municipalitystats").tablesorter();
        }
        );
}

function getSingleMunicipalityStats(municipality_code) {
    let code = municipality_code.toString().padStart(4, '0'); // four digit municipality code
    let jsonurl = `/data/municipalities/${code}.json`;
    $.getJSON(jsonurl)
        .done((data) => {
            if (data) {
                let html = `<thead><tr><th colspan="2">${data.municipality_name}, ${data.region_name}</th></tr></thead><tbody>`;
                let lastgender = '';
                for (item of data.items) {
                    if (lastgender != item.gender) {
                        let label = '';
                        let color = '';
                        if (item.gender == 'female') {
                            label = 'Kvinder';
                            color = '#e99';
                        } else {
                            label = 'Mænd';
                            color = '#88e'
                        }
                        html += `<tr><th colspan="2" style="background-color: ${color}">${label}</th></tr>`
                        lastgender = item.gender;
                    }
                    let symbol = '';
                    if (item.is_human) {
                        symbol += '<span title="Menneske">🧑</span>';
                    }
                    if (item.gender == 'female') {
                        symbol += '<span title="Kvinde">♀</span>';
                    }
                    if (item.gender == 'male') {
                        symbol += '<span title="Mand">♂</span>';
                    }
                    let ways = item.ways.replaceAll(';', '<br>');
                    // :TODO: Escape HTML
                    html += `
                    <tr>
                    <td>${symbol} <a href="/#${item.wikidata_item}">${item.personname}</a><br><div class="persondetails">${item.description ?? ''}</div></td>
                    <td>${ways}</td>
                    </tr>
                    `;
                }
                if (data.items.length == 0) {
                    html += `<tr><td colspan="2" style="font-style: italic">Ingen registrerede veje i kommunen er opkaldt efter personer</td></tr>`
                }
                html += `</tbody>`;
                $('#singlemunicipality').html(html);
                location.hash = code;
                // scroll to table
                $('html, body').animate({
                    scrollTop: $("#singlemunicipality").offset().top
                }, 500);
            }
        }
        );
}

function hashChanged() {
    let hash = decodeURIComponent(window.location.hash.substring(1));
    hash = parseInt(hash);
    if (hash) {
        getSingleMunicipalityStats(hash);
        return true;
    } else {
        $('#singlemunicipality').html('');
        return false;
    }
}
