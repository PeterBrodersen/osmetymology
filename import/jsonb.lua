-- This config example file is released into the Public Domain.

-- This is a very simple Lua config for the Flex output not intended for
-- real-world use. Use it do understand the basic principles of the
-- configuration. After reading and understanding this, have a look at
-- "geometries.lua".

-- For debugging
-- inspect = require('inspect')

-- The global variable "osm2pgsql" is used to talk to the main osm2pgsql code.
-- You can, for instance, get the version of osm2pgsql:
-- print('osm2pgsql version: ' .. osm2pgsql.version)

-- A place to store the SQL tables we will define shortly.
local tables = {}

-- Create a new table called "pois" with the given columns. When running in
-- "create" mode, this will do the `CREATE TABLE`, when running in "append"
-- mode, this will only declare the table for use.
--
-- This is a "node table", it can only contain data derived from nodes and will
-- contain a "node_id" column (SQL type INT8) as first column. When running in
-- "append" mode, osm2pgsql will automatically update this table using the node
-- ids.
tables.points = osm2pgsql.define_node_table('osm_points', {
    { column = 'name', type = 'text' },
    { column = 'name:etymology', type = 'text' },
    { column = 'name:etymology:wikipedia', type = 'text' },
    { column = 'name:etymology:wikidata', type = 'text' },
    { column = 'highway', type = 'text' },
    { column = 'tags', type = 'jsonb' },
    { column = 'geom', type = 'point' }, -- will be something like `GEOMETRY(Point, 4326)` in SQL
}, { schema = 'osmetymology' } )

-- A special table for restaurants to demonstrate that we can have any tables
-- with any columns we want.
-- tables.restaurants = osm2pgsql.define_node_table('restaurants', {
--     { column = 'name',    type = 'text' },
--     { column = 'cuisine', type = 'text' },
--     { column = 'geom',    type = 'point' },
-- })

-- This is a "way table", it can only contain data derived from ways and will
-- contain a "way_id" column. When running in "append" mode, osm2pgsql will
-- automatically update this table using the way ids.
tables.ways = osm2pgsql.define_way_table('osm_ways', {
    { column = 'name', type = 'text' },
    { column = 'name:etymology', type = 'text' },
    { column = 'name:etymology:wikipedia', type = 'text' },
    { column = 'name:etymology:wikidata', type = 'text' },
    { column = 'highway', type = 'text' },
    { column = 'tags', type = 'jsonb' },
    { column = 'geom', type = 'linestring' },
}, { schema = 'osmetymology' } )

-- This is an "area table", it can contain data derived from ways or relations
-- and will contain an "area_id" column. Way ids will be stored "as is" in the
-- "area_id" column, for relations the negative id will be stored. When
-- running in "append" mode, osm2pgsql will automatically update this table
-- using the way/relation ids.
tables.polygons = osm2pgsql.define_area_table('osm_polygons', {
    { column = 'name', type = 'text' },
    { column = 'name:etymology', type = 'text' },
    { column = 'name:etymology:wikipedia', type = 'text' },
    { column = 'name:etymology:wikidata', type = 'text' },
    { column = 'highway', type = 'text' },
    { column = 'tags', type = 'jsonb' },
    -- The type of the `geom` column is `geometry`, because we need to store
    -- polygons AND multipolygons
    { column = 'geom', type = 'geometry' },
}, { schema = 'osmetymology' } )

-- Specific routes table
tables.routes = osm2pgsql.define_relation_table('osm_routes', {
    { column = 'name', type = 'text' },
    { column = 'name:etymology', type = 'text' },
    { column = 'name:etymology:wikipedia', type = 'text' },
    { column = 'name:etymology:wikidata', type = 'text' },
    { column = 'highway', type = 'text' },
    { column = 'route', type = 'text' },
    { column = 'routetype', type = 'text' },
    { column = 'tags', type = 'jsonb' },
    -- The type of the `geom` column is `geometry`, because we need to store
    -- polygons AND multipolygons
    -- Should we change this to line/multilinedir?
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

-- Called for every node in the input. The `object` argument contains all the
-- attributes of the node like `id`, `version`, etc. as well as all tags as a
-- Lua table (`object.tags`).

function osm2pgsql.process_node(object)
    --  Uncomment next line to look at the object data:
    --  print(inspect(object))

    if clean_tags(object.tags) then
        return
    end

    tables.points:add_row({
        name = object.tags.name,
        ["name:etymology"] = object.tags["name:etymology"],
        ["name:etymology:wikipedia"] = object.tags["name:etymology:wikipedia"],
        ["name:etymology:wikidata"] = object.tags["name:etymology:wikidata"],
        highway = object.tags.highway,
        tags = object.tags
})
end

-- Called for every way in the input. The `object` argument contains the same
-- information as with nodes and additionally a boolean `is_closed` flag and
-- the list of node IDs referenced by the way (`object.nodes`).
function osm2pgsql.process_way(object)
    -- print(dump(object.tags))
    
    if clean_tags(object.tags) then
        return
    end
    
    -- Very simple check to decide whether a way is a polygon or not, in a
    -- real stylesheet we'd have to also look at the tags...
    if object.is_closed then
        tables.polygons:add_row({
            name = object.tags.name,
            ["name:etymology"] = object.tags["name:etymology"],
            ["name:etymology:wikipedia"] = object.tags["name:etymology:wikipedia"],
            ["name:etymology:wikidata"] = object.tags["name:etymology:wikidata"],
            highway = object.tags.highway,
            tags = object.tags,
            geom = { create = 'area' }
        })
    else
        tables.ways:add_row({
            name = object.tags.name,
            ["name:etymology"] = object.tags["name:etymology"],
            ["name:etymology:wikipedia"] = object.tags["name:etymology:wikipedia"],
            ["name:etymology:wikidata"] = object.tags["name:etymology:wikidata"],
            highway = object.tags.highway,
            tags = object.tags
        })
    end
end


function osm2pgsql.process_relation(object)
    if clean_tags(object.tags) then
        return
    end

    -- Store multipolygons and boundaries as polygons
    if object.tags.type == 'multipolygon' or
       object.tags.type == 'boundary' then
         tables.polygons:add_row({
            name = object.tags.name,
            ["name:etymology"] = object.tags["name:etymology"],
            ["name:etymology:wikipedia"] = object.tags["name:etymology:wikipedia"],
            ["name:etymology:wikidata"] = object.tags["name:etymology:wikidata"],
            tags = object.tags,
            geom = { create = 'area' }
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
