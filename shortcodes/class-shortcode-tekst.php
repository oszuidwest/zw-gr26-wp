<?php
/**
 * Text block shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_tekst] — a styled text block with optional section title.
 */
class Shortcode_Tekst {

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
	 * Constructor.
	 *
	 * @param Assets   $assets   Asset manager.
	 * @param Renderer $renderer Shared renderer.
	 */
	public function __construct( Assets $assets, Renderer $renderer ) {
		$this->assets   = $assets;
		$this->renderer = $renderer;
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Enclosed shortcode content (HTML allowed).
	 * @return string
	 */
	public function render( $atts, ?string $content = null ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			[
				'titel' => '',
			],
			$atts,
			'zw_gr26_tekst'
		);

		$html  = $atts['titel'] ? $this->renderer->section_open( $atts['titel'] ) : '<section class="zwv-section">';
		$html .= '<div class="zwv-intro">';
		$html .= wp_kses_post( wpautop( do_shortcode( $content ) ) );
		$html .= '</div>';
		$html .= $this->renderer->section_close();

		return $html;
	}
}
