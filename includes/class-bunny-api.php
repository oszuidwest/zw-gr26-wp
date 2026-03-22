<?php
declare( strict_types = 1 );
/**
 * Bunny CDN Stream API client.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches and normalizes video data from the Bunny CDN Stream API.
 */
class Bunny_API {

	/**
	 * Transient cache lifetime in seconds.
	 *
	 * @var int
	 */
	private const TRANSIENT_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * Number of items per API page.
	 *
	 * @var int
	 */
	private const ITEMS_PER_PAGE = 100;

	/**
	 * Bunny video status value for "finished encoding".
	 *
	 * @var int
	 */
	private const STATUS_FINISHED = 4;

	/**
	 * Base URL for the Bunny Stream API.
	 *
	 * @var string
	 */
	private const API_BASE = 'https://video.bunnycdn.com/library/';

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @var int
	 */
	private const API_TIMEOUT = 30;

	/**
	 * Maximum number of pages to fetch from the Bunny API.
	 *
	 * @var int
	 */
	private const MAX_PAGES = 50;

	/**
	 * Prefix for WordPress transient keys.
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'zwgr26_bunny_';

	/**
	 * Per-request credential cache keyed by library ID.
	 *
	 * @var array<int, array|null>
	 */
	private array $credentials_cache = [];

	/**
	 * Looks up Bunny CDN credentials for a library from ACF theme options.
	 *
	 * @param int $library_id The Bunny library ID to look up.
	 * @return array|null Credentials array or null if not found.
	 */
	public function get_credentials( int $library_id ): ?array {
		if ( array_key_exists( $library_id, $this->credentials_cache ) ) {
			return $this->credentials_cache[ $library_id ];
		}

		if ( ! function_exists( 'get_field' ) ) {
			return null;
		}

		$libraries = [
			[
				'id'       => 'bunny_cdn_library_id',
				'hostname' => 'bunny_cdn_hostname',
				'api_key'  => 'bunny_cdn_api_key',
			],
			[
				'id'       => 'bunny_cdn_library_id_fragmenten',
				'hostname' => 'bunny_cdn_hostname_fragmenten',
				'api_key'  => 'bunny_cdn_api_key_fragmenten',
			],
		];

		foreach ( $libraries as $lib ) {
			$id = get_field( $lib['id'], 'option' );
			if ( $id && (int) $id === $library_id ) {
				$hostname = get_field( $lib['hostname'], 'option' );
				$api_key  = get_field( $lib['api_key'], 'option' );
				if ( $hostname && $api_key ) {
					$result = [
						'libraryId' => $library_id,
						'hostname'  => rtrim( $hostname, '/' ),
						'apiKey'    => $api_key,
					];

					$this->credentials_cache[ $library_id ] = $result;
					return $result;
				}
			}
		}

		$this->credentials_cache[ $library_id ] = null;
		return null;
	}

