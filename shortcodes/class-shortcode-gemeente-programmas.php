<?php
/**
 * Gemeente programma's shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_gemeente_programmas] — a flat list of party program links for one municipality.
 *
 * Reads the active gemeente from Shortcode_Pagina::$active_gemeente.
 */
class Shortcode_Gemeente_Programmas {

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
	 * @param array|string $atts Shortcode attributes.
	 * @return string Shortcode HTML output.
	 */
	public function render( $atts ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			[
				'titel' => "Verkiezingsprogramma's",
			],
			$atts,
			'zw_gr26_gemeente_programmas'
		);

		$slug = Shortcode_Pagina::$active_gemeente;
		if ( ! $slug ) {
			return '<!-- zw_gr26_gemeente_programmas: geen gemeente context -->';
		}

		$partijen = $this->data->get_programmas_for( $slug );
		if ( empty( $partijen ) ) {
			return '<!-- zw_gr26_gemeente_programmas: geen partijen gevonden -->';
		}

		$html  = $this->renderer->section_open( $atts['titel'] );
		$html .= '<nav class="zw-gr26-programma" aria-label="' . esc_attr( $atts['titel'] ) . '">';
		$html .= '<div class="zw-gr26-programma__list zw-gr26-programma__list--open">';

		foreach ( $partijen as $partij ) {
			if ( $partij['url'] ) {
				$html .= '<a href="' . esc_url( $partij['url'] ) . '" class="zw-gr26-prow" target="_blank" rel="noopener noreferrer">';
				$html .= '<span class="zw-gr26-prow__partij">' . esc_html( $partij['naam'] ) . '</span>';
				$html .= '<span class="zw-gr26-prow__link-text">Lees programma</span>';
				$html .= '</a>';
			} else {
				$html .= '<div class="zw-gr26-prow zw-gr26-prow--disabled">';
				$html .= '<span class="zw-gr26-prow__partij">' . esc_html( $partij['naam'] ) . '</span>';
				$html .= '<span class="zw-gr26-prow__link-text">Geen programma</span>';
				$html .= '</div>';
			}
		}

		$html .= '</div>';
		$html .= '</nav>';
		$html .= $this->renderer->section_close();

		return $html;
	}
}
