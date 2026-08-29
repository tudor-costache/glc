#!/usr/bin/env python3
"""
prepare_corridors_geojson.py

Fetches river/creek line geometry for GLC's cleanup corridors, primarily
from the Ontario Hydro Network (OHN) Watercourse dataset (Ontario GeoHub /
MNRF, ArcGIS REST service, Open Government Licence - Ontario) -- the same
authoritative source as the OHN watercourse map that inspired this
feature. OHN's official naming turns out to be sparse (most segments,
even along clearly-named creeks, carry no name at all), so any corridor
OHN can't resolve falls back to OpenStreetMap's waterway data (Overpass
API, (c) OpenStreetMap contributors, ODbL) before giving up. The site's
map already credits OpenStreetMap for its base tiles, so no separate
attribution line is needed for that fallback.

Not a live dependency: this is a one-off/occasional offline fetch, same
philosophy as prepare_wildlife_asset.py. Re-run by hand when a corridor is
added, renamed, or a "pin-only" corridor below should be re-checked.

For each corridor, queries OHN for an exact OFFICIAL_NAME_LABEL match
within an expanding bounding box around a known cleanup-site GPS anchor,
then (if nothing matched) queries OSM the same way. A corridor with no
match anywhere is simply left out of the file; the map's PHP side falls
back to placing that corridor's pin from cleanup GPS data with no line
drawn.

Usage:
    python prepare_corridors_geojson.py
    python prepare_corridors_geojson.py speed-river eramosa-river

    Passing one or more slugs re-fetches only those, patching them into the
    existing output file instead of re-querying (and re-rate-limiting
    against) every corridor.

Requires: requests
    pip install requests
"""

import json
import os
import sys
import time

import requests

OHN_QUERY_URL = (
    "https://ws.lioservices.lrc.gov.on.ca/arcgis2/rest/services/"
    "LIO_OPEN_DATA/LIO_Open01/MapServer/26/query"
)

OVERPASS_URL = "https://overpass-api.de/api/interpreter"
OVERPASS_UA = "GLC-corridor-map-prep/1.0 (contact: info@greatlakecleaners.ca)"

ATTRIBUTION = (
    "River corridor lines: Ontario Hydro Network (OHN) - Watercourse, "
    "Ontario Ministry of Natural Resources and Forestry, "
    "Open Government Licence - Ontario; some corridors via OpenStreetMap."
)

OUTPUT_PATH = "plugin-dev/great-lake-cleaners/assets/corridors.geojson"

# One row per corridor with logged (or expected) cleanups. `anchor` is a
# real cleanup GPS point on that corridor (lat, lon). `ohn_name` is only
# needed when it differs from the site's own display `name` (e.g. OHN
# spells Hanlon Creek with an apostrophe-s).
CORRIDORS = [
    # Speed River and Eramosa River are OSM-only: OHN only tags their rural
    # source stretches (near Guelph Lake / Rockwood) with the official name,
    # missing the dense urban core through Guelph -- where GLC's cleanups
    # (and the worst pollution) actually concentrate. OSM's relations cover
    # the full course, source to confluence with the Grand.
    {"slug": "speed-river",     "name": "Speed River",     "osm_only": True, "anchor": (43.52466347, -80.26224696)},
    {"slug": "eramosa-river",   "name": "Eramosa River",   "osm_only": True, "anchor": (43.55649531, -80.21754011)},
    {"slug": "hanlon-creek",    "name": "Hanlon Creek",    "ohn_name": "Hanlon's Creek", "anchor": (43.52546453, -80.28993831)},
    {"slug": "laurel-creek",    "name": "Laurel Creek",    "ohn_name": "Laurel Creek",   "anchor": (43.46635913, -80.52648531)},
    {"slug": "big-creek",       "name": "Big Creek",       "ohn_name": "Big Creek",      "anchor": (42.59477805, -80.48558723)},
    {"slug": "avon-river",      "name": "Avon River",      "ohn_name": "Avon River",     "anchor": (43.37721825, -80.96406212)},
    {"slug": "black-ash-creek", "name": "Black Ash Creek", "ohn_name": "Black Ash Creek","anchor": (44.51088817, -80.22293079)},
    {"slug": "conestogo-river", "name": "Conestogo River", "ohn_name": "Conestogo River","anchor": (43.53505672, -80.57187288)},
    {"slug": "duchesnay-creek", "name": "Duchesnay Creek", "ohn_name": "Duchesnay Creek","anchor": (46.33723058, -79.51120872)},
    {"slug": "bayfield-river",  "name": "Bayfield River",  "ohn_name": "Bayfield River", "anchor": (43.56557318, -81.70655923)},
    {"slug": "maitland-river",  "name": "Maitland River",  "ohn_name": "Maitland River", "anchor": (43.75167451, -81.71491385)},
    {"slug": "hadati-creek",    "name": "Hadati Creek",    "ohn_name": "Hadati Creek",   "anchor": (43.57013212, -80.22539733)},
    {"slug": "ausable-river",   "name": "Ausable River",   "ohn_name": "Ausable River",  "anchor": (43.30225020, -81.76808356)},
    # Nine Mile River: OHN has no match at all here. In OSM it's ~23 short
    # "stream" ways (no relation grouping them), so the normal anchor +
    # escalating-radius search was returning an inconsistent partial subset
    # run to run -- pinned to a fixed bbox instead, like Grand River. The
    # stretch that reaches the actual cleanup site (~-81.716 lon) turned out
    # to be tagged as a `natural=water`+`water=river` polygon rather than a
    # `waterway=*` centerline -- see query_osm()'s docstring. Bbox kept wide
    # of the known extent so a bigger future edit still gets picked up.
    {"slug": "nine-mile-river", "name": "Nine Mile River", "osm_only": True, "bbox": (-81.80, 43.80, -81.45, 44.00)},
]