	/**
	 * Fetches videos for a collection, with transient caching.
	 *
	 * @param int    $library_id    Bunny library ID.
	 * @param string $collection_id Bunny collection GUID.
	 * @param string $page_url      Optional page URL for video deep-links.
	 * @return array|null Normalized video list or null on failure.
	 */
	public function get_videos( int $library_id, string $collection_id, string $page_url = '' ): ?array {
		$transient_key = self::TRANSIENT_PREFIX . $library_id . '_' . md5( $collection_id );
		$cached        = get_transient( $transient_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$credentials = $this->get_credentials( $library_id );
		if ( ! $credentials ) {
			return null;
		}

		$all = $this->fetch_all_videos( $credentials, $collection_id );
		if ( null === $all ) {
			return null;
		}

		$items = $this->normalize_videos( $all, $credentials, $page_url );

		usort(
			$items,
			function ( array $a, array $b ): int {
				return strtotime( $b['datum'] ) - strtotime( $a['datum'] );
			}
		);

		set_transient( $transient_key, $items, self::TRANSIENT_TTL );

		return $items;
	}

	/**
	 * Formats a duration in seconds as H:MM:SS or M:SS.
	 *
	 * @param int $seconds Duration in seconds.
	 * @return string Formatted duration or empty string if zero.
	 */
	public function format_duration( int $seconds ): string {
		if ( $seconds <= 0 ) {
			return '';
		}

		$h = intdiv( $seconds, 3600 );
		$m = intdiv( $seconds % 3600, 60 );
		$s = $seconds % 60;

		return $h > 0
			? sprintf( '%d:%02d:%02d', $h, $m, $s )
			: sprintf( '%d:%02d', $m, $s );
	}

	/**
	 * Gets the full thumbnail URL for a single video by its GUID.
	 *
	 * @param int    $library_id Bunny library ID.
	 * @param string $video_id   Video GUID.
	 * @return string Thumbnail URL or empty string on failure.
	 */
	public function get_thumbnail_url( int $library_id, string $video_id ): string {
		$transient_key = self::TRANSIENT_PREFIX . 'thumb_' . $video_id;
		$cached        = get_transient( $transient_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$credentials = $this->get_credentials( $library_id );
		if ( ! $credentials ) {
			return '';
		}

		$api_url  = self::API_BASE . $credentials['libraryId'] . '/videos/' . $video_id;
		$response = wp_remote_get(
			$api_url,
			[
				'timeout' => self::API_TIMEOUT,
				'headers' => [
					'AccessKey' => $credentials['apiKey'],
					'accept'    => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
		if ( ! $body || empty( $body->thumbnailFileName ) ) {
			return '';
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
		$url = $credentials['hostname'] . '/' . $video_id . '/' . $body->thumbnailFileName;

		set_transient( $transient_key, $url, self::TRANSIENT_TTL );

		return $url;
	}

	/**
	 * Paginates through the Bunny API to fetch all finished videos in a collection.
	 *
	 * @param array  $credentials  API credentials.
	 * @param string $collection_id Collection GUID.
	 * @return array|null List of video objects or null on failure.
	 */
	private function fetch_all_videos( array $credentials, string $collection_id ): ?array {
		$api_url = self::API_BASE . $credentials['libraryId'] . '/videos';
		$page    = 1;
		$all     = [];

		while ( $page <= self::MAX_PAGES ) {
			$response = wp_remote_get(
				add_query_arg(
					[
						'collection'   => $collection_id,
						'itemsPerPage' => self::ITEMS_PER_PAGE,
						'page'         => $page,
					],
					$api_url
				),
				[
					'timeout' => self::API_TIMEOUT,
					'headers' => [
						'AccessKey' => $credentials['apiKey'],
						'accept'    => 'application/json',
					],
				]
			);

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return null;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ) );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
			if ( ! $body || ! isset( $body->items ) ) {
				return null;
			}

			$all = array_merge( $all, $body->items );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
			if ( count( $all ) >= $body->totalItems ) {
				break;
			}
			++$page;
		}

		return array_filter(
			$all,
			function ( $v ): bool {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
				return isset( $v->status ) && self::STATUS_FINISHED === $v->status;
			}
		);
	}

	/**
	 * Normalizes raw Bunny API video objects into a flat array format.
	 *
	 * @param array  $videos      Raw video objects from the API.
	 * @param array  $credentials API credentials including hostname.
	 * @param string $page_url    Optional page URL for deep-links.
	 * @return array Normalized video data.
	 */
	private function normalize_videos( array $videos, array $credentials, string $page_url ): array {
		$now   = new \DateTime( 'now', wp_timezone() );
		$items = [];

		foreach ( $videos as $v ) {
			$broadcast = $this->extract_broadcast_date( $v );

			if ( ! $broadcast || '2026' !== $broadcast->format( 'Y' ) ) {
				continue;
			}

			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
			$items[] = [
				'guid'       => $v->guid,
				'titel'      => $v->title,
				'thumbnail'  => $credentials['hostname'] . '/' . $v->guid . '/' . $v->thumbnailFileName,
				'url'        => $page_url
					? rtrim( $page_url, '/' ) . '/?v=' . $v->guid
					: $this->get_player_url( $v->videoLibraryId, $v->guid ),
				'duur'       => isset( $v->length ) ? (int) $v->length : 0,
				'datum'      => $broadcast->format( 'Y-m-d H:i:s' ),
				'binnenkort' => $broadcast > $now,
			];
			// phpcs:enable
		}

		return $items;
	}

	/**
	 * Gets video info (thumbnail URL, binnenkort status, and MP4 URL) for a single video.
	 *
	 * The MP4 URL points to the highest available resolution and is only set
	 * when the library has MP4 fallback files generated for this video.
	 *
	 * @param int    $library_id Bunny library ID.
	 * @param string $video_id   Video GUID.
	 * @return array|null Array with 'thumbnail', 'binnenkort', and 'mp4_url' keys, or null on failure.
	 */
	public function get_video_info( int $library_id, string $video_id ): ?array {
		$transient_key = self::TRANSIENT_PREFIX . 'info_' . $video_id;
		$cached        = get_transient( $transient_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$credentials = $this->get_credentials( $library_id );
		if ( ! $credentials ) {
			return null;
		}

		$api_url  = self::API_BASE . $credentials['libraryId'] . '/videos/' . $video_id;
		$response = wp_remote_get(
			$api_url,
			[
				'timeout' => self::API_TIMEOUT,
				'headers' => [
					'AccessKey' => $credentials['apiKey'],
					'accept'    => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
		if ( ! $body || empty( $body->thumbnailFileName ) ) {
			return null;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
		$thumbnail = $credentials['hostname'] . '/' . $video_id . '/' . $body->thumbnailFileName;

		$broadcast  = $this->extract_broadcast_date( $body );
		$binnenkort = false;
		if ( $broadcast ) {
			$now        = new \DateTime( 'now', wp_timezone() );
			$binnenkort = $broadcast > $now;
		}

		$mp4_url = '';
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
		if ( ! empty( $body->hasMP4Fallback ) && ! empty( $body->availableResolutions ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
			$best = $this->get_highest_mp4_resolution( $body->availableResolutions );
			if ( $best ) {
				$mp4_url = $credentials['hostname'] . '/' . $video_id . '/play_' . $best . '.mp4';
			}
		}

		$info = [
			'thumbnail'  => $thumbnail,
			'binnenkort' => $binnenkort,
			'mp4_url'    => $mp4_url,
		];

		set_transient( $transient_key, $info, self::TRANSIENT_TTL );

		return $info;
	}

	/**
	 * Returns the Bunny iframe player URL for a video.
	 *
	 * @param int    $library_id Bunny library ID.
	 * @param string $video_id   Video GUID.
	 * @return string Iframe player URL.
	 */
	public function get_player_url( int $library_id, string $video_id ): string {
		return 'https://iframe.mediadelivery.net/play/' . $library_id . '/' . $video_id;
	}

	/**
	 * Returns the HLS playlist URL for a video.
	 *
	 * @param int    $library_id Bunny library ID.
	 * @param string $video_id   Video GUID.
	 * @return string HLS playlist URL or empty string on failure.
	 */
	public function get_stream_url( int $library_id, string $video_id ): string {
		$credentials = $this->get_credentials( $library_id );
		if ( ! $credentials ) {
			return '';
		}
		return $credentials['hostname'] . '/' . $video_id . '/playlist.m3u8';
	}

	/**
	 * Resolves a video array in-place: adds poster, stream_url, and binnenkort from the API.
	 *
	 * The base $video array must contain at least 'titel', 'thumbnail', and 'url' keys.
	 * Guards against empty video ID or missing library ID by returning the array unchanged
	 * with binnenkort defaulting to true (coming soon).
	 *
	 * @param int    $library_id Bunny library ID (0 = skip resolution).
	 * @param string $video_id   Video GUID (empty = skip resolution).
	 * @param array  $video      Base video array to augment.
	 * @return array Augmented video array with 'poster', 'stream_url', and 'binnenkort' added.
	 */
	public function resolve_video( int $library_id, string $video_id, array $video ): array {
		$video['poster']     = '';
		$video['stream_url'] = '';
		$video['binnenkort'] = true;

		if ( ! $video_id || ! $library_id ) {
			return $video;
		}

		$resolved = $this->resolve_video_card( $library_id, $video_id, $video['thumbnail'] ?? '' );

		$video['thumbnail']  = $resolved['thumbnail'];
		$video['poster']     = $resolved['poster'];
		$video['url']        = $resolved['url'];
		$video['stream_url'] = $resolved['stream_url'];
		$video['binnenkort'] = $resolved['binnenkort'];

		return $video;
	}

	/**
	 * Resolves display data for a video card: thumbnail, coming-soon status, and stream URL.
	 *
	 * Checks the video info from the API and determines whether the video is
	 * upcoming ("binnenkort"). When it is not, it also resolves the HLS stream URL.
	 * An existing thumbnail is preserved when already provided.
	 *
	 * @param int    $library_id Bunny library ID.
	 * @param string $video_id   Video GUID.
	 * @param string $thumbnail  Existing thumbnail URL (preserved if non-empty).
	 * @return array{thumbnail: string, poster: string, binnenkort: bool, url: string, stream_url: string} Resolved video display data.
	 */
	public function resolve_video_card( int $library_id, string $video_id, string $thumbnail = '' ): array {
		$result = [
			'thumbnail'  => $thumbnail,
			'poster'     => '',
			'binnenkort' => true,
			'url'        => '',
			'stream_url' => '',
		];

		$info = $this->get_video_info( $library_id, $video_id );
		if ( $info ) {
			$result['poster'] = $info['thumbnail'];
			if ( ! $result['thumbnail'] ) {
				$result['thumbnail'] = $info['thumbnail'];
			}
			$result['binnenkort'] = $info['binnenkort'];
		}

		if ( ! $result['binnenkort'] ) {
			$result['url']        = $this->get_player_url( $library_id, $video_id );
			$result['stream_url'] = $this->get_stream_url( $library_id, $video_id );
		}

		return $result;
	}

	/**
	 * Picks the highest resolution suitable for MP4 fallback from a comma-separated list.
	 *
	 * Bunny CDN generates MP4 fallback files only up to 720p, so resolutions
	 * above that threshold are ignored to avoid pointing at non-existent files.
	 *
	 * @param string $resolutions Comma-separated resolution string from the Bunny API (e.g. '240p,360p,720p,1080p').
	 * @return string Highest valid resolution label (e.g. '720p') or empty string if none found.
	 */
	private function get_highest_mp4_resolution( string $resolutions ): string {
		$max_mp4_height = 720;
		$best           = '';
		$best_height    = 0;

		foreach ( explode( ',', $resolutions ) as $res ) {
			$res = trim( $res );
			if ( preg_match( '/^(\d+)p$/', $res, $m ) ) {
				$height = (int) $m[1];
				if ( $height > $best_height && $height <= $max_mp4_height ) {
					$best_height = $height;
					$best        = $res;
				}
			}
		}

		return $best;
	}

	/**
	 * Extracts the broadcast date from a video's meta tags.
	 *
	 * @param object $video Bunny API video object.
	 * @return \DateTime|null Parsed date or null if not found.
	 */
	private function extract_broadcast_date( object $video ): ?\DateTime {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
		if ( empty( $video->metaTags ) ) {
			return null;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
		foreach ( $video->metaTags as $tag ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
			if ( isset( $tag->property ) && 'description' === $tag->property && ! empty( $tag->value ) ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Bunny API response.
				if ( preg_match( '/^---\s*\n.*?broadcast_date:\s*(.+?)\s*\n.*?---/s', $tag->value, $m ) ) {
					try {
						return new \DateTime( trim( $m[1] ), wp_timezone() );
					} catch ( \Exception $e ) {
						return null;
					}
				}
				break;
			}
		}

		return null;
	}
}
