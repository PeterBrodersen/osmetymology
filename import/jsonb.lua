-- For debugging
-- inspect = require('inspect')

-- The global variable "osm2pgsql" is used to talk to the main osm2pgsql code.
-- You can, for instance, get the version of osm2pgsql:
-- print('osm2pgsql version: ' .. osm2pgsql.version)

-- A place to store the SQL tables we will define shortly.
local tables = {}

-- Store relation tags for ways that need a second pass after relations are processed.
local associated_street_ways = {}
local enable_associated_street = os.getenv('OSM2PGSQL_ENABLE_ASSOCIATED_STREET_RELATIONS') == '1'

if enable_associated_street then
    print('associatedStreet support enabled via OSM2PGSQL_ENABLE_ASSOCIATED_STREET_RELATIONS=1')
end

tables.points = osm2pgsql.define_node_table('osm_points', {
    { column = 'name',                     type = 'text' },
    { column = 'name:etymology',           type = 'text' },
    { column = 'name:etymology:wikipedia', type = 'text' },
    { column = 'name:etymology:wikidata',  type = 'text' },
    { column = 'highway',                  type = 'text' },
    { column = 'tags',                     type = 'jsonb' },
    { column = 'geom',                     type = 'point' }, -- will be something like `GEOMETRY(Point, 4326)` in SQL
}, { schema = 'paris_osmetymology' })

tables.ways = osm2pgsql.define_way_table('osm_ways', {
    { column = 'name',                     type = 'text' },
    { column = 'name:etymology',           type = 'text' },
    { column = 'name:etymology:wikipedia', type = 'text' },
    { column = 'name:etymology:wikidata',  type = 'text' },
    { column = 'highway',                  type = 'text' },
    { column = 'tags',                     type = 'jsonb' },
    { column = 'geom',                     type = 'linestring' },
}, { schema = 'paris_osmetymology' })

tables.polygons = osm2pgsql.define_area_table('osm_polygons', {
    { column = 'name',                     type = 'text' },
    { column = 'name:etymology',           type = 'text' },
    { column = 'name:etymology:wikipedia', type = 'text' },
    { column = 'name:etymology:wikidata',  type = 'text' },
    { column = 'highway',                  type = 'text' },
    { column = 'tags',                     type = 'jsonb' },
    { column = 'geom',                     type = 'geometry' },
}, { schema = 'paris_osmetymology' })

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
    return tags.name == nil or
        (tags["name:etymology"] == nil and tags["name:etymology:wikipedia"] == nil and tags["name:etymology:wikidata"] == nil)
    -- return false -- always false, we want to process everything
end

-- Assume input object is not a point
function is_area(tags)
    return tags.building or tags.landuse or tags.amenity or tags.shop or tags["building:part"] or tags.boundary or
        tags.historic or tags.place or tags["area:highway"] or tags.leisure or tags.natural or tags.area == 'yes' or
        tags.highway == 'platform' or tags.railway == 'platform' or tags.man_made == 'bridge' or
        tags.man_made == 'storage_tank' or tags.man_made == 'pier' or tags.man_made == 'silo' or
        tags.man_made == 'chimney' or
        tags.aeroway == 'aerodrome'
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

    if enable_associated_street and osm2pgsql.stage == 2 and no_usable_data(object.tags) then
        local relation = associated_street_ways[object.id]
        if relation and object.tags.highway then
            print(
                "associatedStreet match: way id=" .. object.id ..
                ", way name=" .. tostring(object.tags.name) ..
                ", relation name=" .. tostring(relation.name)
            )

            local tags = object.tags

            -- Merge tags from relation
            tags.name = tags.name or relation.name
            tags["name:etymology"] = tags["name:etymology"] or relation["name:etymology"]
            tags["name:etymology:wikipedia"] = tags["name:etymology:wikipedia"] or
                relation["name:etymology:wikipedia"]
            tags["name:etymology:wikidata"] = tags["name:etymology:wikidata"] or
                relation["name:etymology:wikidata"]

            tables.ways:insert({
                name = tags.name,
                ["name:etymology"] = tags["name:etymology"],
                ["name:etymology:wikipedia"] = tags["name:etymology:wikipedia"],
                ["name:etymology:wikidata"] = tags["name:etymology:wikidata"],
                highway = tags.highway,
                tags = tags,
                geom = object:as_linestring()
            })
        end
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
    elseif enable_associated_street and object.tags.type == 'associatedStreet' then
        for _, member in ipairs(object.members) do
            if member.type == 'w' then
                associated_street_ways[member.ref] = associated_street_ways[member.ref] or {
                    name = object.tags.name,
                    ["name:etymology"] = object.tags["name:etymology"],
                    ["name:etymology:wikipedia"] = object.tags["name:etymology:wikipedia"],
                    ["name:etymology:wikidata"] = object.tags["name:etymology:wikidata"]
                }
            end
        end
    end
end

function osm2pgsql.select_relation_members(relation)
    if enable_associated_street and relation.tags.type == 'associatedStreet' then
        print(
            "select_relation_members: associatedStreet relation id=" .. relation.id ..
            ", name=" .. tostring(relation.tags.name)
        )

        return {
            ways = osm2pgsql.way_member_ids(relation)
        }
    end
end

function dump(o)
    if type(o) == 'table' then
        local s = '{ '
        for k, v in pairs(o) do
            if type(k) ~= 'number' then k = '"' .. k .. '"' end
            s = s .. '[' .. k .. '] = ' .. dump(v) .. ','
        end
        return s .. '} '
    else
        return tostring(o)
    end
end
