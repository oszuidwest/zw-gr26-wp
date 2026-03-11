<?php
/**
 * Explainers shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_explainers] and [zw_gr26_explainer] — a manually curated carousel of explainer videos.
 *
 * Uses instance properties for parent-child communication between nested shortcodes.
 */
class Shortcode_Explainers {

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
	 * Bunny CDN API client.
	 *
	 * @var Bunny_API
	 */
	private Bunny_API $bunny;

	/**
	 * Explainers collected while processing child shortcodes.
	 *
	 * @var array
	 */
	private array $explainers = [];

	/**
	 * Constructor.
	 *
	 * @param Assets    $assets   Asset manager.
	 * @param Renderer  $renderer Shared renderer.
	 * @param Bunny_API $bunny    Bunny CDN API client.
	 */
	public function __construct( Assets $assets, Renderer $renderer, Bunny_API $bunny ) {
		$this->assets   = $assets;
		$this->renderer = $renderer;
		$this->bunny    = $bunny;
	}

	/**
	 * Renders the [zw_gr26_explainers] wrapper shortcode.
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Enclosed shortcode content.
	 * @return string Shortcode HTML output.
	 */
	public function render( $atts, ?string $content = null ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			[
				'titel'       => 'Explainers',
				'bibliotheek' => '',
			],
			$atts,
			'zw_gr26_explainers'
		);

		$library_id = (int) $atts['bibliotheek'];

		$this->explainers = [];

		do_shortcode( $content );

		if ( empty( $this->explainers ) ) {
			return '';
		}

		$html  = $this->renderer->section_open( $atts['titel'] );
		$html .= '<div class="zw-gr26-explainer-carousel">';

		foreach ( $this->explainers as $explainer ) {
			$coming_soon = empty( $explainer['videoid'] );

			$video = [
				'titel'     => $explainer['naam'],
				'thumbnail' => $explainer['thumbnail'],
				'url'       => '',
			];

			if ( ! $coming_soon && $library_id ) {
				$resolved = $this->bunny->resolve_video_card( $library_id, $explainer['videoid'], $video['thumbnail'] );

				$video['thumbnail'] = $resolved['thumbnail'];
				$video['url']       = $resolved['url'];
				$coming_soon        = $resolved['binnenkort'];
			}

			$video['binnenkort'] = $coming_soon;

			$html .= $this->renderer->explainer_card( $video );
		}

		$html .= '</div>';
		$html .= $this->renderer->section_close();

		$this->explainers = [];

		return $html;
	}

	/**
	 * Renders the [zw_gr26_explainer] child shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Always empty — data is collected via instance properties.
	 */
	public function render_explainer( $atts ): string {
		$atts = shortcode_atts(
			[
				'naam'      => '',
				'videoid'   => '',
				'thumbnail' => '',
			],
			$atts,
			'zw_gr26_explainer'
		);

		if ( $atts['naam'] ) {
			$this->explainers[] = [
				'naam'      => $atts['naam'],
				'videoid'   => $atts['videoid'],
				'thumbnail' => $atts['thumbnail'],
			];
		}

		return '';
	}
}
