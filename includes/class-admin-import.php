<?php
/**
 * Admin import page for election results from the ZuidWest Kiest API.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds an admin page under "Uitslagen GR26" to import results from platform.zuidwestkiest.nl.
 */
class Admin_Import {

	/**
	 * API base URL.
	 */
	private const API_BASE = 'https://platform.zuidwestkiest.nl/api/data/';

	/**
	 * Mapping of API IDs to municipality slugs.
	 */
	private const API_MAP = [
		21 => 'woensdrecht',
		22 => 'halderberge',
		23 => 'bergen-op-zoom',
		24 => 'moerdijk',
		25 => 'roosendaal',
		26 => 'etten-leur',
		27 => 'zundert',
	];

	/**
	 * Nonce action name.
	 */
	private const NONCE_ACTION = 'zwgr26_import_action';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		$instance = new self();
		add_action( 'admin_menu', [ $instance, 'add_submenu_page' ], 20 );
		add_action( 'admin_post_zwgr26_import', [ $instance, 'handle_import' ] );
	}

	/**
	 * Add the import submenu page under Uitslagen GR26.
	 *
	 * @return void
	 */
	public function add_submenu_page(): void {
		add_submenu_page(
			'edit.php?post_type=gemeente_uitslag',
			'Importeren',
			'Importeren',
			'edit_pages',
			'zwgr26-import',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Render the import admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = isset( $_GET['zwgr26_result'] ) ? sanitize_text_field( wp_unslash( $_GET['zwgr26_result'] ) ) : '';

		echo '<div class="wrap">';
		echo '<h1>Uitslagen importeren</h1>';

		if ( $result ) {
			$this->render_notices( $result );
		}

		echo '<p>Importeer verkiezingsdata van <strong>platform.zuidwestkiest.nl</strong>.<br>';
		echo 'Bestaande data wordt overschreven. De import kan vaker worden uitgevoerd.</p>';

		echo '<table class="widefat fixed striped" style="max-width:600px">';
		echo '<thead><tr><th>Gemeente</th><th>API ID</th><th>Status</th></tr></thead><tbody>';

		$posts = $this->get_all_posts();

		foreach ( self::API_MAP as $api_id => $slug ) {
			$post   = $posts[ $slug ] ?? null;
			$name   = $post ? $post->post_title : $slug;
			$status = $post ? ucfirst( get_post_status( $post ) ) : '<em>Geen post</em>';
			echo '<tr>';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td>' . esc_html( (string) $api_id ) . '</td>';
			echo '<td>' . esc_html( $status ) . '</td>';
			echo '</tr>';
		}

		$not_covered = [ 'steenbergen', 'rucphen', 'tholen' ];
		foreach ( $not_covered as $slug ) {
			$post   = $posts[ $slug ] ?? null;
			$name   = $post ? $post->post_title : $slug;
			$status = $post ? ucfirst( get_post_status( $post ) ) : '<em>Geen post</em>';
			echo '<tr>';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td><em>n.v.t.</em></td>';
			echo '<td>' . esc_html( $status ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:16px">';
		echo '<input type="hidden" name="action" value="zwgr26_import">';
		wp_nonce_field( self::NONCE_ACTION, 'zwgr26_nonce' );

		echo '<p>';
		echo '<label><input type="checkbox" name="publish" value="1"> ';
		echo 'Gemeenten met 2026-resultaten automatisch publiceren</label>';
		echo '</p>';

		submit_button( 'Importeren vanuit ZuidWest Kiest', 'primary', 'submit', false );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle the import form submission.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( 'Geen toegang.', 403 );
		}

		check_admin_referer( self::NONCE_ACTION, 'zwgr26_nonce' );

		$publish = ! empty( $_POST['publish'] );
		$log     = [];
		$success = 0;
		$errors  = 0;

		foreach ( self::API_MAP as $api_id => $slug ) {
			$post = $this->find_post( $slug );
			if ( ! $post ) {
				$log[] = $slug . ': geen post gevonden';
				++$errors;
				continue;
			}

			$data = $this->fetch_api( $api_id );
			if ( null === $data ) {
				$log[] = $post->post_title . ': API-fout (ID ' . $api_id . ')';
				++$errors;
				continue;
			}

			$has_2026    = $this->has_current_results( $data );
			$party_count = count( $data['parties'] ?? [] );

			$this->update_post( $post->ID, $data );

			$msg = $post->post_title . ': ' . $party_count . ' partijen geïmporteerd';

			if ( $publish && $has_2026 && 'publish' !== $post->post_status ) {
				wp_update_post(
					[
						'ID'          => $post->ID,
						'post_status' => 'publish',
					]
				);
				$msg .= ' (gepubliceerd)';
			}

			$log[] = $msg;
			++$success;
		}

		$summary = $success . ' geïmporteerd, ' . $errors . ' fouten';
		$result  = rawurlencode( $summary . '|' . implode( "\n", $log ) );

		wp_safe_redirect(
			add_query_arg(
				'zwgr26_result',
				$result,
				admin_url( 'edit.php?post_type=gemeente_uitslag&page=zwgr26-import' )
			)
		);
		exit;
	}

	/**
	 * Render admin notices from the import result string.
	 *
	 * @param string $result Encoded result string.
	 * @return void
	 */
	private function render_notices( string $result ): void {
		$parts   = explode( '|', $result, 2 );
		$summary = $parts[0];
		$details = $parts[1] ?? '';

		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>' . esc_html( $summary ) . '</strong></p>';
		if ( $details ) {
			echo '<ul style="list-style:disc;padding-left:20px;margin-top:0">';
			foreach ( explode( "\n", $details ) as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/**
	 * Get all municipality posts keyed by slug.
	 *
	 * @return array<string, \WP_Post>
	 */
	private function get_all_posts(): array {
		$posts = get_posts(
			[
				'post_type'   => 'gemeente_uitslag',
				'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
				'numberposts' => 20,
			]
		);

		$map = [];
		foreach ( $posts as $post ) {
			$map[ $post->post_name ] = $post;
		}
		return $map;
	}

	/**
	 * Find the gemeente_uitslag post for a given slug.
	 *
	 * @param string $slug Municipality slug.
	 * @return \WP_Post|null
	 */
	private function find_post( string $slug ): ?\WP_Post {
		$posts = get_posts(
			[
				'post_type'   => 'gemeente_uitslag',
				'name'        => $slug,
				'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
				'numberposts' => 1,
			]
		);

		return $posts[0] ?? null;
	}

	/**
	 * Fetch municipality data from the API.
	 *
	 * @param int $api_id API municipality ID.
	 * @return array|null Decoded JSON or null on failure.
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
	 * @param array $data API response data.
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

	/**
	 * Update ACF fields on a municipality post from API data.
	 *
	 * @param int   $post_id WordPress post ID.
	 * @param array $data    API response data.
	 * @return void
	 */
	private function update_post( int $post_id, array $data ): void {
		update_field( 'totaal_zetels', (int) ( $data['total_seats'] ?? 0 ), $post_id );

		// Build repeater rows.
		$partijen = [];
		foreach ( $data['parties'] ?? [] as $party ) {
			$last_seats    = $party['results']['last']['seats'] ?? null;
			$current_seats = $party['results']['current']['seats'] ?? null;

			$partijen[] = [
				'partij_naam' => $party['name'] ?? '',
				'naam_kort'   => $party['short_name'] ?? '',
				'kleur'       => $party['color'] ?? '#90a4ae',
				'zetels_2022' => null !== $last_seats ? (int) $last_seats : '',
				'zetels_2026' => null !== $current_seats ? (int) $current_seats : '',
			];
		}

		update_field( 'partijen', $partijen, $post_id );
	}
}
