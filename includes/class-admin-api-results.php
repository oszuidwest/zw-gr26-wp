<?php
/**
 * Admin meta box showing election results from the ZuidWest Kiest API.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a read-only meta box on gemeente_uitslag edit screens that
 * displays live results from platform.zuidwestkiest.nl.
 */
class Admin_API_Results {

	/**
	 * API base URL.
	 */
	private const API_BASE = 'https://platform.zuidwestkiest.nl/api/data/';

	/**
	 * Mapping of municipality slugs to API IDs.
	 */
	private const API_MAP = [
		'woensdrecht'    => 21,
		'halderberge'    => 22,
		'bergen-op-zoom' => 23,
		'moerdijk'       => 24,
		'roosendaal'     => 25,
		'etten-leur'     => 26,
		'zundert'        => 27,
	];

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_gemeente_uitslag', [ new self(), 'add_meta_box' ] );
	}

	/**
	 * Add the meta box to the gemeente_uitslag edit screen.
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public function add_meta_box( \WP_Post $post ): void {
		$slug = $post->post_name;
		if ( ! isset( self::API_MAP[ $slug ] ) ) {
			return;
		}

		add_meta_box(
			'zwgr26_api_results',
			'Uitslagen vanuit ZuidWest Kiest API',
			[ $this, 'render_meta_box' ],
			'gemeente_uitslag',
			'normal',
			'low'
		);
	}

	/**
	 * Render the meta box content.
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$slug   = $post->post_name;
		$api_id = self::API_MAP[ $slug ] ?? null;

		if ( null === $api_id ) {
			echo '<p><em>Geen API-koppeling voor deze gemeente.</em></p>';
			return;
		}

		$data = $this->fetch_api( $api_id );

		if ( null === $data ) {
			echo '<p style="color:#d63638"><strong>Kon data niet ophalen van de API.</strong></p>';
			return;
		}

		$parties  = $data['parties'] ?? [];
		$total    = (int) ( $data['total_seats'] ?? 0 );
		$has_2026 = $this->has_current_results( $data );

		$status = $has_2026
			? '<span style="color:#00a32a;font-weight:600">2026-resultaten beschikbaar</span>'
			: '<span style="color:#996800;font-weight:600">Nog geen 2026-resultaten</span>';

		echo '<p>Totaal zetels: <strong>' . esc_html( (string) $total ) . '</strong> &middot; ';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML with safe inline values.
		echo $status . '</p>';

		if ( empty( $parties ) ) {
			echo '<p><em>Geen partijdata.</em></p>';
			return;
		}

		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>Partij</th>';
		echo '<th>Kort</th>';
		echo '<th style="text-align:center">Zetels 2022</th>';
		echo '<th style="text-align:center">Zetels 2026</th>';
		echo '</tr></thead><tbody>';

		usort(
			$parties,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['name'] ?? '', $b['name'] ?? '' );
			}
		);

		foreach ( $parties as $party ) {
			$name    = $party['name'] ?? '';
			$short   = $party['short_name'] ?? '';
			$last    = $party['results']['last']['seats'] ?? '—';
			$current = $party['results']['current']['seats'] ?? '—';

			echo '<tr>';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td>' . esc_html( $short ) . '</td>';
			echo '<td style="text-align:center">' . esc_html( (string) $last ) . '</td>';
			echo '<td style="text-align:center;font-weight:700">' . esc_html( (string) $current ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		$refresh_url = add_query_arg(
			[
				'post'   => $post->ID,
				'action' => 'edit',
			],
			admin_url( 'post.php' )
		);
		echo '<p style="margin-top:8px">';
		echo '<a href="' . esc_url( $refresh_url ) . '" class="button">Ververs API-data</a> ';
		echo '<span class="description">Data van platform.zuidwestkiest.nl</span>';
		echo '</p>';
	}

	/**
	 * Fetch municipality data from the API.
	 *
	 * @param int $api_id API municipality ID.
	 * @return array<string, mixed>|null Decoded JSON or null on failure.
	 */
	private function fetch_api( int $api_id ): ?array {
		$response = wp_remote_get(
			self::API_BASE . $api_id,
			[ 'timeout' => 15 ]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Check if the API data contains 2026 results.
	 *
	 * @param array<string, mixed> $data API response data.
	 * @return bool
	 */
	private function has_current_results( array $data ): bool {
		foreach ( $data['parties'] ?? [] as $party ) {
			$seats = $party['results']['current']['seats'] ?? null;
			if ( null !== $seats ) {
				return true;
			}
		}
		return false;
	}
}
