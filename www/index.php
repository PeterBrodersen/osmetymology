<!DOCTYPE html>
<html>

<head>
    <title>
        Hvad er danske vejnavne og steder opkaldt efter?
    </title>
    <script src="https://cdn.jsdelivr.net/npm/underscore@1.13.1/underscore-min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.3/dist/leaflet.css" integrity="sha256-kLaT2GOSpHechhsozzB+flnD+zUyjE2LlfWPgU04xyI=" crossorigin="" />
    <script src="https://cdn.jsdelivr.net/npm/leaflet/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatgeobuf/dist/flatgeobuf-geojson.min.js"></script>
    <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
    <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css' rel='stylesheet' />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.14.0/jquery-ui.js"></script>
    <script src="https://code.jquery.com/color/jquery.color-2.1.2.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.0/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <script src="https://cdn.jsdelivr.net/npm/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
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

<div style="clear: both;">
</div>

    <div id="userinput">
        <div id="placename"><input required autofocus id="namefind" placeholder="Slå vejnavn op" accesskey="f"> <span id="copylink"><a href="#">[kopiér link 🔗]</a></span></div>
        <div id="itemname"><input required autofocus id="itemfind" placeholder="Slå emne op" accesskey="t"></div>
    </div>

    <div id="result">
    </div>

    <template id="tabletemplate">
        <table class="resulttable">
            <thead>
                <tr class="tableheader">
                    <th>Kort</th>
                    <th>Type</th>
                    <th>Stednavn</th>
                    <th>Kommune</th>
                    <th>Emne</th>
                    <th>Beskrivelse</th>
                </tr>
            </thead>
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
<div class="drlink">
	<p>
	Velkommen til! Tjek også <a href="kommuner/">kønsfordeling på kommuneniveau</a> samt <a href="https://osrm.findvej.dk/nomales/"><strong>ruteplanen, der nægter at køre på veje opkaldt efter mænd</strong></a>.
	</p>
	<p>
		Eksempler:
	</p>
	<ul>
		<li><a href="https://osrm.findvej.dk/nomales/?z=16&center=55.403470%2C10.385185&loc=55.401682%2C10.391736&loc=55.404484%2C10.394011&hl=da&alt=0">Odense</a></li>
		<li><a href="https://osrm.findvej.dk/nomales/?z=16&center=55.667353%2C12.533405&loc=55.665580%2C12.529242&loc=55.665435%2C12.530379&hl=da&alt=0">Carlsberg Byen</a></li>
        <li><a href="https://osrm.findvej.dk/nomales/?z=16&center=56.030328%2C12.600653&loc=56.030370%2C12.604601&loc=56.031740%2C12.605647&hl=da&alt=0">Helsingør</a></li>
	</ul>
</div>

    <div id="map" style="height: 700px; width: 100%; border: 1px solid black; z-index: 90; margin-top: 10px;"></div>

    <div id="betaboilerplate">
        <p>
            Vejnavne-projektet bliver løbende opdateret. Se også undersiden for <a href="kommuner/">kønsfordeling på kommuneniveau</a>.
        </p>
        <p>
            Datagrundlaget er veje i det frivillige kort-projekt <a href="https://www.openstreetmap.org/">OpenStreetMap</a>. Her har frivillige
            bladret <a href="https://github.com/PeterBrodersen/osmetymology/blob/main/Resources.md">bøger og websites</a> igennem for oplysninger om,
            hvad danske vejnavne er opkaldt efter. Der findes ingen centrale kilder i øvrigt om, hvad danske vejnavne er opkaldt efter, og der
            er en række <a href="https://github.com/PeterBrodersen/osmetymology/blob/main/Editing.md?tab=readme-ov-file#caveats">fælder</a> at være på
            udkig efter.
        </p>
        <p>
            Ikke alle steder er registreret endnu. Pt. er der oplysninger om over 40.000 danske veje (ca. en tredjedel af alle veje i Danmark), som er opkaldt efter over 14.000 forskellige emner.
        </p>
        <p>
            Projektet er udviklet af <a href="https://www.openstreetmap.org/user/Peter%20Brodersen">Peter Brodersen</a>. Du kan hente alle danske veje med oplysninger i en <a href="/data/navne.csv">kommasepareret fil</a> og i <a href="/data/navne.fgb">FlatGeobuf-format (for GIS-brugere)</a> (og link gerne tilbage til denne side, hvis du gør brug af data). Koden bag projektet er <a href="https://github.com/PeterBrodersen/osmetymology">tilgængeligt på GitHub</a>.
            Har du spørgsmålet til projektet, er du mere end velkommen til at <a href="mailto:peter@ter.dk">sende en mail</a>.
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
