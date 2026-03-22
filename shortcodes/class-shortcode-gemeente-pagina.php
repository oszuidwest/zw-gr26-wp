<?php
declare( strict_types = 1 );
/**
 * Gemeente page wrapper shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_gemeente_pagina] — a full-page wrapper for a single municipality.
 *
 * Sets the active gemeente context so child shortcodes can scope their output.
 */
class Shortcode_Gemeente_Pagina {

	/**
	 * Asset manager.
	 *
	 * @var Assets
	 */
	private Assets $assets;

	/**
	 * Shared renderer.
	 *
	 * @var Renderer
	 */
	private Renderer $renderer;

	/**
	 * Data provider.
	 *
	 * @var Data_Provider
	 */
	private Data_Provider $data;

	/**
	 * Constructor.
	 *
	 * @param Assets        $assets   Asset manager.
	 * @param Renderer      $renderer Shared renderer.
	 * @param Data_Provider $data     Data provider.
	 */
	public function __construct( Assets $assets, Renderer $renderer, Data_Provider $data ) {
		$this->assets   = $assets;
		$this->renderer = $renderer;
		$this->data     = $data;
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Enclosed shortcode content.
	 * @return string Shortcode HTML output.
	 */
	public function render( $atts, ?string $content = null ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			[
				'gemeente'    => '',
				'titel'       => '',
				'achtergrond' => 'https://www.zuidwestupdate.nl/wp-content/uploads/2022/03/potlood.jpg',
			],
			$atts,
			'zw_gr26_gemeente_pagina'
		);

		$slug = sanitize_title( $atts['gemeente'] );
		if ( ! $slug ) {
			return '<!-- zw_gr26_gemeente_pagina: gemeente niet opgegeven -->';
		}

		// Validate against known municipalities.
		$municipalities = $this->data->get_all_municipalities();
		if ( ! isset( $municipalities[ $slug ] ) ) {
			return '<!-- zw_gr26_gemeente_pagina: onbekende gemeente -->';
		}

		$naam = $municipalities[ $slug ];

		$was_active   = Shortcode_Pagina::$active;
		$was_gemeente = Shortcode_Pagina::$active_gemeente;

		Shortcode_Pagina::$active          = true;
		Shortcode_Pagina::$active_gemeente = $slug;

		try {
			$inner = do_shortcode( $content );
		} finally {
			Shortcode_Pagina::$active          = $was_active;
			Shortcode_Pagina::$active_gemeente = $was_gemeente;
		}

		$inner = Renderer::clean_shortcode_html( $inner );
		Renderer::video_modal();

		$gemeente_pages = $this->data->get_gemeente_pages();
		$main_page_url  = $this->data->get_main_page_url();
		$nav            = $this->renderer->gemeente_nav( $naam, $slug, $gemeente_pages, $main_page_url );
		$subtitle       = "Alles over de gemeente\xC2\xADraads\xC2\xADverkiezingen van 2026 in " . $nav;

		$html  = '<main class="zw-gr26-wrapper not-prose">';
		$titel = $atts['titel'] ? $atts['titel'] : $naam;

		$html .= $this->renderer->hero(
			$titel,
			$subtitle,
			$atts['achtergrond']
		);
		$html .= $this->renderer->stripe();
		$html .= '<div class="zw-gr26-container">';
		$html .= $inner;
		$html .= '</div></main>';

		return $html;
	}
}
