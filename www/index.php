<!DOCTYPE html>
<html>

<head>
    <title>
        Hvad er danske vejnavne og steder opkaldt efter?
    </title>
    <script src="https://unpkg.com/underscore@1.13.1/underscore-min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" integrity="sha256-kLaT2GOSpHechhsozzB+flnD+zUyjE2LlfWPgU04xyI=" crossorigin="" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/flatgeobuf/dist/flatgeobuf-geojson.min.js"></script>
    <script src="https://unpkg.com/json-formatter-js"></script>
    <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
    <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css' rel='stylesheet' />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/color/jquery.color-2.1.2.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol/dist/L.Control.Locate.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol/dist/L.Control.Locate.min.js" charset="utf-8"></script> -->
    <script src="/map.js"></script>
    <script src="/helper.js"></script>
    <link href='/style.css' rel='stylesheet' />
    <meta property="og:image" content="https://navne.findvej.dk/media/l%C3%A6rkevej.png" />
    <meta property="og:image:width" content="1000" />
    <meta property="og:image:height" content="800" />
</head>

<body>
    <h1>Hvad er danske vejnavne og steder opkaldt efter?</h1>

    <div id="userinput"><input required autofocus id="namefind" placeholder="Slå vejnavn op" accesskey="f"> <span id="copylink"><a href="#">[kopiér link 🔗]</a></span></div>

    <div id="result">
    </div>

    <template id="tabletemplate">
        <table class="resulttable">
            <tr class="tableheader">
                <th>Kort</th>
                <th>Type</th>
                <th>Stednavn</th>
                <th>Kommune</th>
                <th>Wikidata-emne</th>
                <th>Beskrivelse</th>
            </tr>
        </table>
    </template>

    <div id="maplinks">
        <div>
            <a href="#" id="getposition">[➹ find nærmeste steder nær dig]</a> <span class="location-loader" style="display:none;"></span>
        </div>
        <div>
            <a href="#" id="showplacesinmapview">[list alle steder i kortudsnit]</a><br>
        </div>
        <div>
            <a href="#" id="copylinktomap">[kopiér link til kortudsnit 🔗]</a><br>
        </div>

    </div>
    <div id="map" style="height: 700px; width: 100%; border: 1px solid black; z-index: 90; margin-top: 10px;"></div>

    <div id="betaboilerplate">
        <p>
            BETA: Dette er en tidlig udgave af vejnavne-projektet. Meget mere er på vej, blandt andet opslag på emner, kort, statistik, m.m. Forvent fejl og ændringer.
        </p>
        <p>
            Datagrundlaget er veje i det frivillige kort-projekt <a href="https://www.openstreetmap.org/">OpenStreetMap</a>. I løbet af
            <a href="https://taginfo.geofabrik.de/europe:denmark/keys/name%3Aetymology%3Awikidata#chronology">det seneste år</a> har frivillige
            bladret <a href="https://github.com/PeterBrodersen/osmetymology/blob/main/Resources.md">bøger og websites</a> igennem for oplysninger om,
            hvad danske vejnavne er opkaldt efter. Der findes ingen centrale kilder i øvrigt om, hvad danske vejnavne er opkaldt efter.
        </p>
        <p>
            Der mangler stadigvæk oplysninger for mange veje. Pt. er der oplysninger om over 20.000 veje i Danmark, som er opkaldt efter over 6.500 forskellige emner.
        </p>
        <p>
            Det kan være en udfordring at finde det korrekte ophav for et navn. Ananasvej i Aalborg Kommune er opkaldt efter Ananas, mens Ananasvej i Favrskov Kommune
            er opkaldt efter æblesorten Rød Ananas. Og for det mest udbredte vejnavn i Danmark, Lærkevej, er 70 % opkaldt efter lærkefuglen, mens 30 % er opkaldt efter
            lærketræet. <a href="https://github.com/PeterBrodersen/osmetymology/blob/main/Editing.md?tab=readme-ov-file#caveats">Tjek oversigten med typiske fælder.</a>
        </p>
        <p>
            Projektet er udviklet af <a href="https://www.openstreetmap.org/user/Peter%20Brodersen">Peter Brodersen</a>. Du kan hente alle danske veje med oplysninger i en <a href="/data/navne.csv">kommasepareret fil</a> og i <a href="/data/aggregate.fgb">FlatGeobuf-format (for GIS-brugere)</a> (og link gerne tilbage til denne side, hvis du gør brug af data). Koden bag projektet er <a href="https://github.com/PeterBrodersen/osmetymology">tilgængeligt på GitHub</a>.
            Har du spørgsmålet om <b>projektet</b> (men <i>ikke</i> spørgsmål om manglende vejnavne), er du mere end velkommen <a href="mailto:peter@ter.dk">sende en mail</a>.
        </p>

        <p class="copyright">
            Kortdata er hentet fra <a href="https://www.openstreetmap.org/">OpenStreetMap</a> og er frigivet under <a href="https://www.openstreetmap.org/copyright">Open Data Commons Open Database License (ODbL)</a>. Metadata er hentet fra <a href="https://www.wikidata.org/">Wikidata</a> og er frigivet under <a href="https://creativecommons.org/publicdomain/zero/1.0/deed.da">Creative Commons CC0 licens</a>.
        </p>

        <p class="stats">
            Indhold i databasen:<br>
            Antal steder: <span id="totalroads"></span><br>
            Antal unikt navngivne steder: <span id="uniquenamedroads"></span><br>
            Antal unikke emner, steder er opkaldt efter: <span id="uniqueetymologywikidata"></span><br>
            Dato for datasæt: <span id="importfiletime"></span>
        </p>
    </div>
</body>

</html>