# Grand River: context line only -- no cleanups are logged directly on it
# (GLC's corridors are its tributaries), so it gets a fixed bounding box
# covering its Ontario course rather than a cleanup-site anchor. OHN only
# tags a ~1km stretch near Fergus with this name (confirmed by a test
# fetch) -- nowhere near "the complete flow into the Lakes" the user
# wants, so this one skips OHN and goes straight to OSM, whose "Grand
# River" relation is a complete, well-connected 49-way route.
GRAND_RIVER = {
    "slug": "grand-river",
    "name": "Grand River",
    "osm_only": True,
    "bbox": (-80.55, 42.85, -79.95, 44.25),  # Dundalk headwaters to the Lake Erie mouth
}

# Degrees of lat/lon to search around an anchor, tried in order until a
# match is found.
SEARCH_RADII = [0.06, 0.15, 0.35]

ROUND_DP = 6


def bbox_around(lat, lon, radius):
    return (lon - radius, lat - radius, lon + radius, lat + radius)


def query_ohn_all(where, bbox):
    """Runs the query, paginating via resultOffset if the server truncates."""
    features = []
    offset = 0
    while True:
        params = {
            "where": where,
            "geometry": ",".join(str(v) for v in bbox),
            "geometryType": "esriGeometryEnvelope",
            "inSR": 4326,
            "spatialRel": "esriSpatialRelIntersects",
            "outFields": "OFFICIAL_NAME_LABEL,OGF_ID",
            "outSR": 4326,
            "f": "geojson",
            "returnGeometry": "true",
            "resultOffset": offset,
            "resultRecordCount": 500,
        }
        resp = requests.get(OHN_QUERY_URL, params=params, timeout=60)
        resp.raise_for_status()
        data = resp.json()
        feats = data.get("features", [])
        features.extend(feats)
        exceeded = data.get("exceededTransferLimit") or data.get("properties", {}).get("exceededTransferLimit")
        if not exceeded or not feats:
            break
        offset += len(feats)
    return features


def query_osm(name, bbox):
    """bbox = (xmin, ymin, xmax, ymax) -- converted to Overpass's south,west,north,east.

    Searches both tagging styles OSM uses for rivers: a centerline
    (`waterway=river`/`stream`, common for narrower creeks and tributaries)
    and a river-area polygon (`natural=water` + `water=river`, common for
    wider stretches -- this is how the "missing" middle of Nine Mile River
    turned out to be mapped; the centerline-only search silently missed it
    entirely since it isn't tagged `waterway=*` at all).
    """
    xmin, ymin, xmax, ymax = bbox
    esc = name.replace('"', '\\"')
    bbox_str = "{s},{w},{n_},{e}".format(s=ymin, w=xmin, n_=ymax, e=xmax)
    query = (
        '[out:json][timeout:90];'
        '(way["waterway"]["name"="{n}"]({b});'
        'relation["waterway"]["name"="{n}"]({b});'
        'way["natural"="water"]["water"="river"]["name"="{n}"]({b});'
        'relation["natural"="water"]["water"="river"]["name"="{n}"]({b}););'
        "out geom;"
    ).format(n=esc, b=bbox_str)
    headers = {"User-Agent": OVERPASS_UA}
    resp = requests.post(OVERPASS_URL, data={"data": query}, headers=headers, timeout=120)
    resp.raise_for_status()
    data = resp.json()

    features = []
    for el in data.get("elements", []):
        if el["type"] == "way":
            ways = [el]
        elif el["type"] == "relation":
            ways = [m for m in el.get("members", []) if m.get("type") == "way" and m.get("geometry")]
        else:
            continue
        for way in ways:
            coords = [[pt["lon"], pt["lat"]] for pt in way.get("geometry", []) if pt]
            if len(coords) >= 2:
                features.append({
                    "type": "Feature",
                    "geometry": {"type": "LineString", "coordinates": coords},
                    "properties": {},
                })
    return features


