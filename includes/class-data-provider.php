<?php
/**
 * Data layer for WordPress posts and election results from CPT.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides dossier posts via WP_Query and election results from the gemeente_uitslag CPT.
 */
class Data_Provider {

	/**
	 * Cached election results loaded from CPT.
	 *
	 * @var array|null
	 */
	private static ?array $election_results = null;

	/**
	 * Cached municipality list (all statuses).
	 *
	 * @var array|null
	 */
	private static ?array $all_municipalities = null;

	/**
	 * Cached gemeente subpage URLs.
	 *
	 * @var array|null
	 */
	private static ?array $gemeente_pages = null;

	/**
	 * Cached main page URL.
	 *
	 * @var string|false|null Null = not fetched, false = not found.
	 */
	private static string|false|null $main_page_url = null;

	/**
	 * Fetches the latest dossier posts.
	 *
	 * When dossier_slug is empty, posts are queried without a dossier filter
	 * (useful for regio-only queries on gemeente subpages).
	 *
	 * @param string $dossier_slug Taxonomy slug for the dossier. Empty for no dossier filter.
	 * @param int    $count        Maximum number of posts to return.
	 * @param string $regio_slug   Optional regio taxonomy slug to filter by.
	 * @return array List of article data arrays.
	 */
	public function get_dossier_posts( string $dossier_slug, int $count = 6, string $regio_slug = '' ): array {
		$tax_query = [];

		if ( '' !== $dossier_slug ) {
			if ( ! taxonomy_exists( 'dossier' ) ) {
				return [];
			}
			$tax_query[] = [
				'taxonomy' => 'dossier',
				'field'    => 'slug',
				'terms'    => $dossier_slug,
			];
		}

		if ( '' !== $regio_slug && taxonomy_exists( 'regio' ) ) {
			if ( ! empty( $tax_query ) ) {
				$tax_query['relation'] = 'AND';
			}
			$tax_query[] = [
				'taxonomy' => 'regio',
				'field'    => 'slug',
				'terms'    => $regio_slug,
			];
		}

		if ( empty( $tax_query ) ) {
			return [];
		}

		$query = new \WP_Query(
			[
				'post_type'      => 'post',
				'posts_per_page' => $count,
				'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		$items = [];
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();

				$regio = '';
				if ( taxonomy_exists( 'regio' ) ) {
					$regios = get_the_terms( $post_id, 'regio' );
					if ( ! empty( $regios ) && ! is_wp_error( $regios ) ) {
						$regio = $regios[0]->name;
					}
				}

				$thumbnail = get_the_post_thumbnail_url( $post_id, 'full' );

				$items[] = [
					'url'        => get_permalink( $post_id ),
					'titel'      => get_the_title( $post_id ),
					'datum'      => get_the_date( 'j F', $post_id ),
					'datum_iso'  => get_the_date( 'c', $post_id ),
					'afbeelding' => $thumbnail ? $thumbnail : '',
					'regio'      => $regio,
					'auteur'     => get_the_author(),
					'auteur_url' => get_author_posts_url( (int) get_the_author_meta( 'ID' ) ),
				];
			}
			wp_reset_postdata();
		}

		return $items;
	}

