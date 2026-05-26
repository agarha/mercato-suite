# Canadian geographic taxonomy for the Gigsii tenant (tenant_id=2).
# All coordinates from Statistics Canada / Wikipedia (CC-BY-SA, public-record).
# Population figures are 2021 census or latest official estimate.
#
# Hierarchy:
#   country = Canada                              (1 row)
#   province/territory                            (13 rows)
#   cities (top ~5 per province by population)    (~55 rows)
#   neighborhoods (Toronto only, ~20 rows)        (~20 rows)
#
# Total: ~90 rows. Idempotent via UNIQUE (tenant_id, slug).

TENANT = 2  # Gigsii

# (slug, code, name, lat, lng, radius_km, population, country_code)
country = ('canada', 'CA', 'Canada', 56.1304, -106.3468, 5000, 40000000, 'CA')

provinces = [
    # province_slug, code, name, lat, lng, radius_km, population
    ('ontario',                'ON', 'Ontario',                   51.2538,  -85.3232,  900, 15600000),
    ('quebec',                 'QC', 'Quebec',                    52.9399,  -73.5491,  900,  8800000),
    ('british-columbia',       'BC', 'British Columbia',          53.7267, -127.6476,  900,  5200000),
    ('alberta',                'AB', 'Alberta',                   53.9333, -116.5765,  700,  4600000),
    ('manitoba',               'MB', 'Manitoba',                  53.7609,  -98.8139,  700,  1400000),
    ('saskatchewan',           'SK', 'Saskatchewan',              52.9399, -106.4509,  700,  1200000),
    ('nova-scotia',            'NS', 'Nova Scotia',               44.6820,  -63.7443,  300,  1000000),
    ('new-brunswick',          'NB', 'New Brunswick',             46.5653,  -66.4619,  300,   790000),
    ('newfoundland-labrador',  'NL', 'Newfoundland and Labrador', 53.1355,  -57.6604,  800,   525000),
    ('prince-edward-island',   'PE', 'Prince Edward Island',      46.5107,  -63.4168,  100,   165000),
    ('yukon',                  'YT', 'Yukon',                     64.2823, -135.0000,  900,    44000),
    ('northwest-territories',  'NT', 'Northwest Territories',     61.2181, -113.5083, 1200,    45000),
    ('nunavut',                'NU', 'Nunavut',                   70.2998,  -83.1076, 2000,    39000),
]

