-- For debugging
-- inspect = require('inspect')

-- The global variable "osm2pgsql" is used to talk to the main osm2pgsql code.
-- You can, for instance, get the version of osm2pgsql:
-- print('osm2pgsql version: ' .. osm2pgsql.version)

-- A place to store the SQL tables we will define shortly.
local tables = {}

tables.points = osm2pgsql.define_node_table('osm_points', {
    { column = 'name', type = 'text' },
    { column = 'name:etymology', type = 'text' },
    { column = 'name:etymology:wikipedia', type = 'text' },
    { column = 'name:etymology:wikidata', type = 'text' },
    { column = 'highway', type = 'text' },
    { column = 'tags', type = 'jsonb' },
    { column = 'geom', type = 'point' }, -- will be something like `GEOMETRY(Point, 4326)` in SQL
}, { schema = 'osmetymology' } )

tables.ways = osm2pgsql.define_way_table('osm_ways', {
    { column = 'name', type = 'text' },
    { column = 'name:etymology', type = 'text' },
    { column = 'name:etymology:wikipedia', type = 'text' },
    { column = 'name:etymology:wikidata', type = 'text' },
    { column = 'highway', type = 'text' },
    { column = 'tags', type = 'jsonb' },
    { column = 'geom', type = 'linestring' },
}, { schema = 'osmetymology' } )

tables.polygons = osm2pgsql.define_area_table('osm_polygons', {
    { column = 'name', type = 'text' },
    { column = 'name:etymology', type = 'text' },
    { column = 'name:etymology:wikipedia', type = 'text' },
    { column = 'name:etymology:wikidata', type = 'text' },
    { column = 'highway', type = 'text' },
    { column = 'tags', type = 'jsonb' },
    { column = 'geom', type = 'geometry' },
}, { schema = 'osmetymology' } )

-- Debug output: Show definition of tables
for name, dtable in pairs(tables) do
    print("\ntable '" .. name .. "':")
    print("  name='" .. dtable:name() .. "'")
--    print("  columns=" .. inspect(dtable:columns()))
end

-- Helper function to remove some of the tags we usually are not interested in.
-- Returns true if there are no tags left.
function clean_tags(tags)
    tags.odbl = nil
    tags.created_by = nil
    tags.source = nil
    tags['source:ref'] = nil

    return next(tags) == nil
end

-- We are only interested in objects with name and some kind of etymology
function no_usable_data(tags)
    -- return tags.name == nil or ( tags["name:etymology"] == nil and tags["name:etymology:wikipedia"] == nil and tags["name:etymology:wikidata"] == nil )
    return false -- always false, we want to process everything

end

-- Assume input object is not a point
function is_area(tags)
    return tags.building or tags.landuse or tags.amenity or tags.shop or tags["building:part"] or tags.boundary or tags.historic or tags.place or tags["area:highway"] or tags.leisure or tags.natural or tags.area == 'yes' or tags.highway == 'platform' or tags.railway == 'platform' or tags.man_made == 'bridge' or tags.man_made == 'storage_tank' or tags.man_made == 'pier' or tags.man_made == 'silo' or tags.man_made == 'chimney' or tags.aeroway == 'aerodrome'
end

function osm2pgsql.process_node(object)
    if no_usable_data(object.tags) then
        return
    end

    tables.points:insert({
        name = object.tags.name,
        ["name:etymology"] = object.tags["name:etymology"],
        ["name:etymology:wikipedia"] = object.tags["name:etymology:wikipedia"],
        ["name:etymology:wikidata"] = object.tags["name:etymology:wikidata"],
        highway = object.tags.highway,
        tags = object.tags,
        geom = object:as_point()
    })
end

function osm2pgsql.process_way(object)
    -- print(dump(object.tags))

    if no_usable_data(object.tags) then
        return
    end

    -- TODO: Support panes with different Z indexes before loading areas
    -- if false then
    if is_area(object.tags) then
        tables.polygons:insert({
            name = object.tags.name,
            ["name:etymology"] = object.tags["name:etymology"],
            ["name:etymology:wikipedia"] = object.tags["name:etymology:wikipedia"],
            ["name:etymology:wikidata"] = object.tags["name:etymology:wikidata"],
            highway = object.tags.highway,
            tags = object.tags,
            geom = object:as_polygon()
        })
    else
        tables.ways:insert({
            name = object.tags.name,
            ["name:etymology"] = object.tags["name:etymology"],
            ["name:etymology:wikipedia"] = object.tags["name:etymology:wikipedia"],
            ["name:etymology:wikidata"] = object.tags["name:etymology:wikidata"],
            highway = object.tags.highway,
            tags = object.tags,
            geom = object:as_linestring()
        })
    end
end


function osm2pgsql.process_relation(object)

    if no_usable_data(object.tags) then
        return
    end

    -- Store multipolygons and boundaries as polygons
    if object.tags.type == 'multipolygon' or
       object.tags.type == 'boundary' then
         tables.polygons:insert({
            name = object.tags.name,
            ["name:etymology"] = object.tags["name:etymology"],
            ["name:etymology:wikipedia"] = object.tags["name:etymology:wikipedia"],
            ["name:etymology:wikidata"] = object.tags["name:etymology:wikidata"],
            tags = object.tags,
            geom = object:as_multipolygon()
        })
    end

end

function dump(o)
    if type(o) == 'table' then
       local s = '{ '
       for k,v in pairs(o) do
          if type(k) ~= 'number' then k = '"'..k..'"' end
          s = s .. '['..k..'] = ' .. dump(v) .. ','
       end
       return s .. '} '
    else
       return tostring(o)
    end
 end
