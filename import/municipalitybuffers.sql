-- Update Copenhagen Municipality with larger buffer to support bridges
-- This should be done in a more generic way for more municipalities, perhaps several non-connecting municipalities at once?
WITH newcph AS (
	select navn, kode, ST_Transform(ST_Buffer(ST_Transform(wkb_geometry, 25832), 200), 4326) as buffergeom 
	from osmetymology.municipalities m 
	where kode = '0101'
), withoutcph AS (
	SELECT ST_UNION(m.wkb_geometry) AS supergeom
	FROM osmetymology.municipalities m
	WHERE kode != '0101'
)
UPDATE osmetymology.municipalities m SET wkb_geometry = ST_Difference(newcph.buffergeom, supergeom)
from newcph, withoutcph
WHERE m.kode = '0101'