# Cities: (province_slug, city_slug, city_name, lat, lng, radius_km, population)
cities = [
    # Ontario
    ('ontario', 'toronto',         'Toronto',         43.6532,  -79.3832, 35, 2930000),
    ('ontario', 'ottawa',          'Ottawa',          45.4215,  -75.6972, 30, 1017000),
    ('ontario', 'mississauga',     'Mississauga',     43.5890,  -79.6441, 25,  720000),
    ('ontario', 'brampton',        'Brampton',        43.7315,  -79.7624, 25,  656000),
    ('ontario', 'hamilton',        'Hamilton',        43.2557,  -79.8711, 25,  570000),
    ('ontario', 'london',          'London',          42.9849,  -81.2453, 20,  423000),
    ('ontario', 'markham',         'Markham',         43.8561,  -79.3370, 20,  338000),
    ('ontario', 'vaughan',         'Vaughan',         43.8361,  -79.4983, 20,  324000),
    ('ontario', 'kitchener',       'Kitchener',       43.4516,  -80.4925, 20,  256000),
    ('ontario', 'windsor',         'Windsor',         42.3149,  -83.0364, 20,  229000),
    ('ontario', 'oakville',        'Oakville',        43.4675,  -79.6877, 15,  213000),
    ('ontario', 'burlington',      'Burlington',      43.3255,  -79.7990, 15,  186000),
    ('ontario', 'oshawa',          'Oshawa',          43.8971,  -78.8658, 15,  175000),
    # Quebec
    ('quebec', 'montreal',         'Montreal',        45.5017,  -73.5673, 30, 1763000),
    ('quebec', 'quebec-city',      'Quebec City',     46.8139,  -71.2080, 25,  542000),
    ('quebec', 'laval',            'Laval',           45.6066,  -73.7124, 20,  438000),
    ('quebec', 'gatineau',         'Gatineau',        45.4765,  -75.7013, 20,  291000),
    ('quebec', 'longueuil',        'Longueuil',       45.5371,  -73.5119, 15,  254000),
    ('quebec', 'sherbrooke',       'Sherbrooke',      45.4042,  -71.8929, 15,  173000),
    ('quebec', 'saguenay',         'Saguenay',        48.4275,  -71.0686, 20,  144000),
    ('quebec', 'trois-rivieres',   'Trois-Rivieres',  46.3432,  -72.5432, 15,  139000),
    # British Columbia
    ('british-columbia', 'vancouver',     'Vancouver',     49.2827, -123.1207, 30,  675000),
    ('british-columbia', 'surrey',        'Surrey',        49.1913, -122.8490, 25,  568000),
    ('british-columbia', 'burnaby',       'Burnaby',       49.2488, -122.9805, 15,  249000),
    ('british-columbia', 'richmond',      'Richmond',      49.1666, -123.1336, 15,  209000),
    ('british-columbia', 'victoria',      'Victoria',      48.4284, -123.3656, 15,   92000),
    ('british-columbia', 'kelowna',       'Kelowna',       49.8880, -119.4960, 20,  144000),
    ('british-columbia', 'abbotsford',    'Abbotsford',    49.0504, -122.3045, 15,  153000),
    # Alberta
    ('alberta', 'calgary',         'Calgary',         51.0447, -114.0719, 30, 1306000),
    ('alberta', 'edmonton',        'Edmonton',        53.5461, -113.4938, 30, 1010000),
    ('alberta', 'red-deer',        'Red Deer',        52.2681, -113.8112, 15,  100000),
    ('alberta', 'lethbridge',      'Lethbridge',      49.6956, -112.8451, 15,   99000),
    # Manitoba
    ('manitoba', 'winnipeg',       'Winnipeg',        49.8951,  -97.1384, 30,  749000),
    ('manitoba', 'brandon',        'Brandon',         49.8489,  -99.9501, 15,   51000),
    # Saskatchewan
    ('saskatchewan', 'saskatoon',  'Saskatoon',       52.1332, -106.6700, 20,  273000),
    ('saskatchewan', 'regina',     'Regina',          50.4452, -104.6189, 20,  226000),
    # Nova Scotia
    ('nova-scotia', 'halifax',     'Halifax',         44.6488,  -63.5752, 30,  440000),
    # New Brunswick
    ('new-brunswick', 'moncton',   'Moncton',         46.0878,  -64.7782, 15,   79000),
    ('new-brunswick', 'saint-john','Saint John',      45.2733,  -66.0633, 15,   69000),
    ('new-brunswick', 'fredericton','Fredericton',    45.9636,  -66.6431, 15,   63000),
    # Newfoundland
    ('newfoundland-labrador', 'st-johns', "St. John's", 47.5615, -52.7126, 20, 110000),
    # PEI
    ('prince-edward-island', 'charlottetown', 'Charlottetown', 46.2382, -63.1311, 10, 38000),
    # Yukon
    ('yukon', 'whitehorse',        'Whitehorse',      60.7212, -135.0568, 15,   28000),
    # NWT
    ('northwest-territories', 'yellowknife', 'Yellowknife', 62.4540, -114.3718, 15, 20000),
    # Nunavut
    ('nunavut', 'iqaluit',         'Iqaluit',         63.7467,  -68.5170, 10,    8000),
]

