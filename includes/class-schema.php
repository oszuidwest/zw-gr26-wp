<?php
/**
 * Yoast SEO Schema integration.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds CollectionPage type and VideoObject nodes to the Yoast SEO schema graph.
 */
class Schema {

	/**
	 * Bunny CDN API client.
	 *
	 * @var Bunny_API
	 */
	private Bunny_API $bunny;

	/**
	 * Data provider for posts and election results.
	 *
	 * @var Data_Provider
	 */
	private Data_Provider $data;

	/**
	 * Cached video list parsed from post content.
	 *
	 * @var array|null
	 */
	private ?array $videos = null;

	/**
	 * Cached article list parsed from post content.
	 *
	 * @var array|null
	 */
	private ?array $articles = null;

	/**
	 * Dutch-to-English month name mapping for date parsing.
	 *
	 * @var array<string, string>
	 */
	private const DUTCH_MONTHS = [
		'januari'   => 'January',
		'februari'  => 'February',
		'maart'     => 'March',
		'april'     => 'April',
		'mei'       => 'May',
		'juni'      => 'June',
		'juli'      => 'July',
		'augustus'  => 'August',
		'september' => 'September',
		'oktober'   => 'October',
		'november'  => 'November',
		'december'  => 'December',
	];

	/**
	 * Constructor.
	 *
	 * @param Bunny_API     $bunny Bunny CDN API client.
	 * @param Data_Provider $data  Data provider.
	 */
	public function __construct( Bunny_API $bunny, Data_Provider $data ) {
		$this->bunny = $bunny;
		$this->data  = $data;
	}

	/**
	 * Registers Yoast schema filters if Yoast is active.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			return;
		}

		add_filter( 'wpseo_schema_webpage', [ $this, 'set_collection_page' ] );
		add_filter( 'wpseo_schema_graph', [ $this, 'add_video_objects' ] );
	}

	/**
	 * Changes WebPage type to CollectionPage and adds video and article references.
	 *
	 * @param array $data WebPage schema piece.
	 * @return array Modified schema piece.
	 */
	public function set_collection_page( array $data ): array {
		if ( ! $this->is_election_page() ) {
			return $data;
		}

		$data['@type'] = 'CollectionPage';

		$videos = $this->get_videos();
		if ( ! empty( $videos ) ) {
			$canonical     = $this->get_canonical();
			$data['video'] = [];
			foreach ( $videos as $video ) {
				$data['video'][] = [
					'@id' => $canonical . '#/schema/VideoObject/' . $video['videoid'],
				];
			}
		}

		$articles = $this->get_articles();
		if ( ! empty( $articles ) ) {
			$data['hasPart'] = [];
			foreach ( $articles as $article ) {
				$part = [
					'@type'         => 'Article',
					'@id'           => $article['url'],
					'url'           => $article['url'],
					'name'          => $article['titel'],
					'headline'      => $article['titel'],
					'datePublished' => $article['datum_iso'],
				];

				if ( ! empty( $article['afbeelding'] ) ) {
					$part['image'] = $article['afbeelding'];
				}

				if ( ! empty( $article['auteur'] ) ) {
					$part['author'] = [
						'@type' => 'Person',
						'name'  => $article['auteur'],
						'url'   => $article['auteur_url'] ?? '',
					];
				}

				$data['hasPart'][] = $part;
			}
		}

		return $data;
	}

	/**
	 * Adds VideoObject nodes to the schema graph.
	 *
	 * @param array $graph The full schema graph array.
	 * @return array Modified graph with VideoObject nodes appended.
	 */
	public function add_video_objects( array $graph ): array {
		if ( ! $this->is_election_page() ) {
			return $graph;
		}

		$videos    = $this->get_videos();
		$canonical = $this->get_canonical();

		foreach ( $videos as $video ) {
			$graph[] = $this->build_video_object( $video, $canonical );
		}

		return $graph;
	}

	/**
	 * Checks if the current post contains the election page shortcode.
	 *
	 * @return bool True if the current post uses the election page shortcode.
	 */
	private function is_election_page(): bool {
		$post = get_post();
		if ( ! $post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'zw_gr26_pagina' );
	}

	/**
	 * Gets the canonical URL for the current page.
	 *
	 * @return string Canonical URL.
	 */
	private function get_canonical(): string {
		$canonical = wp_get_canonical_url( get_the_ID() );

		return $canonical ? $canonical : get_permalink();
	}

	/**
	 * Parses all videos from the raw post content. Cached per request.
	 *
	 * @return array List of video arrays with keys: naam, videoid, library_id, datum.
	 */
	private function get_videos(): array {
		if ( null !== $this->videos ) {
			return $this->videos;
		}

		$post = get_post();
		if ( ! $post ) {
			$this->videos = [];
			return $this->videos;
		}

		$content = $post->post_content;

		$all_videos = array_merge(
			$this->parse_video_shortcodes( $content, 'zw_gr26_debatten', 'zw_gr26_debat' ),
			$this->parse_video_shortcodes( $content, 'zw_gr26_explainers', 'zw_gr26_explainer' )
		);

		// Exclude videos with a broadcast_date in the future.
		$this->videos = array_values(
			array_filter(
				$all_videos,
				function ( array $video ): bool {
					if ( ! $video['library_id'] ) {
						return true;
					}
					$info = $this->bunny->get_video_info( $video['library_id'], $video['videoid'] );
					return null === $info || ! $info['binnenkort'];
				}
			)
		);

		return $this->videos;
	}

