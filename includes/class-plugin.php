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
	 * Return the singleton instance, creating it on first call.
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
	 * Construct dependencies and register shortcodes.
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
	 * Register all plugin shortcodes.
	 *
	 * @return void
	 */
	private function register_shortcodes(): void {
		$pagina       = new Shortcode_Pagina( $this->assets, $this->renderer );
		$livestream   = new Shortcode_Livestream( $this->assets, $this->renderer );
		$debatten     = new Shortcode_Debatten( $this->assets, $this->renderer, $this->bunny );
		$explainers   = new Shortcode_Explainers( $this->assets, $this->renderer, $this->bunny );
		$nieuws       = new Shortcode_Nieuws( $this->assets, $this->renderer, $this->data );
		$programmas   = new Shortcode_Programmas( $this->assets, $this->renderer, $this->data );
		$resultaten   = new Shortcode_Resultaten( $this->assets, $this->renderer, $this->data );
		$stemlocaties = new Shortcode_Stemlocaties( $this->assets, $this->renderer, $this->data );
		$tekst        = new Shortcode_Tekst( $this->assets, $this->renderer );

		add_shortcode( 'zw_gr26_pagina', [ $pagina, 'render' ] );
		add_shortcode( 'zw_gr26_livestream', [ $livestream, 'render' ] );
		add_shortcode( 'zw_gr26_debatten', [ $debatten, 'render' ] );
		add_shortcode( 'zw_gr26_debat', [ $debatten, 'render_debat' ] );
		add_shortcode( 'zw_gr26_explainers', [ $explainers, 'render' ] );
		add_shortcode( 'zw_gr26_explainer', [ $explainers, 'render_explainer' ] );
		add_shortcode( 'zw_gr26_nieuws', [ $nieuws, 'render' ] );
		add_shortcode( 'zw_gr26_programmas', [ $programmas, 'render' ] );
		add_shortcode( 'zw_gr26_resultaten', [ $resultaten, 'render' ] );
		add_shortcode( 'zw_gr26_stemlocaties', [ $stemlocaties, 'render' ] );
		add_shortcode( 'zw_gr26_tekst', [ $tekst, 'render' ] );
	}
}
