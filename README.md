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

Full page wrapper:

```
[zw_gr26_pagina titel="ZuidWest Kiest" ondertitel="Alles over de gemeenteraadsverkiezingen" achtergrond="https://example.com/bg.jpg"]
  ... other shortcodes here ...
[/zw_gr26_pagina]
```

Livestream embed:

```
[zw_gr26_livestream titel="De uitslagenavond" badge="Live op 18 maart" naam="ZuidWest Kiest: De Uitslag" datum_tekst="Woensdag 18 maart &bull; Vanaf 21:00 &bull; Live" url="https://example.com/stream" thumbnail="https://example.com/thumb.jpg" tijd="21:00"]
```

Debates (parent/child, videos via Bunny CDN):

```
[zw_gr26_debatten titel="Debatten" bibliotheek="12345"]
  [zw_gr26_debat naam="Debat Roosendaal" datum="5 maart" kanaal="ZuidWest TV 1" videoid="abc-123" thumbnail="https://example.com/thumb.jpg"]
  [zw_gr26_debat naam="Debat Bergen op Zoom" datum="6 maart" kanaal="ZuidWest TV 2" videoid="def-456"]
[/zw_gr26_debatten]
```

Explainer videos (parent/child, videos via Bunny CDN):

```
[zw_gr26_explainers titel="Explainers" bibliotheek="12345"]
  [zw_gr26_explainer naam="Hoe werkt stemmen?" videoid="abc-123"]
  [zw_gr26_explainer naam="Wat doet de gemeenteraad?" videoid="def-456" thumbnail="https://example.com/thumb.jpg"]
[/zw_gr26_explainers]
```

News from a dossier taxonomy:

```
[zw_gr26_nieuws titel="Laatste nieuws" dossier="gemeenteraadsverkiezingen" aantal="6" link="https://example.com/dossier" regio="west-brabant"]
```

Party programs (data from the gemeente_uitslag CPT):

```
[zw_gr26_programmas titel="Verkiezingsprogramma's"]
```

Election results with interactive modal:

```
[zw_gr26_resultaten titel="Uitslagen per gemeente"]
```

Polling station locations (from waarismijnstemlokaal.nl API):

```
[zw_gr26_stemlocaties titel="Stemlocaties"]
```

Text section with optional title:

```
[zw_gr26_tekst titel="Over de verkiezingen"]
  Vrije tekst en <strong>HTML</strong> hier.
[/zw_gr26_tekst]
```

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
