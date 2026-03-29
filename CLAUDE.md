# Great Lake Cleaners — Project Context

## Identity

**Organization:** Great Lake Cleaners  
**Tagline:** The lake starts here.  
**Mission:** Regular cleanups of Guelph's local waterways — by foot and paddle — that flow into the Great Lakes system via the Grand River and Lake Erie.  
**Location:** Guelph, Ontario, Canada  
**Stage:** Pre-incorporation, Phase 1 (personal/family effort, year one)

## Online Presence

| Asset | Value |
|---|---|
| Primary domain | greatlakecleaners.ca |
| Domain registrar | CanSpace |
| Instagram | @greatlakecleaners |
| Facebook | (to claim) |
| .org domain | Not registered yet — revisit at incorporation |

## Instagram Bio (final)

```
The lake starts here. 🌊
Cleaning Guelph's rivers and shores by
foot and paddle — because what enters
our waterways reaches the Great Lakes.

🔗 greatlakecleaners.ca
```

## First Pinned Post (approved)

```
The lake starts here. 🌊

Guelph sits at the confluence of the Speed and Eramosa Rivers. From here,
water flows into the Grand River, through southwestern Ontario, and into
Lake Erie — one of the five Great Lakes that hold a fifth of the world's
fresh surface water.

What gets left on a riverbank in Guelph doesn't stay in Guelph.

Great Lake Cleaners is a Guelph-based group doing regular cleanups along
the Speed River, Eramosa River, and Hanlon Creek corridors — by foot on
the shores and by paddle on the water. We locate and remove what shouldn't
be there before it travels downstream.

Small local effort. Watershed-scale impact.

We're just getting started. Follow along. 🛶

#GreatLakeCleaners #SpeedRiver #EramosoRiver #HanlonCreek #GuelphOntario
#GrandRiver #LakeErie #GreatLakes #RiverCleanup #CleanupGuelph
#Watershed #FreshWater
```

## Operating Corridors

- Speed River
- Eramosa River
- Hanlon Creek
- Guelph Lake area (secondary)

## Watershed Chain

Speed/Eramosa Rivers → Grand River → Lake Erie → Great Lakes system

## Cleanup Methodology

- Shore cleanups on foot (dog walks = regular informal outings)
- Paddle cleanups on water
- Scrap metal and deposit-return items collected for revenue to fund operations

## Phase Roadmap

| Phase | Timeline | Focus |
|---|---|---|
| 1 — Individual | Now–~1 year | Personal/family cleanups, documentation, relationship building |
| 2 — Informal group | 6–18 months | Unincorporated association, small donor base, city/GRCA relationships |
| 3 — Nonprofit incorporation | 18–36 months | Provincial incorporation under ONCA, CRA charitable registration |

## Key Relationships to Build

- **City of Guelph** — Clean and Green program (April), parks permits
- **GRCA** — Site access permits for conservation areas, Grand River Conservation Foundation (grants)
- **OPIRG Guelph** — Speed River Project, existing volunteer network (collaborate not compete)
- **Wellington Water Watchers** — 2Rivers Festival partnership history with OPIRG

## Revenue Streams (informal phase)

- Scrap metal → Benmet Steel & Metal, 415 Elizabeth St (519-763-1209) or B&F Scrap (226-566-1630)
- Deposit return containers → Beer Store (verify nearest open location at thebeerstore.ca/where-to-return-empties)
- Eventually: grants (GRCA Foundation, TD Friends of the Environment, Ontario Trillium Foundation)

## Legal / Incorporation Notes

- Incorporate provincially under ONCA ($155, ServiceOntario) when ready
- CRA charitable registration takes 6–12 months — file early
- Environmental restoration qualifies under "other purposes beneficial to the community"
- Purposes clause is the most scrutinized part of CRA application — draft carefully

---

## Technical Infrastructure

