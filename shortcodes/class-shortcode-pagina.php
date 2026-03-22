<?php
declare( strict_types = 1 );
/**
 * Wrapper shortcode for the election page.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_pagina] — the full-page wrapper with hero, stripe, and inner shortcodes.
 */
class Shortcode_Pagina {

	/**
	 * Whether the pagina shortcode is currently rendering.
	 *
	 * Child shortcodes check this flag and return empty output
	 * when used outside [zw_gr26_pagina].
	 *
	 * @var bool
	 */
	public static bool $active = false;

	/**
	 * The currently active municipality slug, set by [zw_gr26_gemeente_pagina].
	 *
	 * Child shortcodes read this to scope their output to a single municipality.
	 * Null when rendering the main page (no municipality context).
	 *
	 * @var string|null
	 */
	public static ?string $active_gemeente = null;

	/**
	 * Whether the video modal has already been added to the footer.
	 *
	 * @var bool
	 */
	public static bool $video_modal_added = false;

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
				'titel'       => 'ZuidWest Kiest',
				'achtergrond' => 'https://www.zuidwestupdate.nl/wp-content/uploads/2022/03/potlood.jpg',
			],
			$atts,
			'zw_gr26_pagina'
		);

		$was_active   = self::$active;
		self::$active = true;
		try {
			$inner = do_shortcode( $content );
		} finally {
			self::$active = $was_active;
		}

		$inner = Renderer::clean_shortcode_html( $inner );
		Renderer::video_modal();

		$gemeente_pages = $this->data->get_gemeente_pages();
		$main_page_url  = (string) get_permalink();
		$nav            = $this->renderer->gemeente_nav( 'West-Brabant', null, $gemeente_pages, $main_page_url );
		$subtitle       = "Alles over de gemeente\xC2\xADraads\xC2\xADverkiezingen van 2026 in " . $nav;

		$html  = '<main class="zw-gr26-wrapper not-prose">';
		$html .= $this->renderer->hero(
			$atts['titel'],
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
