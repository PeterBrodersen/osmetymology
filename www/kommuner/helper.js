$(function () {
    getStats();
    getMunicipalityStats();
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
                let html = '';
                for (municipality of data) {
                    html += `
                    <tr>
                    <td class="numeric">${municipality.municipality_code}</td>
                    <td data-municipalitycode="${municipality.municipality_code}"><a href="#" onclick="getSingleMunicipalityStats(parentElement.dataset.municipalitycode)">${municipality.municipality_name}</a></td>
                    <td class="numeric">${municipality.unique_female_topic}</td>
                    <td class="numeric">${municipality.unique_male_topic}</td>
                    <td class="numeric">${municipality.female_percentage.toFixed(0)}</td>
                    <td class="numeric">${municipality.male_percentage.toFixed(0)}</td>
                    </tr>
                    `;
                }
                $('#municipalitystats tbody').html(html);
                sortTable(4, true);
                sortTable(4, true);
            }
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
                for (item of data.items) {
                    var symbol = (item.gender == 'female' ? '♀' : '♂'); // assuming gender is set
                    var ways = item.ways.replace(';', '<br>');
                    // :TODO: Escape HTML
                    html += `
                    <tr>
                    <td>${symbol} <a href="/#${item.wikidata_item}">${item.personname}</a><br><div class="persondetails">${item.description}</div></td>
                    <td>${ways}</td>
                    </tr>
                    `;
                }
                html += `</tbody>`;
                $('#singlemunicipality').html(html);

            }
        }
        );
}


// sort code from https://www.w3schools.com/howto/howto_js_sort_table.asp
// numeric sort option added by pb
function sortTable(n, numeric) {
    var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
    table = document.getElementById("municipalitystats");
    switching = true;
    // Set the sorting direction to ascending:
    dir = "asc";
    /* Make a loop that will continue until
    no switching has been done: */
    while (switching) {
        // Start by saying: no switching is done:
        switching = false;
        rows = table.rows;
        /* Loop through all table rows (except the
        first, which contains table headers): */
        for (i = 1; i < (rows.length - 1); i++) {
            // Start by saying there should be no switching:
            shouldSwitch = false;
            /* Get the two elements you want to compare,
            one from current row and one from the next: */
            x = rows[i].getElementsByTagName("TD")[n];
            y = rows[i + 1].getElementsByTagName("TD")[n];
            /* Check if the two rows should switch place,
            based on the direction, asc or desc: */
            if (dir == "asc") {
                if (!numeric && (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) || numeric && (Number(x.innerHTML) > Number(y.innerHTML))) {
                    // If so, mark as a switch and break the loop:
                    shouldSwitch = true;
                    break;
                }
            } else if (dir == "desc") {
                if (!numeric && (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) || numeric && (Number(x.innerHTML) < Number(y.innerHTML))) {
                    // If so, mark as a switch and break the loop:
                    shouldSwitch = true;
                    break;
                }
            }
        }
        if (shouldSwitch) {
            /* If a switch has been marked, make the switch
            and mark that a switch has been done: */
            rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
            switching = true;
            // Each time a switch is done, increase this count by 1:
            switchcount++;
        } else {
            /* If no switching has been done AND the direction is "asc",
            set the direction to "desc" and run the while loop again. */
            if (switchcount == 0 && dir == "asc") {
                dir = "desc";
                switching = true;
            }
        }
    }
}