def round_coords(geom):
    def rnd(pt):
        return [round(pt[0], ROUND_DP), round(pt[1], ROUND_DP)]

    if geom["type"] == "LineString":
        geom["coordinates"] = [rnd(p) for p in geom["coordinates"]]
    elif geom["type"] == "MultiLineString":
        geom["coordinates"] = [[rnd(p) for p in line] for line in geom["coordinates"]]
    return geom


def total_points(feats):
    return sum(len(f["geometry"]["coordinates"]) for f in feats)


def first_match(bbox_attempts, query_fn, source_label):
    """Runs query_fn(bbox) over each bbox attempt in turn, returning the
    first non-empty result. query_fn takes just a bbox and raises
    requests.RequestException on failure."""
    for label, bbox in bbox_attempts:
        try:
            feats = query_fn(bbox)
        except requests.RequestException as exc:
            print("  ! {} query failed at {}: {}".format(source_label, label, exc))
            continue
        if feats:
            print("  {}: {} segment(s), {} pts, at {}".format(source_label, len(feats), total_points(feats), label))
            return feats
    return []


def fetch_corridor(corridor):
    if "bbox" in corridor:
        ohn_attempts = osm_attempts = [("fixed bbox", corridor["bbox"])]
    else:
        lat, lon = corridor["anchor"]
        ohn_attempts = [("{} deg".format(r), bbox_around(lat, lon, r)) for r in SEARCH_RADII]
        # OSM searches widest-first: many rivers are digitized as several
        # disconnected ways rather than one relation (Nine Mile River,
        # Ausable River), so the *first* radius that returns *anything* is
        # often an incomplete fragment, not the full river. A larger bbox
        # isn't meaningfully riskier for a name-filtered query, and
        # Overpass returns full way geometry regardless of bbox size, so
        # search wide-to-narrow and keep the first success; narrower boxes
        # are only a fallback if the wide query times out under load.
        osm_attempts = [("{} deg".format(r), bbox_around(lat, lon, r)) for r in sorted(SEARCH_RADII, reverse=True)]

    # Always try both sources (unless osm_only skips a known-empty OHN) and
    # keep whichever is richer by total coordinate points, rather than
    # stopping at the first source that returns *anything*. OHN returning a
    # single 2-point stub while OSM has the whole river mapped in detail is
    # a real, recurring case (Speed River, Eramosa River, Big Creek) --
    # "found a match" isn't the same as "found a good match".
    ohn_feats = []
    if not corridor.get("osm_only"):
        where = "OFFICIAL_NAME_LABEL='{}'".format(corridor.get("ohn_name", corridor["name"]).replace("'", "''"))
        ohn_feats = first_match(ohn_attempts, lambda bbox: query_ohn_all(where, bbox), "OHN")

    osm_name = corridor.get("osm_name", corridor["name"])
    osm_feats = first_match(osm_attempts, lambda bbox: query_osm(osm_name, bbox), "OSM")
    time.sleep(0.5)  # Overpass fair-use pacing between corridors

    if not ohn_feats and not osm_feats:
        return []
    if total_points(osm_feats) > total_points(ohn_feats):
        print("  using OSM (richer)")
        return osm_feats
    print("  using OHN (richer or OSM empty)")
    return ohn_feats


def main():
    only_slugs = set(sys.argv[1:]) or None

    all_features = []
    if only_slugs and os.path.exists(OUTPUT_PATH):
        with open(OUTPUT_PATH, encoding="utf-8") as f:
            existing = json.load(f)
        all_features = [feat for feat in existing.get("features", []) if feat["properties"]["slug"] not in only_slugs]

    matched, missing = [], []
    corridors_to_fetch = [c for c in CORRIDORS + [GRAND_RIVER] if not only_slugs or c["slug"] in only_slugs]

    for corridor in corridors_to_fetch:
        print(corridor["name"] + "...")
        feats = fetch_corridor(corridor)
        if not feats:
            missing.append(corridor["name"])
            print("  no match found - pin-only fallback will apply")
            continue
        matched.append(corridor["name"])
        for feat in feats:
            feat["geometry"] = round_coords(feat["geometry"])
            feat["properties"] = {"slug": corridor["slug"], "name": corridor["name"]}
            all_features.append(feat)
        time.sleep(0.3)  # be polite to the government server

    out = {
        "type": "FeatureCollection",
        "attribution": ATTRIBUTION,
        "features": all_features,
    }

    with open(OUTPUT_PATH, "w", encoding="utf-8") as f:
        json.dump(out, f, ensure_ascii=False, separators=(",", ":"))

    print("")
    print("Wrote {} ({} features)".format(OUTPUT_PATH, len(all_features)))
    print("Matched ({}): {}".format(len(matched), ", ".join(matched) or "none"))
    print("Pin-only, no line match ({}): {}".format(len(missing), ", ".join(missing) or "none"))


if __name__ == "__main__":
    main()