### Hosting
- **VPS:** OVHcloud Canada or WebSavers (Canadian data residency, green power)
- **Stack:** Ubuntu 24, Apache, MySQL, PHP 8.2, Python 3
- **WordPress** for public site and cleanup event management

### Domains
- Registered at CanSpace
- Primary: greatlakecleaners.ca
- SSL: Let's Encrypt (free, auto-renews via certbot)

### WordPress Plugin: `great-lake-cleaners`

Self-contained plugin. Drop into `wp-content/plugins/`, activate alongside ACF (free).

**Custom Post Type:** `cleanup_event`  
**ACF Fields:** date, site_name, gps_lat, gps_lon, volunteers, hours, bags, weight_kg,
species_planted, meters_bank_cleared, notable_finds, wildlife_obs

**Shortcodes:**
| Shortcode | Output |
|---|---|
| `[glc_stats]` | Cumulative totals banner |
| `[glc_map]` | Leaflet/OpenStreetMap of all cleanup sites |
| `[glc_archive]` | Card list of recent cleanups |
| `[glc_archive limit="5"]` | Limited archive |

**CSV Importer:** Tools → Import Cleanups CSV (accepts cleanups.csv format)

**Function/constant prefix:** `glc_` / `GLC_`  
**CSS class prefix:** `.glc-`

### Python Scripts

#### `cleanup_report.py`
Reads `cleanups/cleanups.csv`, generates per-event Instagram outputs:
- `reports/YYYY-MM-DD_sitename/card.png` — 1080×1080 stats card
- `reports/YYYY-MM-DD_sitename/caption.txt` — caption with hashtags

```bash
python cleanup_report.py              # latest event
python cleanup_report.py 2026-04-05   # specific date
```

**Config constants (top of file):**
```python
ORG_NAME   = "Great Lake Cleaners"
IG_HANDLE  = "@greatlakecleaners"
```

#### `cleanups/cleanups.csv`
Master event log. Columns:
`date, site_name, gps_lat, gps_lon, volunteers, hours, bags, weight_kg,
species_planted, meters_bank_cleared, notable_finds, wildlife_obs, notes,
photo_folder, best_photo`

### Outing Tracker
`great-lake-cleaners-outing-tracker.xlsx` — Google Sheets compatible.

**Daily Log tab:** one row per dog-walk outing  
- Garbage (bags, kg), Recycling (cans, bottles, kg), Deposit return (beer cans, bottles, auto-value), Scrap metal (aluminum, steel, copper kg), Notable finds  
- Total weight and deposit $ auto-calculated

**Weekly Summary tab:** manual weekly rollup → feeds into cleanups.csv for formal events

**Integration point:** Weekly Summary totals → cleanups.csv → WordPress plugin → public site

---

## Files Produced This Session

| File | Purpose |
|---|---|
| `great-lake-cleaners-plugin.zip` | WordPress plugin, drop into wp-content/plugins/ |
| `cleanup_report.py` | Instagram card + caption generator |
| `cleanups/cleanups.csv` | Master cleanup event log (seed data) |
| `great-lake-cleaners-outing-tracker.xlsx` | Weekly dog-walk outing tracker |

---

## Next Steps

- [ ] Provision VPS (OVHcloud Canada or WebSavers)
- [ ] Point greatlakecleaners.ca nameservers to VPS
- [ ] Install LAMP stack + WordPress
- [ ] Install ACF plugin + Great Lake Cleaners plugin
- [ ] Choose WordPress theme
- [ ] Build `archive-cleanup_event.php` and `single-cleanup_event.php` theme templates
- [ ] Import cleanups.csv seed data
- [ ] Post Instagram bio and first pinned post
- [ ] Get a digital fish scale (~$15–20) for accurate weight logging
- [ ] First cleanup outing logged in outing tracker
- [ ] Connect with OPIRG Speed River Project coordinator
- [ ] Register for City of Guelph Clean and Green (April)
