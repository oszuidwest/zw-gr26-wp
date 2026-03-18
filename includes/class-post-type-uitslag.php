<?php
/**
 * Custom Post Type for election results per municipality.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the gemeente_uitslag CPT, ACF fields, admin menu, seeding and protections.
 */
class Post_Type_Uitslag {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	private const POST_TYPE = 'gemeente_uitslag';

	/**
	 * Fixed municipalities: slug => display name.
	 *
	 * @var array<string, string>
	 */
	private const MUNICIPALITIES = [
		'roosendaal'     => 'Roosendaal',
		'bergen-op-zoom' => 'Bergen op Zoom',
		'moerdijk'       => 'Moerdijk',
		'halderberge'    => 'Halderberge',
		'woensdrecht'    => 'Woensdrecht',
		'etten-leur'     => 'Etten-Leur',
		'zundert'        => 'Zundert',
		'rucphen'        => 'Rucphen',
		'steenbergen'    => 'Steenbergen',
		'tholen'         => 'Tholen',
	];

	/**
	 * Wires up all hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'seed_municipalities' ] );
		add_action( 'acf/include_fields', [ $this, 'register_acf_fields' ] );

		// Protections.
		add_action( 'admin_head', [ $this, 'hide_add_new_button' ] );
		add_filter( 'user_has_cap', [ $this, 'block_delete' ], 10, 3 );

		// Sort repeater rows alphabetically on save.
		add_action( 'acf/save_post', [ $this, 'sort_partijen' ], 20 );

		// Menu highlighting.
		add_filter( 'parent_file', [ $this, 'fix_parent_file' ] );
		add_filter( 'submenu_file', [ $this, 'fix_submenu_file' ] );
	}

	/**
	 * Registers the custom post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'             => [
					'name'               => 'Uitslagen GR26',
					'singular_name'      => 'Uitslag',
					'edit_item'          => 'Uitslag bewerken',
					'view_item'          => 'Uitslag bekijken',
					'search_items'       => 'Uitslagen zoeken',
					'not_found'          => 'Geen uitslagen gevonden',
					'not_found_in_trash' => 'Geen uitslagen in prullenbak',
				],
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_rest'       => false,
				'show_in_menu'       => false,
				'supports'           => [ 'title' ],
				'capability_type'    => 'page',
				'map_meta_cap'       => true,
			]
		);
	}

	/**
	 * Registers the top-level admin menu and per-municipality submenu links.
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			'Uitslagen GR26',
			'Uitslagen GR26',
			'edit_pages',
			'edit.php?post_type=' . self::POST_TYPE,
			'',
			'dashicons-chart-bar',
			30
		);

		// Per-municipality submenu links.
		$posts = $this->get_municipality_posts();
		foreach ( $posts as $slug => $post_id ) {
			$name = self::MUNICIPALITIES[ $slug ] ?? $slug;
			add_submenu_page(
				'edit.php?post_type=' . self::POST_TYPE,
				$name,
				$name,
				'edit_pages',
				'post.php?post=' . $post_id . '&action=edit'
			);
		}

		// Remove "Add New" submenu.
		remove_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, 'post-new.php?post_type=' . self::POST_TYPE );
	}

	/**
	 * Registers ACF field group for election results.
	 *
	 * @return void
	 */
	public function register_acf_fields(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			[
				'key'      => 'group_zwgr26_uitslag',
				'title'    => 'Verkiezingsuitslag',
				'fields'   => [
					[
						'key'          => 'field_zwgr26_totaal_zetels',
						'label'        => 'Totaal zetels',
						'name'         => 'totaal_zetels',
						'type'         => 'number',
						'required'     => 1,
						'min'          => 1,
						'max'          => 100,
						'instructions' => 'Het totaal aantal raadszetels in deze gemeente.',
						'wrapper'      => [ 'width' => '34' ],
					],
					[
						'key'          => 'field_zwgr26_opkomst_2022',
						'label'        => 'Opkomst 2022',
						'name'         => 'opkomst_2022',
						'type'         => 'number',
						'required'     => 0,
						'min'          => 0,
						'max'          => 100,
						'step'         => 0.1,
						'append'       => '%',
						'instructions' => 'Opkomstpercentage 2022.',
						'wrapper'      => [ 'width' => '33' ],
					],
					[
						'key'          => 'field_zwgr26_opkomst_2026',
						'label'        => 'Opkomst 2026',
						'name'         => 'opkomst_2026',
						'type'         => 'number',
						'required'     => 0,
						'min'          => 0,
						'max'          => 100,
						'step'         => 0.1,
						'append'       => '%',
						'instructions' => 'Opkomstpercentage 2026.',
						'wrapper'      => [ 'width' => '33' ],
					],
					[
						'key'          => 'field_zwgr26_partijen',
						'label'        => 'Partijen',
						'name'         => 'partijen',
						'type'         => 'repeater',
						'required'     => 0,
						'max'          => 30,
						'layout'       => 'table',
						'button_label' => 'Partij toevoegen',
						'sub_fields'   => [
							[
								'key'      => 'field_zwgr26_partij_naam',
								'label'    => 'Partijnaam',
								'name'     => 'partij_naam',
								'type'     => 'text',
								'required' => 1,
								'wrapper'  => [ 'width' => '20' ],
							],
							[
								'key'      => 'field_zwgr26_naam_kort',
								'label'    => 'Naam kort',
								'name'     => 'naam_kort',
								'type'     => 'text',
								'required' => 1,
								'wrapper'  => [ 'width' => '15' ],
							],
							[
								'key'           => 'field_zwgr26_kleur',
								'label'         => 'Kleur',
								'name'          => 'kleur',
								'type'          => 'color_picker',
								'required'      => 0,
								'default_value' => '#90a4ae',
								'wrapper'       => [ 'width' => '15' ],
							],
							[
								'key'          => 'field_zwgr26_zetels_2022',
								'label'        => 'Zetels 2022',
								'name'         => 'zetels_2022',
								'type'         => 'number',
								'required'     => 0,
								'min'          => 0,
								'wrapper'      => [ 'width' => '12' ],
								'instructions' => 'Leeg = partij bestond niet in 2022.',
							],
							[
								'key'      => 'field_zwgr26_zetels_2026',
								'label'    => 'Zetels 2026',
								'name'     => 'zetels_2026',
								'type'     => 'number',
								'required' => 0,
								'min'      => 0,
								'wrapper'  => [ 'width' => '12' ],
							],
							[
								'key'          => 'field_zwgr26_programma_url',
								'label'        => 'Programma',
								'name'         => 'programma_url',
								'type'         => 'url',
								'required'     => 0,
								'wrapper'      => [ 'width' => '14' ],
								'instructions' => 'Link naar het verkiezingsprogramma.',
							],
						],
					],
				],
				'location' => [
					[
						[
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => self::POST_TYPE,
						],
					],
				],
				'style'    => 'seamless',
				'position' => 'normal',
			]
		);
	}

	/**
	 * Seeds the 10 fixed municipalities as draft posts.
	 *
	 * @return void
	 */
	public function seed_municipalities(): void {
		// Only run if the post type is registered.
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			return;
		}

		// Single query to find all existing municipality slugs.
		$existing_posts = get_posts(
			[
				'post_type'   => self::POST_TYPE,
				'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
				'numberposts' => 20,
				'fields'      => 'ids',
			]
		);

		if ( count( $existing_posts ) >= count( self::MUNICIPALITIES ) ) {
			return;
		}

		$existing_slugs = [];
		foreach ( $existing_posts as $post_id ) {
			$existing_slugs[] = get_post_field( 'post_name', $post_id );
		}

		foreach ( self::MUNICIPALITIES as $slug => $name ) {
			if ( in_array( $slug, $existing_slugs, true ) ) {
				continue;
			}

			wp_insert_post(
				[
					'post_type'   => self::POST_TYPE,
					'post_title'  => $name,
					'post_name'   => $slug,
					'post_status' => 'draft',
				]
			);
		}
	}

	/**
	 * Seeds municipalities on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$instance = new self();
		$instance->register_post_type();
		$instance->seed_municipalities();
	}

	/**
	 * Sorts the partijen repeater rows alphabetically by party name.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function sort_partijen( int $post_id ): void {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$partijen = get_field( 'partijen', $post_id );
		if ( ! is_array( $partijen ) || empty( $partijen ) ) {
			return;
		}

		usort(
			$partijen,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['partij_naam'] ?? '', $b['partij_naam'] ?? '' );
			}
		);

		// Unhook to prevent infinite loop.
		remove_action( 'acf/save_post', [ $this, 'sort_partijen' ], 20 );
		update_field( 'partijen', $partijen, $post_id );
		add_action( 'acf/save_post', [ $this, 'sort_partijen' ], 20 );
	}

	/**
	 * Hides the Add New button via CSS on the CPT overview screen.
	 *
	 * @return void
	 */
	public function hide_add_new_button(): void {
		$screen = get_current_screen();
		if ( $screen && self::POST_TYPE === $screen->post_type ) {
			echo '<style>.page-title-action { display: none !important; }</style>';
		}
	}

	/**
	 * Blocks deletion of municipality posts.
	 *
	 * @param array $allcaps All capabilities of the user.
	 * @param array $caps    Required capabilities.
	 * @param array $args    Arguments: [0] = requested cap, [1] = user ID, [2] = post ID.
	 * @return array Modified capabilities.
	 */
	public function block_delete( array $allcaps, array $caps, array $args ): array {
		if ( empty( $args[2] ) ) {
			return $allcaps;
		}

		$cap = $args[0] ?? '';
		if ( 'delete_page' !== $cap && 'delete_post' !== $cap ) {
			return $allcaps;
		}

		$post = get_post( $args[2] );
		if ( $post && self::POST_TYPE === $post->post_type ) {
			$allcaps['delete_pages']           = false;
			$allcaps['delete_others_pages']    = false;
			$allcaps['delete_published_pages'] = false;
		}

		return $allcaps;
	}

	/**
	 * Fixes parent menu highlighting when editing a municipality post.
	 *
	 * @param string $parent_file Current parent file.
	 * @return string Corrected parent file.
	 */
	public function fix_parent_file( string $parent_file ): string {
		$screen = get_current_screen();
		if ( $screen && self::POST_TYPE === $screen->post_type ) {
			return 'edit.php?post_type=' . self::POST_TYPE;
		}
		return $parent_file;
	}

	/**
	 * Fixes submenu highlighting when editing a specific municipality post.
	 *
	 * @param string|null $submenu_file Current submenu file.
	 * @return string|null Corrected submenu file.
	 */
	public function fix_submenu_file( ?string $submenu_file ): ?string {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return $submenu_file;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( $post_id ) {
			return 'post.php?post=' . $post_id . '&action=edit';
		}

		return $submenu_file;
	}

	/**
	 * Gets all municipality post IDs keyed by slug.
	 *
	 * @return array<string, int>
	 */
	private function get_municipality_posts(): array {
		$posts = get_posts(
			[
				'post_type'   => self::POST_TYPE,
				'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
				'numberposts' => 20,
			]
		);

		$map = [];
		foreach ( $posts as $post ) {
			$slug = $post->post_name;
			if ( isset( self::MUNICIPALITIES[ $slug ] ) ) {
				$map[ $slug ] = $post->ID;
			}
		}

		return $map;
	}
}
