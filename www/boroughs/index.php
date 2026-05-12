<!DOCTYPE html>
<html>

<head>
    <title>
        Overview and statistics for gender distribution of street names in London's boroughs
    </title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.32.0/js/jquery.tablesorter.min.js" integrity="sha512-O/JP2r8BG27p5NOtVhwqsSokAwEP5RwYgvEzU9G6AfNjLYqyt2QT8jqU1XrXCiezS50Qp1i3ZtCQWkHZIRulGA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link href='/style.css' rel='stylesheet' />
    <script src="/boroughs/helper.js"></script>
    <meta property="og:image" content="https://navne.findvej.dk/media/l%C3%A6rkevej.png" />
    <meta property="og:image:width" content="1000" />
    <meta property="og:image:height" content="800" />
</head>

<body>
    <h1>Statistics for gender distribution of street names in London's boroughs</h1>
    <p>
        Statistics from the <a href="/">Name Project</a> for gender distribution on streets in the boroughs. Each person is counted only once within an borough, even if multiple street names refer to the same person. Click on an borough name to see the people in the borough with streets associated.
    </p>
    <p>
        About 800 streets in London have registered etymology. Not all streets are included yet, and the project is still under development, based on voluntary work.
    </p>
    <p>
        Date for data set: <span id="importfiletime"></span>
    </p>

    <table id="boroughstats" class="resulttable">
        <thead>
            <tr>
                <th rowspan="2">Borough</th>
                <th rowspan="2">Name</th>
                <th colspan="3">Humans that have existed</th>
                <th colspan="3">All humans, including fictional</th>
            </tr>
            <tr>
                <th>Women</th>
                <th>Men</th>
                <th>Percentage distribution</th>
                <th>Women</th>
                <th>Men</th>
                <th>Percentage distribution</th>
            </tr>
        </thead>
        <tbody>

        </tbody>
        <tfoot>
            <tr>
                <th>Borough</th>
                <th>Name</th>
                <th>Women,<br>humans</th>
                <th>Men,<br>humans</th>
                <th>Percentage distribution,<br>humans</th>
                <th>Women,<br>all</th>
                <th>Men,<br>all</th>
                <th>Percentage distribution</th>
            </tr>
        </tfoot>
    </table>

    <table class="resulttable" id="singleborough">
    </table>

    <div class="clear"></div>

    <p>
        The sum for all boroughs is higher than the total for <b>All boroughs</b>, as duplicates (same people appearing in multiple boroughs) are only counted once in the total.
    </p>

    <div id="betaboilerplate">
        <p>
            About 800 streets in London are registrered. This extract only fetches roads and not e.g. buildings
            statues, parks, etc.
        </p>
        <p>
            The project is developed by <a href="https://www.openstreetmap.org/user/Peter%20Brodersen">Peter Brodersen</a>. This extract for boroughs can be <a href="/data/boroughs.json">downloaded as JSON file</a>. If you have questions about the project, feel free to <a href="mailto:peter@ter.dk">send an email</a>.
        </p>
        <p class="copyright">
            Map data is sourced from <a href="https://www.openstreetmap.org/">OpenStreetMap</a> and is licensed under the
            <a href="https://www.openstreetmap.org/copyright">Open Data Commons Open Database License (ODbL)</a>. Metadata is sourced from <a href="https://www.wikidata.org/">Wikidata</a> and is licensed under the
            <a href="https://creativecommons.org/publicdomain/zero/1.0/deed.da">Creative Commons CC0 License</a>.
        </p>
    </div>

</body>

</html>
