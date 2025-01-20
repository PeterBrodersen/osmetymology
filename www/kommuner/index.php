<!DOCTYPE html>
<html>

<head>
    <title>
        Oversigt og statistik for kønsfordeling for vejnavne i kommuner
    </title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.32.0/js/jquery.tablesorter.min.js" integrity="sha512-O/JP2r8BG27p5NOtVhwqsSokAwEP5RwYgvEzU9G6AfNjLYqyt2QT8jqU1XrXCiezS50Qp1i3ZtCQWkHZIRulGA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link href='/style.css' rel='stylesheet' />
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
    <p>
        Der er registreret ophav for ca. 35.000 veje i Danmark. Ikke alle veje er med endnu, og projektet er stadigvæk under udvikling, baseret på frivilligt arbejde.
    </p>
    <p>
        Dato for datasæt: <span id="importfiletime"></span>
    </p>

    <table id="municipalitystats" class="resulttable">
        <thead>
            <tr>
                <th>Kommunekode</th>
                <th>Kommune</th>
                <th>Kvinder,<br>menneske</th>
                <th>Mænd,<br>menneske</th>
                <th>Kvinder (%)<br>menneske</th>
                <th>Mænd (%)<br>menneske</th>
                <th>Kvinder,<br>alle</th>
                <th>Mænd,<br>alle</th>
                <th>Kvinder (%)<br>alle</th>
                <th>Mænd (%)<br>alle</th>
            </tr>
        </thead>
        <tbody>

        </tbody>
        <tfoot>
            <tr>
                <th>Kommunekode</th>
                <th>Kommune</th>
                <th>Kvinder,<br>menneske</th>
                <th>Mænd,<br>menneske</th>
                <th>Kvinder (%)<br>menneske</th>
                <th>Mænd (%)<br>menneske</th>
                <th>Kvinder,<br>alle</th>
                <th>Mænd,<br>alle</th>
                <th>Kvinder (%)<br>alle</th>
                <th>Mænd (%)<br>alle</th>
            </tr>
        </tfoot>
    </table>

    <table class="resulttable" id="singlemunicipality">
    </table>

    <div class="clear"></div>

    <p>
        Tallene for <b>Alle kommuner</b> er lavere end summen for de enkelte kommuner, idet dubletter (samme personer, som optræder
        i flere kommuner) kun bliver talt med én gang.
    </p>

    <div id="betaboilerplate">
        <p>
            BETA: Dette er en tidlig udgave af vejnavne-projektet.
        </p>
        <p>
            Ca. 35.000 veje i Danmark er registreret og katalogiseret. Dette udtræk henter kun veje og ikke fx bygninger, statuer, parker, m.m.
        </p>
        <p>
            Projektet er udviklet af <a href="https://www.openstreetmap.org/user/Peter%20Brodersen">Peter Brodersen</a>. Dette udtræk kan <a href="/data/municipalities.json">hentes som JSON-fil</a>. Har du spørgsmålet til projektet, er du mere end velkommen til at <a href="mailto:peter@ter.dk">sende en mail</a>.
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