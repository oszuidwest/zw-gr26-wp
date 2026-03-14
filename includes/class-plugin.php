<?php
/**
 * Plugin orchestrator.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton that wires up all plugin components and registers shortcodes.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Asset manager.
	 *
	 * @var Assets
	 */
	private Assets $assets;

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
	 * Yoast SEO Schema integration.
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Image proxy service.
	 *
	 * @var Image_Proxy
	 */
	private Image_Proxy $proxy;

	/**
	 * Shared HTML renderer.
	 *
	 * @var Renderer
	 */
	private Renderer $renderer;

	/**
	 * Election results CPT manager.
	 *
	 * @var Post_Type_Uitslag
	 */
	private Post_Type_Uitslag $uitslag;

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructs dependencies and registers shortcodes.
	 */
	private function __construct() {
		$this->assets   = new Assets();
		$this->bunny    = new Bunny_API();
		$this->data     = new Data_Provider();
		$this->proxy    = new Image_Proxy();
		$this->renderer = new Renderer( $this->proxy );
		$this->schema   = new Schema( $this->bunny, $this->data );
		$this->uitslag  = new Post_Type_Uitslag();

		$this->assets->register();
		$this->schema->register();
		$this->uitslag->register();
		$this->register_shortcodes();
	}

	/**
	 * Registers all plugin shortcodes.
	 *
	 * @return void
	 */
	private function register_shortcodes(): void {
		$pagina       = new Shortcode_Pagina( $this->assets, $this->renderer );
		$livestream   = new Shortcode_Livestream( $this->assets, $this->renderer );
		$debatten     = new Shortcode_Debatten( $this->assets, $this->renderer, $this->bunny, $this->data );
		$explainers   = new Shortcode_Explainers( $this->assets, $this->renderer, $this->bunny );
		$nieuws       = new Shortcode_Nieuws( $this->assets, $this->renderer, $this->data );
		$podcast      = new Shortcode_Podcast( $this->assets, $this->renderer, $this->data, $this->proxy );
		$programmas   = new Shortcode_Programmas( $this->assets, $this->renderer, $this->data );
		$resultaten   = new Shortcode_Resultaten( $this->assets, $this->renderer, $this->data );
		$stemlocaties = new Shortcode_Stemlocaties( $this->assets, $this->renderer, $this->data );
		$tekst        = new Shortcode_Tekst( $this->assets, $this->renderer );

		// Gemeente subpage shortcodes.
		$gem_pagina     = new Shortcode_Gemeente_Pagina( $this->assets, $this->renderer, $this->data );
		$gem_explainer  = new Shortcode_Gemeente_Explainer( $this->assets, $this->renderer, $this->bunny, $this->proxy );
		$gem_programmas = new Shortcode_Gemeente_Programmas( $this->assets, $this->renderer, $this->data );
		$gem_resultaten = new Shortcode_Gemeente_Resultaten( $this->assets, $this->renderer, $this->data );

		add_shortcode( 'zw_gr26_pagina', [ $pagina, 'render' ] );
		add_shortcode( 'zw_gr26_gemeente_pagina', [ $gem_pagina, 'render' ] );

		// All other shortcodes only render inside [zw_gr26_pagina] or [zw_gr26_gemeente_pagina].
		$this->add_nested_shortcode( 'zw_gr26_livestream', [ $livestream, 'render' ] );
		$this->add_nested_shortcode( 'zw_gr26_debatten', [ $debatten, 'render' ] );
		$this->add_nested_shortcode( 'zw_gr26_debat', [ $debatten, 'render_debat' ] );
		$this->add_nested_shortcode( 'zw_gr26_explainers', [ $explainers, 'render' ] );
		$this->add_nested_shortcode( 'zw_gr26_explainer', [ $explainers, 'render_explainer' ] );
		$this->add_nested_shortcode( 'zw_gr26_nieuws', [ $nieuws, 'render' ] );
		$this->add_nested_shortcode( 'zw_gr26_podcast', [ $podcast, 'render' ] );
		$this->add_nested_shortcode( 'zw_gr26_programmas', [ $programmas, 'render' ] );
		$this->add_nested_shortcode( 'zw_gr26_resultaten', [ $resultaten, 'render' ] );
		$this->add_nested_shortcode( 'zw_gr26_stemlocaties', [ $stemlocaties, 'render' ] );
		$this->add_nested_shortcode( 'zw_gr26_tekst', [ $tekst, 'render' ] );
		$this->add_nested_shortcode( 'zw_gr26_gemeente_explainer', [ $gem_explainer, 'render' ] );
		$this->add_nested_shortcode( 'zw_gr26_gemeente_programmas', [ $gem_programmas, 'render' ] );
		$this->add_nested_shortcode( 'zw_gr26_gemeente_resultaten', [ $gem_resultaten, 'render' ] );
	}

	/**
	 * Registers a shortcode that only renders inside [zw_gr26_pagina].
	 *
	 * @param string   $tag      Shortcode tag.
	 * @param callable $callback Render callback.
	 */
	private function add_nested_shortcode( string $tag, callable $callback ): void {
		add_shortcode(
			$tag,
			static function ( $atts, $content = null ) use ( $callback ) {
				if ( ! Shortcode_Pagina::$active ) {
					return '';
				}
				return $callback( $atts, $content );
			}
		);
	}
}