# Toronto neighborhoods (the flagship Gigsii market). 20 of the most-recognised
# neighbourhoods with rough geographic centroids and walkable radii.
toronto_hoods = [
    # slug, name, lat, lng, radius_km
    ('downtown-toronto',    'Downtown Toronto',    43.6500, -79.3833, 3),
    ('financial-district',  'Financial District',  43.6483, -79.3815, 2),
    ('entertainment-district','Entertainment District', 43.6450, -79.3900, 2),
    ('annex',               'The Annex',           43.6700, -79.4050, 2),
    ('yorkville',           'Yorkville',           43.6711, -79.3897, 2),
    ('kensington-market',   'Kensington Market',   43.6547, -79.4023, 2),
    ('liberty-village',     'Liberty Village',     43.6385, -79.4225, 2),
    ('king-west',           'King West',           43.6440, -79.4000, 2),
    ('queen-west',          'Queen West',          43.6470, -79.4180, 2),
    ('leslieville',         'Leslieville',         43.6646, -79.3392, 2),
    ('riverside',           'Riverside',           43.6594, -79.3502, 2),
    ('the-beaches',         'The Beaches',         43.6716, -79.2941, 3),
    ('high-park',           'High Park',           43.6465, -79.4637, 3),
    ('roncesvalles',        'Roncesvalles',        43.6481, -79.4486, 2),
    ('the-junction',        'The Junction',        43.6660, -79.4690, 2),
    ('etobicoke',           'Etobicoke',           43.6205, -79.5132, 8),
    ('scarborough',         'Scarborough',         43.7764, -79.2318, 12),
    ('north-york',          'North York',          43.7615, -79.4111, 8),
    ('east-york',           'East York',           43.6911, -79.3279, 4),
    ('midtown-toronto',     'Midtown Toronto',     43.7065, -79.3984, 4),
]

# Emit SQL. Each level references the parent via a subquery on slug+tenant
# so the script is order-independent and re-runnable (INSERT IGNORE).
print("-- Canadian region taxonomy for the Gigsii tenant (tenant_id={}).".format(TENANT))
print("-- Generated by tools/sql/gen_geo_seed.py; do not edit by hand.")
print("-- Re-runnable: INSERT IGNORE skips rows whose slug already exists.")
print()
print("SET @tid := {};".format(TENANT))
print()

# Country
print("INSERT IGNORE INTO wp_mercato_geo_regions (tenant_id, parent_id, type, code, name, slug, country_code, latitude, longitude, radius_km, population, sort_order)")
print("VALUES (@tid, NULL, 'country', '{}', '{}', '{}', '{}', {}, {}, {}, {}, 0);".format(
    country[1], country[2], country[0], country[7], country[3], country[4], country[5], country[6]))
print()

# Provinces
print("-- Provinces and territories")
for i, (slug, code, name, lat, lng, r, pop) in enumerate(provinces):
    print("INSERT IGNORE INTO wp_mercato_geo_regions (tenant_id, parent_id, type, code, name, slug, country_code, latitude, longitude, radius_km, population, sort_order)")
    print("SELECT @tid, region_id, 'province', '{}', '{}', '{}', 'CA', {}, {}, {}, {}, {} FROM wp_mercato_geo_regions WHERE tenant_id=@tid AND slug='canada';".format(
        code, name.replace("'", "''"), slug, lat, lng, r, pop, i))
print()

# Cities
print("-- Cities")
for i, (province_slug, city_slug, city_name, lat, lng, r, pop) in enumerate(cities):
    print("INSERT IGNORE INTO wp_mercato_geo_regions (tenant_id, parent_id, type, code, name, slug, country_code, latitude, longitude, radius_km, population, sort_order)")
    print("SELECT @tid, region_id, 'city', NULL, '{}', '{}', 'CA', {}, {}, {}, {}, {} FROM wp_mercato_geo_regions WHERE tenant_id=@tid AND slug='{}';".format(
        city_name.replace("'", "''"), city_slug, lat, lng, r, pop, i, province_slug))
print()

# Toronto neighborhoods
print("-- Toronto neighborhoods (flagship Gigsii market)")
for i, (slug, name, lat, lng, r) in enumerate(toronto_hoods):
    print("INSERT IGNORE INTO wp_mercato_geo_regions (tenant_id, parent_id, type, code, name, slug, country_code, latitude, longitude, radius_km, population, sort_order)")
    print("SELECT @tid, region_id, 'neighborhood', NULL, '{}', '{}', 'CA', {}, {}, {}, NULL, {} FROM wp_mercato_geo_regions WHERE tenant_id=@tid AND slug='toronto';".format(
        name.replace("'", "''"), slug, lat, lng, r, i))
print()

print("-- Verify")
print("SELECT type, COUNT(*) AS rows FROM wp_mercato_geo_regions WHERE tenant_id=@tid GROUP BY type ORDER BY FIELD(type, 'country','province','city','neighborhood');")
