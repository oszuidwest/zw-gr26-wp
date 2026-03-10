<?php
/**
 * Verkiezingsprogramma shortcodes.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_programmas], [zw_gr26_gemeente], and [zw_gr26_partij].
 *
 * Uses instance properties for parent-child communication between nested shortcodes.
 */
class Shortcode_Programmas {

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
	 * Parties collected while processing the current [zw_gr26_gemeente].
	 *
	 * @var array
	 */
	private array $current_parties = [];

	/**
	 * Municipalities collected while processing [zw_gr26_programmas].
	 *
	 * @var array
	 */
	private array $gemeenten = [];

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
	 * Render the [zw_gr26_programmas] wrapper shortcode.
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Enclosed shortcode content.
	 * @return string
	 */
	public function render( $atts, ?string $content = null ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			[
				'titel' => "Verkiezingsprogramma's",
			],
			$atts,
			'zw_gr26_programmas'
		);

		$this->gemeenten = [];

		do_shortcode( $content );

		if ( empty( $this->gemeenten ) ) {
			return '';
		}

		$html  = $this->renderer->section_open( $atts['titel'] );
		$html .= '<nav class="zwv-programma" aria-label="' . esc_attr( $atts['titel'] ) . '">';

		// Municipality dropdown.
		$html .= '<div class="zwv-programma__select-wrap">';
		$html .= '<select class="zwv-programma__select" data-zw-gr26-programma-select>';
		$html .= '<option value="">Kies je gemeente...</option>';

		foreach ( $this->gemeenten as $gemeente ) {
			$id    = 'zwv-prog-' . sanitize_title( $gemeente['naam'] );
			$html .= '<option value="' . esc_attr( $id ) . '">' . esc_html( $gemeente['naam'] ) . '</option>';
		}

		$html .= '</select></div>';

		// Party lists per municipality.
		foreach ( $this->gemeenten as $gemeente ) {
			$id    = 'zwv-prog-' . sanitize_title( $gemeente['naam'] );
			$html .= '<div class="zwv-programma__list" id="' . esc_attr( $id ) . '">';

			foreach ( $gemeente['partijen'] as $partij ) {
				$html .= '<a href="' . esc_url( $partij['url'] ) . '" class="zwv-prow">';
				$html .= '<span class="zwv-prow__partij">' . esc_html( $partij['naam'] ) . '</span>';
				$html .= '<span class="zwv-prow__link-text">Lees programma</span>';
				$html .= '</a>';
			}

			$html .= '</div>';
		}

		$html .= '</nav>';
		$html .= $this->renderer->section_close();

		$this->gemeenten = [];

		return $html;
	}

	/**
	 * Render the [zw_gr26_gemeente] nested shortcode.
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Enclosed shortcode content.
	 * @return string Always empty — data is collected via instance properties.
	 */
	public function render_gemeente( $atts, ?string $content = null ): string {
		$atts = shortcode_atts(
			[
				'naam' => '',
			],
			$atts,
			'zw_gr26_gemeente'
		);

		if ( ! $atts['naam'] ) {
			return '';
		}

		$this->current_parties = [];

		do_shortcode( $content );

		$this->gemeenten[] = [
			'naam'     => $atts['naam'],
			'partijen' => $this->current_parties,
		];

		$this->current_parties = [];

		return '';
	}

	/**
	 * Render the [zw_gr26_partij] nested shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Always empty — data is collected via instance properties.
	 */
	public function render_partij( $atts ): string {
		$atts = shortcode_atts(
			[
				'naam' => '',
				'url'  => '#',
			],
			$atts,
			'zw_gr26_partij'
		);

		if ( $atts['naam'] ) {
			$this->current_parties[] = [
				'naam' => $atts['naam'],
				'url'  => $atts['url'],
			];
		}

		return '';
	}
}