	/**
	 * Parses articles from the raw post content via Data_Provider. Cached per request.
	 *
	 * @return array List of article arrays with keys: url, titel, datum_iso.
	 */
	private function get_articles(): array {
		if ( null !== $this->articles ) {
			return $this->articles;
		}

		$this->articles = [];

		$post = get_post();
		if ( ! $post ) {
			return $this->articles;
		}

		$content = $post->post_content;

		if ( ! preg_match( '/\[zw_gr26_nieuws\s+([^\]]*)\]/', $content, $match ) ) {
			return $this->articles;
		}

		$atts = shortcode_parse_atts( $match[1] );
		if ( empty( $atts['dossier'] ) ) {
			return $this->articles;
		}

		$count          = ! empty( $atts['aantal'] ) ? (int) $atts['aantal'] : 6;
		$this->articles = $this->data->get_dossier_posts( $atts['dossier'], $count );

		return $this->articles;
	}

	/**
	 * Parses parent and child video shortcode blocks from raw content.
	 *
	 * @param string $content     Raw post content.
	 * @param string $parent_tag  Parent shortcode name (e.g. 'zw_gr26_debatten').
	 * @param string $child_tag   Child shortcode name (e.g. 'zw_gr26_debat').
	 * @return array Videos found in the blocks.
	 */
	private function parse_video_shortcodes( string $content, string $parent_tag, string $child_tag ): array {
		$videos = [];

		$parent_pattern = '/\[' . $parent_tag . '\s+([^\]]*)\](.*?)\[\/' . $parent_tag . '\]/s';
		if ( ! preg_match_all( $parent_pattern, $content, $parents, PREG_SET_ORDER ) ) {
			return $videos;
		}

		$child_pattern = '/\[' . $child_tag . '\s+([^\]]*)\]/';

		foreach ( $parents as $parent ) {
			$parent_atts = shortcode_parse_atts( $parent[1] );
			$library_id  = ! empty( $parent_atts['bibliotheek'] ) ? (int) $parent_atts['bibliotheek'] : 0;
			$inner       = $parent[2];

			if ( ! preg_match_all( $child_pattern, $inner, $children, PREG_SET_ORDER ) ) {
				continue;
			}

			foreach ( $children as $child ) {
				$atts = shortcode_parse_atts( $child[1] );
				if ( empty( $atts['videoid'] ) ) {
					continue;
				}

				$videos[] = [
					'naam'       => $atts['naam'] ?? '',
					'videoid'    => $atts['videoid'],
					'library_id' => $library_id,
					'datum'      => $atts['datum'] ?? '',
				];
			}
		}

		return $videos;
	}

	/**
	 * Builds a single VideoObject schema node.
	 *
	 * @param array  $video {
	 *     Video data.
	 *
	 *     @type string $naam       Video display name.
	 *     @type string $videoid    Bunny video GUID.
	 *     @type int    $library_id Bunny library ID.
	 *     @type string $datum      Dutch date string.
	 * }
	 * @param string $canonical Canonical URL of the page.
	 * @return array Schema.org VideoObject.
	 */
	private function build_video_object( array $video, string $canonical ): array {
		$thumbnail = '';
		if ( $video['library_id'] ) {
			$info = $this->bunny->get_video_info( $video['library_id'], $video['videoid'] );
			if ( $info ) {
				$thumbnail = $info['thumbnail'];
			}
		}

		$upload_date = $this->parse_dutch_date( $video['datum'] );

		$content_url = '';
		if ( $video['library_id'] ) {
			$content_url = $this->bunny->get_mp4_url( $video['library_id'], $video['videoid'] );
		}

		return [
			'@type'            => 'VideoObject',
			'@id'              => $canonical . '#/schema/VideoObject/' . $video['videoid'],
			'name'             => $video['naam'],
			'description'      => $video['naam'],
			'thumbnailUrl'     => $thumbnail,
			'contentUrl'       => $content_url,
			'uploadDate'       => $upload_date,
			'isFamilyFriendly' => true,
			'inLanguage'       => 'nl-NL',
		];
	}

	/**
	 * Parses a Dutch date string (e.g. '13 maart 2026') into ISO 8601 format.
	 *
	 * Falls back to the current post's publication date.
	 *
	 * @param string $date_string Dutch date string.
	 * @return string ISO 8601 date string.
	 */
	private function parse_dutch_date( string $date_string ): string {
		if ( $date_string ) {
			$english = str_ireplace(
				array_keys( self::DUTCH_MONTHS ),
				array_values( self::DUTCH_MONTHS ),
				$date_string
			);

			$timestamp = strtotime( $english );
			if ( false !== $timestamp ) {
				return gmdate( 'Y-m-d\TH:i:sP', $timestamp );
			}
		}

		$date = get_the_date( 'c' );

		return $date ? $date : '';
	}
}
