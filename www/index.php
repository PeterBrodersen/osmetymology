<!DOCTYPE html>
<html>

<head>
    <title>
        What are places in Paris named after?
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
    <h1>What are streets and places in Paris named after?</h1>

    <div style="clear: both;">
    </div>

    <div id="userinput">
        <div id="placename"><input required autofocus id="namefind" placeholder="Search street name" accesskey="f"> <span id="copylink"><a href="#">[copy link 🔗]</a></span></div>
        <div id="itemname"><input required autofocus id="itemfind" placeholder="Search topic" accesskey="t"></div>
    </div>

    <div id="result">
    </div>

    <template id="tabletemplate">
        <table class="resulttable">
            <thead>
                <tr class="tableheader">
                    <th>Map</th>
                    <th>Type</th>
                    <th>Name</th>
                    <th>Arrondissement</th>
                    <th>Topic</th>
                    <th>Description</th>
                </tr>
            </thead>
        </table>
    </template>

    <div id="maplinks">
        <div>
            <a href="#" id="getposition">[➹ find places near you]</a> <span class="location-loader" style="display:none;"></span>
        </div>
        <div>
            <a href="#" id="showplacesinmapview">[list all places in map view]</a><br>
        </div>
        <div>
            <a href="#" id="copylinktomap">[copy link to map view 🔗]</a><br>
        </div>

    </div>
    <div class="drlink">
        <p>
            Also check out:
	</p>
	<ul>
		<li><a href="arrondissements/">Gender distribution per arrondissement</a></li>
		<li><a href="https://osrm.findvej.dk/paris/">Route planner that avoids roads named after men</a> (<a href="https://osrm.findvej.dk/paris/?z=13&center=48.842722%2C2.372320&loc=48.823939%2C2.330303&loc=48.819228%2C2.329402&hl=fr&alt=0">Example</a>)</li>
        </ul>
    </div>

    <div id="map" style="height: 700px; width: 100%; border: 1px solid black; z-index: 90; margin-top: 10px;"></div>

    <div id="betaboilerplate">
        <p>
            The data source is the voluntary mapping project <a href="https://www.openstreetmap.org/">OpenStreetMap</a>.
        </p>
        <p>
            Not all places are registered yet. Currently, there are about 5,000 Parisian roads with information about their etymology, which are named after over 3,000 different topics.
        </p>
        <p>
            The project is developed by <a href="https://www.openstreetmap.org/user/Peter%20Brodersen">Peter Brodersen</a>. You can download all Parisian roads with information in a <a href="/data/noms.csv">comma-separated file</a> and in <a href="/data/noms.fgb">FlatGeobuf format (for GIS users)</a>
            The code for the project is <a href="https://github.com/PeterBrodersen/osmetymology/tree/paris">available on GitHub</a>. If you have questions about the project, you are
            more than welcome to <a href="mailto:peter@ter.dk">send an email</a>.
        </p>

        <p class="copyright">
            Map data is sourced from <a href="https://www.openstreetmap.org/">OpenStreetMap</a> and is licensed under the <a href="https://www.openstreetmap.org/copyright">Open Data Commons Open Database License (ODbL)</a>. Metadata is sourced from <a href="https://www.wikidata.org/">Wikidata</a> and is licensed under the <a href="https://creativecommons.org/publicdomain/zero/1.0/deed.da">Creative Commons CC0 License</a>.
        </p>

        <p class="stats">
            Content in the database:<br>
            Number of places: <span id="totalroads"></span><br>
            Number of uniquely named places: <span id="uniquenamedroads"></span><br>
            Number of unique topics, places are named after: <span id="uniqueetymologywikidata"></span><br>
            Date of dataset: <span id="importfiletime"></span>
        </p>
    </div>
</body>

</html>
