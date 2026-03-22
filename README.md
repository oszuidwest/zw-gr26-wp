# ZW-GR26

WordPress plugin for the 2026 municipal elections (*gemeenteraadsverkiezingen*) in West-Brabant, Netherlands. Built for [Streekomroep ZuidWest](https://www.zuidwestupdate.nl/).

> **Note:** This plugin is hard-coded against the [streekomroep-wp](https://github.com/oszuidwest/streekomroep-wp) theme and is not guaranteed to work with other themes.

## Requirements

- PHP 8.3+
- WordPress 6.8+
- [Advanced Custom Fields](https://www.advancedcustomfields.com/) (ACF)

Optional:
- Yoast SEO (for structured data / schema.org integration)
- Bunny CDN Stream library (for debate and explainer videos)
- imgproxy (for responsive image resizing, uses the same credentials as the streekomroep-wp theme)

## Shortcodes

All shortcodes are nested inside `[zw_gr26_pagina]` or `[zw_gr26_gemeente_pagina]` and only render within one of these wrappers.

### `[zw_gr26_pagina]`

Full-page wrapper with hero and inner shortcodes.

| Attribute | Default |
|-----------|---------|
| `titel` | `ZuidWest Kiest` |
| `achtergrond` | *(default background image URL)* |

### `[zw_gr26_livestream]`

Election night livestream player card.

| Attribute | Default |
|-----------|---------|
| `titel` | `De uitslagenavond` |
| `badge` | `Live op 18 maart` |
| `naam` | `ZuidWest Kiest: De Uitslag` |
| `datum_tekst` | `Woensdag 18 maart • Vanaf 21:00 • Live` |
| `url` | — |
| `thumbnail` | — |
| `tijd` | — |

### `[zw_gr26_debatten]` / `[zw_gr26_debat]`

Debate video grid (parent/child, videos via Bunny CDN).

**Parent:**

| Attribute | Default |
|-----------|---------|
| `titel` | `Debatten` |
| `bibliotheek` | — |

**Child:**

| Attribute | Default |
|-----------|---------|
| `naam` | — |
| `datum` | — |
| `kanaal` | — |
| `videoid` | — |
| `thumbnail` | — |

### `[zw_gr26_explainers]` / `[zw_gr26_explainer]`

Explainer video carousel (parent/child, videos via Bunny CDN).

**Parent:**

| Attribute | Default |
|-----------|---------|
| `titel` | `Explainers` |
| `bibliotheek` | — |

**Child:**

| Attribute | Default |
|-----------|---------|
| `naam` | — |
| `videoid` | — |
| `thumbnail` | — |

### `[zw_gr26_nieuws]`

News articles from a dossier taxonomy.

| Attribute | Default |
|-----------|---------|
| `titel` | `Laatste nieuws` |
| `dossier` | — (required) |
| `aantal` | `6` |
| `link` | — (auto-detected from dossier term) |
| `regio` | — |

### `[zw_gr26_podcast]`

Podcast promotion card with polaroid-stack cover art.

| Attribute | Default |
|-----------|---------|
| `titel` | `Podcast` |
| `naam` | `Het Fractiehuis` |
| `label` | — |
| `beschrijving` | — |
| `feed` | — (required) |
| `filter` | — |
| `spotify` | — |
| `apple` | — |

### `[zw_gr26_programmas]`

Party program links per municipality (data from the `gemeente_uitslag` CPT).

| Attribute | Default |
|-----------|---------|
| `titel` | `Verkiezingsprogramma's` |

### `[zw_gr26_resultaten]`

Election results with interactive modal, donut chart, and coalition builder.

| Attribute | Default |
|-----------|---------|
| `titel` | `Uitslagen` |

### `[zw_gr26_stemlocaties]`

Polling station locations (from waarismijnstemlokaal.nl API).

| Attribute | Default |
|-----------|---------|
| `titel` | `Stemlocaties` |

### `[zw_gr26_tekst]`

Free-form text/HTML block with optional title.

| Attribute | Default |
|-----------|---------|
| `titel` | — |

### `[zw_gr26_uitslag_tabel]`

Standalone election results table. Does not require a page wrapper shortcode.

| Attribute | Default |
|-----------|---------|
| `gemeente` | — (required, municipality slug) |

## Gemeente subpage shortcodes

These shortcodes are used to build per-municipality subpages. They are nested inside `[zw_gr26_gemeente_pagina]` and share the same child shortcodes as the main page (debatten, nieuws, podcast, livestream, tekst, stemlocaties).

### `[zw_gr26_gemeente_pagina]`

Municipality subpage wrapper with hero and gemeente context. Validates the municipality against the `gemeente_uitslag` CPT.

| Attribute | Default |
|-----------|---------|
| `gemeente` | — (required, municipality slug) |
| `titel` | *(auto-detected from municipality name)* |
| `achtergrond` | *(default background image URL)* |

When active, existing shortcodes adapt automatically:
- `[zw_gr26_debatten]` renders a spotlight layout (main debate large, others as compact sidebar cards)
- `[zw_gr26_nieuws]` auto-fills `regio` from the active municipality
- `[zw_gr26_stemlocaties]` shows only locations for the active municipality

### `[zw_gr26_gemeente_explainer]`

Single explainer video with blue background, pencil pattern, and text panel.

| Attribute | Default |
|-----------|---------|
| `titel` | `Explainer` |
| `videoid` | — (required) |
| `naam` | — |
| `bibliotheek` | — |
| `thumbnail` | — |
| `tekst` | *(default explanatory text)* |

### `[zw_gr26_gemeente_programmas]`

Simple party program list without dropdown (single municipality).

| Attribute | Default |
|-----------|---------|
| `titel` | `Verkiezingsprogramma's` |

### `[zw_gr26_gemeente_resultaten]`

Inline election results with donut chart, results table, and coalition builder. Title is generated dynamically as "Uitslag {gemeente naam}". No configurable attributes.

## REST API

### `GET /wp-json/zwgr26/v1/uitslagen`

Public endpoint (no authentication required) that returns election results for all municipalities as JSON. Used for external integrations.

## Admin

### Election results meta box

The `gemeente_uitslag` edit screens include a read-only meta box that fetches live election results from `platform.zuidwestkiest.nl`. This allows editors to compare the external data source with the locally stored results.

## Installation

Download the latest release zip from the [Releases](../../releases) page and install it via **Plugins > Add New > Upload Plugin** in WordPress.

## Development

```bash
# PHP dependencies (PHPCS, PHPStan)
composer install

# JS/CSS dependencies (Biome)
npm install
```

### Linting

```bash
# PHP
vendor/bin/phpcs                # WordPress coding standards
vendor/bin/phpcbf               # Auto-fix
vendor/bin/phpstan analyse      # Static analysis (level 6)

# JS/CSS
npm run lint                    # Biome check
npm run lint:fix                # Biome auto-fix
```

## License

GPL-2.0-or-later