	/**
	 * Loads election results from published gemeente_uitslag posts.
	 *
	 * Returns only municipalities with status 'publish'.
	 * Partijen are sorted by zetels 2026 descending.
	 *
	 * @return array Election results keyed by municipality slug.
	 */
	public function get_election_results(): array {
		if ( null !== self::$election_results ) {
			return self::$election_results;
		}

		if ( ! post_type_exists( 'gemeente_uitslag' ) ) {
			self::$election_results = [];
			return self::$election_results;
		}

		$posts = get_posts(
			[
				'post_type'   => 'gemeente_uitslag',
				'post_status' => 'publish',
				'numberposts' => 20,
			]
		);

		$results = [];
		foreach ( $posts as $post ) {
			$slug          = $post->post_name;
			$totaal_zetels = (int) get_field( 'totaal_zetels', $post->ID );
			$opkomst_2022  = get_field( 'opkomst_2022', $post->ID );
			$opkomst_2026  = get_field( 'opkomst_2026', $post->ID );
			$repeater      = get_field( 'partijen', $post->ID );

			$partijen = [];
			if ( is_array( $repeater ) ) {
				foreach ( $repeater as $row ) {
					$zetels_2022 = $row['zetels_2022'];
					$partijen[]  = [
						'naam'          => $row['partij_naam'] ?? '',
						'naam_kort'     => $row['naam_kort'] ?? '',
						'kleur'         => ! empty( $row['kleur'] ) ? $row['kleur'] : '#90a4ae',
						'zetels_2022'   => '' !== $zetels_2022 && null !== $zetels_2022 ? (int) $zetels_2022 : null,
						'zetels'        => (int) ( $row['zetels_2026'] ?? 0 ),
						'programma_url' => ! empty( $row['programma_url'] ) ? $row['programma_url'] : '',
					];
				}

				// Check whether any party has 2026 seats.
				$has_2026 = false;
				foreach ( $partijen as $p ) {
					if ( $p['zetels'] > 0 ) {
						$has_2026 = true;
						break;
					}
				}

				if ( $has_2026 ) {
					// Split into parties with seats and parties that lost all seats.
					$with_seats    = [];
					$without_seats = [];
					foreach ( $partijen as $p ) {
						if ( $p['zetels'] > 0 ) {
							$with_seats[] = $p;
						} elseif ( null !== $p['zetels_2022'] && $p['zetels_2022'] > 0 ) {
							$without_seats[] = $p;
						}
					}

					// Sort parties with seats descending by 2026 seats.
					usort(
						$with_seats,
						static function ( array $a, array $b ): int {
							return $b['zetels'] <=> $a['zetels'];
						}
					);

					// Sort disappeared parties descending by 2022 seats.
					usort(
						$without_seats,
						static function ( array $a, array $b ): int {
							return $b['zetels_2022'] <=> $a['zetels_2022'];
						}
					);

					$partijen = array_merge( $with_seats, $without_seats );
				} else {
					// Keep parties with 2022 seats, sorted descending.
					$partijen = array_values(
						array_filter(
							$partijen,
							static function ( array $p ): bool {
								return null !== $p['zetels_2022'] && $p['zetels_2022'] > 0;
							}
						)
					);
					usort(
						$partijen,
						static function ( array $a, array $b ): int {
							return $b['zetels_2022'] <=> $a['zetels_2022'];
						}
					);
				}
			}

			$results[ $slug ] = [
				'naam'          => $post->post_title,
				'totaal_zetels' => $totaal_zetels,
				'has_2026'      => $has_2026 ?? false,
				'opkomst_2022'  => '' !== $opkomst_2022 && null !== $opkomst_2022 ? (float) $opkomst_2022 : null,
				'opkomst_2026'  => '' !== $opkomst_2026 && null !== $opkomst_2026 ? (float) $opkomst_2026 : null,
				'partijen'      => $partijen,
			];
		}

		self::$election_results = $results;

		return self::$election_results;
	}

