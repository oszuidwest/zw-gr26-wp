<?php
/**
 * Nieuws shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_nieuws] — a grid of recent news articles from a dossier.
 */
class Shortcode_Nieuws {

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
				'titel'   => 'Laatste nieuws',
				'dossier' => '',
				'aantal'  => 6,
				'link'    => '',
				'regio'   => '',
			],
			$atts,
			'zw_gr26_nieuws'
		);

		// Auto-fill regio from gemeente context when not explicitly set.
		if ( ! $atts['regio'] && Shortcode_Pagina::$active_gemeente ) {
			$atts['regio'] = Shortcode_Pagina::$active_gemeente;
		}

		if ( ! $atts['dossier'] && ! $atts['regio'] ) {
			return '<!-- zw_gr26_nieuws: dossier of regio niet opgegeven -->';
		}

		$items = $this->data->get_dossier_posts( $atts['dossier'], (int) $atts['aantal'], $atts['regio'] );

		if ( empty( $items ) ) {
			return '<!-- zw_gr26_nieuws: geen posts gevonden -->';
		}

		$link = $atts['link'];
		if ( ! $link && $atts['dossier'] && taxonomy_exists( 'dossier' ) ) {
			$term_link = get_term_link( $atts['dossier'], 'dossier' );
			if ( ! is_wp_error( $term_link ) ) {
				$link = $term_link;
			}
		}

		$html = $this->renderer->section_open(
			$atts['titel'],
			$link,
			'Alle nieuws'
		);

		$html .= '<div class="zw-gr26-article-grid">';

		foreach ( $items as $item ) {
			$html .= $this->renderer->article_card( $item );
		}

		$html .= '</div>';
		$html .= $this->renderer->section_close();

		return $html;
	}
}
