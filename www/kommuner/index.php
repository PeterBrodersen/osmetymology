<!DOCTYPE html>
<html>

<head>
    <title>
        Oversigt og statistik for kønsfordeling for vejnavne i kommuner
    </title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href='/style.css' rel='stylesheet' />
    <!-- Find tablesorter -->
    <script src="/kommuner/helper.js"></script>
    <meta property="og:image" content="https://navne.findvej.dk/media/l%C3%A6rkevej.png" />
    <meta property="og:image:width" content="1000" />
    <meta property="og:image:height" content="800" />
</head>

<body>
    <h1>Statistik for kønsfordeling for vejnavne i kommuner</h1>
    <p>
        Statistik fra <a href="/">Navneprojektet</a> for kønsfordeling på veje i kommunen. Hver person tælles kun med én gang inden for en kommune, også selv
        om flere vejnavne refererer til personen. Klik på et kommunenavn for at se personer i kommunen med veje tilknyttet.
    </p>
    <p>Dato for datasæt: <span id="importfiletime"></span></p>

    <table id="municipalitystats" class="resulttable" style="float: left;  margin-right: 3em;">
        <thead>
            <tr style="cursor: pointer;">
                <th onclick="sortTable(0,true)">Kommunekode</th>
                <th onclick="sortTable(1,false)">Kommune</th>
                <th onclick="sortTable(2,true)">Kvinder</th>
                <th onclick="sortTable(3,true)">Mænd</th>
                <th onclick="sortTable(4,true)">Kvinder (%)</th>
                <th onclick="sortTable(5,true)">Mænd (%)</th>
            </tr>
        </thead>
        <tbody>

        </tbody>
    </table>

    <table class="resulttable" id="singlemunicipality" style="float: left;">
    </table>

    <div style="clear: both;"></div>

    <div id="betaboilerplate">
        <p>
            BETA: Dette er en tidlig udgave af vejnavne-projektet.
        </p>
        <p>
            Ca. 35.000 veje i Danmark er registreret og katalogiseret. Dette udtræk henter kun veje og ikke fx bygninger, statuer, parker, pladser, m.m.
        </p>
        <p>
            Projektet er udviklet af <a href="https://www.openstreetmap.org/user/Peter%20Brodersen">Peter Brodersen</a>. Dette udtræk kan <a href="/data/municipalities.json">hentes som JSON-fil</a>. Har du spørgsmålet til projektet, er du mere end velkommen til at  <a href="mailto:peter@ter.dk">sende en mail</a>.
        </p>
        <p class="copyright">
            Kortdata er hentet fra <a href="https://www.openstreetmap.org/">OpenStreetMap</a> og er frigivet under
            <a href="https://www.openstreetmap.org/copyright">Open Data Commons Open Database License (ODbL)</a>.
            Metadata er hentet fra <a href="https://www.wikidata.org/">Wikidata</a> og er frigivet under
            <a href="https://creativecommons.org/publicdomain/zero/1.0/deed.da">Creative Commons CC0 licens</a>.
        </p>
    </div>

</body>

</html>