	/**
	 * Fetches polling station locations from waarismijnstemlokaal.nl API.
	 *
	 * Results are cached as a WP transient for 12 hours.
	 *
	 * @return array<string, array> gemeente_slug => array of location records.
	 */
	public function get_stemlocaties(): array {
		$cache_key = 'zwgr26_stemlocaties_v3';
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$municipalities = $this->get_all_municipalities();
		$result         = [];

		foreach ( $municipalities as $slug => $naam ) {
			$gemeente_data = $this->fetch_stemlocaties_for( $naam );
			if ( ! empty( $gemeente_data['locaties'] ) ) {
				$result[ $slug ] = $gemeente_data;
			}
		}

		set_transient( $cache_key, $result, 12 * HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Fetches and transforms polling stations for a single municipality.
	 *
	 * @param string $gemeente Municipality name.
	 * @return array{contact: string, website: string, locaties: array}
	 */
	private function fetch_stemlocaties_for( string $gemeente ): array {
		$empty = [
			'contact'  => '',
			'website'  => '',
			'locaties' => [],
		];

		$url = add_query_arg(
			[
				'resource_id' => 'ff973715-2f66-4421-a860-10cc0b8d6295',
				'filters'     => wp_json_encode( [ 'Gemeente' => $gemeente ] ),
				'limit'       => 200,
			],
			'https://data.waarismijnstemlokaal.nl/api/3/action/datastore_search'
		);

		$response = wp_remote_get(
			$url,
			[
				'timeout' => 15,
				'headers' => [ 'Accept' => 'application/json' ],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $empty;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['result']['records'] ) || ! is_array( $body['result']['records'] ) ) {
			return $empty;
		}

		$locaties = [];
		$contact  = '';
		$website  = '';

		foreach ( $body['result']['records'] as $rec ) {
			// Capture municipality-level contact info from first record.
			if ( empty( $contact ) && ! empty( $rec['Contactgegevens gemeente'] ) ) {
				$contact = $rec['Contactgegevens gemeente'];
			}
			if ( empty( $website ) && ! empty( $rec['Verkiezingswebsite gemeente'] ) ) {
				$website = $rec['Verkiezingswebsite gemeente'];
			}

			$adres_parts = array_filter(
				[
					trim(
						( $rec['Straatnaam'] ?? '' ) . ' ' .
						( $rec['Huisnummer'] ?? '' ) .
						( $rec['Huisletter'] ?? '' ) .
						( $rec['Huisnummertoevoeging'] ?? '' )
					),
					trim( ( $rec['Postcode'] ?? '' ) . ' ' . ( $rec['Plaats'] ?? '' ) ),
				]
			);

			$locaties[] = [
				'naam'               => $rec['Naam stembureau'] ?? '',
				'type'               => $rec['Type stembureau'] ?? '',
				'adres'              => implode( ', ', $adres_parts ),
				'open'               => $this->parse_time( $rec['Openingstijd'] ?? '' ),
				'sluit'              => $this->parse_time( $rec['Sluitingstijd'] ?? '' ),
				'lat'                => ! empty( $rec['Latitude'] ) ? (float) $rec['Latitude'] : null,
				'lon'                => ! empty( $rec['Longitude'] ) ? (float) $rec['Longitude'] : null,
				'toegankelijk'       => $this->normalize_feature( $rec['Toegankelijk voor mensen met een lichamelijke beperking'] ?? '' ),
				'ov_halte'           => $this->normalize_feature( $rec['Toegankelijke ov-halte'] ?? '' ),
				'toilet'             => $this->normalize_feature( $rec['Toilet'] ?? '' ),
				'geleidelijnen'      => $this->normalize_feature( $rec['Geleidelijnen'] ?? '' ),
				'audio'              => $this->normalize_feature( $rec['Stemmal met audio-ondersteuning'] ?? '' ),
				'braille'            => $this->normalize_feature( $rec['Kandidatenlijst in braille'] ?? '' ),
				'grote_letters'      => $this->normalize_feature( $rec['Kandidatenlijst met grote letters'] ?? '' ),
				'gebarentolk'        => $this->normalize_feature( $rec['Gebarentolk (NGT)'] ?? '' ),
				'gebarentalig_lid'   => $this->normalize_feature( $rec['Gebarentalig stembureaulid (NGT)'] ?? '' ),
				'slechthorenden'     => $this->normalize_feature( $rec['Akoestiek geschikt voor slechthorenden'] ?? '' ),
				'prikkelarm'         => $this->normalize_feature( $rec['Prikkelarm'] ?? '' ),
				'extra_toegankelijk' => trim( $rec['Extra toegankelijkheidsinformatie'] ?? '' ),
			];
		}

		return [
			'contact'  => $contact,
			'website'  => $website,
			'locaties' => $locaties,
		];
	}

	/**
	 * Extracts HH:mm time from a datetime string like '2026-03-18T07:30:00'.
	 *
	 * @param string $datetime Raw datetime value.
	 * @return string Time in HH:mm format, or original string if parsing fails.
	 */
	private function parse_time( string $datetime ): string {
		if ( '' === $datetime ) {
			return '';
		}

		$pos = strpos( $datetime, 'T' );
		if ( false !== $pos ) {
			return substr( $datetime, $pos + 1, 5 );
		}

		return $datetime;
	}

	/**
	 * Normalizes a feature field: empty string becomes empty (unavailable),
	 * any other value is kept as-is (e.g. "ja", "ja, toegankelijk toilet", "op afstand").
	 *
	 * @param string $value Raw field value.
	 * @return string Empty if unavailable, otherwise the trimmed original value.
	 */
	private function normalize_feature( string $value ): string {
		return trim( $value );
	}

	/**
	 * Gets program URLs per municipality from the gemeente_uitslag CPT.
	 *
	 * Returns all municipalities (publish + draft) that have at least one
	 * party with a programma_url, sorted alphabetically by municipality name.
	 *
	 * @return array<int, array{naam: string, partijen: array<int, array{naam: string, url: string}>}>
	 */
	public function get_programmas(): array {
		if ( ! post_type_exists( 'gemeente_uitslag' ) ) {
			return [];
		}

		$posts = get_posts(
			[
				'post_type'   => 'gemeente_uitslag',
				'post_status' => [ 'publish', 'draft' ],
				'numberposts' => 20,
				'orderby'     => 'title',
				'order'       => 'ASC',
			]
		);

		$gemeenten = [];
		foreach ( $posts as $post ) {
			$repeater = get_field( 'partijen', $post->ID );
			if ( ! is_array( $repeater ) ) {
				continue;
			}

			$partijen = [];
			foreach ( $repeater as $row ) {
				$partijen[] = [
					'naam' => $row['partij_naam'] ?? '',
					'url'  => ! empty( $row['programma_url'] ) ? $row['programma_url'] : '',
				];
			}

			if ( ! empty( $partijen ) ) {
				$gemeenten[] = [
					'naam'     => $post->post_title,
					'partijen' => $partijen,
				];
			}
		}

		return $gemeenten;
	}

	/**
	 * Gets program URLs for a single municipality by slug.
	 *
	 * @param string $slug Municipality post slug.
	 * @return array<int, array{naam: string, url: string}> Party program list.
	 */
	public function get_programmas_for( string $slug ): array {
		if ( ! post_type_exists( 'gemeente_uitslag' ) ) {
			return [];
		}

		$posts = get_posts(
			[
				'post_type'   => 'gemeente_uitslag',
				'post_status' => [ 'publish', 'draft' ],
				'name'        => $slug,
				'numberposts' => 1,
			]
		);

		if ( empty( $posts ) ) {
			return [];
		}

		$repeater = get_field( 'partijen', $posts[0]->ID );
		if ( ! is_array( $repeater ) ) {
			return [];
		}

		$partijen = [];
		foreach ( $repeater as $row ) {
			$partijen[] = [
				'naam' => $row['partij_naam'] ?? '',
				'url'  => ! empty( $row['programma_url'] ) ? $row['programma_url'] : '',
			];
		}

		return $partijen;
	}

	/**
	 * Fetches unique episode cover images from a podcast RSS feed.
	 *
	 * Results are cached as a WP transient for 1 hour.
	 *
	 * @param string $feed_url     Podcast RSS feed URL.
	 * @param string $title_filter Optional substring to match in episode titles.
	 * @return string[] List of unique cover image URLs.
	 */
	public function get_podcast_covers( string $feed_url, string $title_filter = '' ): array {
		$hash      = md5( $feed_url . $title_filter );
		$cache_key = 'zwgr26_podcast_' . $hash;
		$cd_key    = 'zwgr26_podcastcd_' . $hash;
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// Cooldown active — skip fetch, return empty without hammering the feed.
		if ( false !== get_transient( $cd_key ) ) {
			return [];
		}

		$response = wp_remote_get(
			$feed_url,
			[
				'timeout' => 10,
				'headers' => [ 'Accept' => 'application/rss+xml, application/xml, text/xml' ],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $cd_key, 1, 5 * MINUTE_IN_SECONDS );
			return [];
		}

		$body = wp_remote_retrieve_body( $response );

		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NOCDATA );
		libxml_clear_errors();

		if ( false === $xml ) {
			set_transient( $cd_key, 1, 5 * MINUTE_IN_SECONDS );
			return [];
		}

		$covers     = [];
		$namespaces = $xml->getNamespaces( true );
		$ns         = $namespaces['itunes'] ?? 'http://www.itunes.com/dtds/podcast-1.0.dtd';

		foreach ( $xml->channel->item as $item ) {
			if ( '' !== $title_filter ) {
				$title = (string) $item->title;
				if ( false === stripos( $title, $title_filter ) ) {
					continue;
				}
			}

			$itunes_children = $item->children( $ns );

			if ( ! isset( $itunes_children->image ) ) {
				continue;
			}

			$href = (string) $itunes_children->image->attributes()['href'];

			if ( '' !== $href ) {
				$covers[] = $href;
			}
		}

		$covers = array_values( array_unique( $covers ) );

		if ( ! empty( $covers ) ) {
			set_transient( $cache_key, $covers, HOUR_IN_SECONDS );
		} else {
			set_transient( $cd_key, 1, 5 * MINUTE_IN_SECONDS );
		}

		return $covers;
	}

	/**
	 * Finds WordPress pages that use [zw_gr26_gemeente_pagina] and returns
	 * their gemeente slug, display name, and permalink.
	 *
	 * Results are cached for the duration of the request.
	 *
	 * @return array<int, array{slug: string, naam: string, url: string}>
	 */
	public function get_gemeente_pages(): array {
		if ( null !== self::$gemeente_pages ) {
			return self::$gemeente_pages;
		}

		$pages = get_posts(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'numberposts' => 50,
				's'           => 'zw_gr26_gemeente_pagina',
			]
		);

		$municipalities = $this->get_all_municipalities();
		$result         = [];

		foreach ( $pages as $page ) {
			if ( ! has_shortcode( $page->post_content, 'zw_gr26_gemeente_pagina' ) ) {
				continue;
			}

			// Extract the gemeente attribute from the shortcode.
			if ( ! preg_match( '/\[zw_gr26_gemeente_pagina\s[^\]]*gemeente=["\']?([^"\'\]\s]+)/', $page->post_content, $matches ) ) {
				continue;
			}

			$slug = sanitize_title( $matches[1] );
			$naam = $municipalities[ $slug ] ?? '';
			if ( ! $naam ) {
				continue;
			}

			$result[] = [
				'slug' => $slug,
				'naam' => $naam,
				'url'  => get_permalink( $page->ID ),
			];
		}

		// Sort alphabetically by name.
		usort(
			$result,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['naam'], $b['naam'] );
			}
		);

		self::$gemeente_pages = $result;

		return self::$gemeente_pages;
	}

	/**
	 * Finds the permalink of the page that uses [zw_gr26_pagina].
	 *
	 * @return string Main page URL, or empty string if not found.
	 */
	public function get_main_page_url(): string {
		if ( null !== self::$main_page_url ) {
			return false !== self::$main_page_url ? self::$main_page_url : '';
		}

		$pages = get_posts(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'numberposts' => 10,
				's'           => 'zw_gr26_pagina',
			]
		);

		foreach ( $pages as $page ) {
			if ( has_shortcode( $page->post_content, 'zw_gr26_pagina' ) ) {
				self::$main_page_url = (string) get_permalink( $page->ID );
				return self::$main_page_url;
			}
		}

		self::$main_page_url = false;

		return '';
	}

	/**
	 * Gets all 10 municipalities (publish and draft) for tile rendering.
	 *
	 * @return array<string, string> slug => display name.
	 */
	public function get_all_municipalities(): array {
		if ( null !== self::$all_municipalities ) {
			return self::$all_municipalities;
		}

		if ( ! post_type_exists( 'gemeente_uitslag' ) ) {
			self::$all_municipalities = [];
			return self::$all_municipalities;
		}

		$posts = get_posts(
			[
				'post_type'   => 'gemeente_uitslag',
				'post_status' => [ 'publish', 'draft' ],
				'numberposts' => 20,
				'orderby'     => 'title',
				'order'       => 'ASC',
			]
		);

		$map = [];
		foreach ( $posts as $post ) {
			$map[ $post->post_name ] = $post->post_title;
		}

		self::$all_municipalities = $map;

		return self::$all_municipalities;
	}
}
