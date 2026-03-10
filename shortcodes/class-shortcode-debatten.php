<?php
/**
 * Debatten shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_debatten] and [zw_gr26_debat] — a manually curated grid of debate video cards.
 *
 * Uses instance properties for parent-child communication between nested shortcodes.
 */
class Shortcode_Debatten {

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
	 * Debates collected while processing child shortcodes.
	 *
	 * @var array
	 */
	private array $debatten = [];

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
	 * Renders the [zw_gr26_debatten] wrapper shortcode.
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Enclosed shortcode content.
	 * @return string Shortcode HTML output.
	 */
	public function render( $atts, ?string $content = null ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			[
				'titel'       => 'Debatten',
				'bibliotheek' => '',
			],
			$atts,
			'zw_gr26_debatten'
		);

		$library_id = (int) $atts['bibliotheek'];

		$this->debatten = [];

		do_shortcode( $content );

		if ( empty( $this->debatten ) ) {
			return '';
		}

		$html  = $this->renderer->section_open( $atts['titel'] );
		$html .= '<div class="zw-gr26-video-grid">';

		foreach ( $this->debatten as $debat ) {
			$coming_soon = empty( $debat['videoid'] );

			$video = [
				'titel'     => $debat['naam'],
				'thumbnail' => $debat['thumbnail'],
				'url'       => '',
				'meta'      => $debat['datum'] . ' &bull; ' . $debat['kanaal'],
			];

			if ( ! $coming_soon && $library_id ) {
				$info = $this->bunny->get_video_info( $library_id, $debat['videoid'] );
				if ( $info ) {
					if ( ! $video['thumbnail'] ) {
						$video['thumbnail'] = $info['thumbnail'];
					}
					$coming_soon = $info['binnenkort'];
				} else {
					$coming_soon = true;
				}

				if ( ! $coming_soon ) {
					$video['url'] = 'https://iframe.mediadelivery.net/play/'
						. $library_id . '/' . $debat['videoid'];
				}
			}

			$html .= $this->renderer->video_card( $video, $coming_soon );
		}

		$html .= '</div>';
		$html .= $this->renderer->section_close();

		$this->debatten = [];

		return $html;
	}

	/**
	 * Renders the [zw_gr26_debat] child shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Always empty — data is collected via instance properties.
	 */
	public function render_debat( $atts ): string {
		$atts = shortcode_atts(
			[
				'naam'      => '',
				'datum'     => '',
				'kanaal'    => '',
				'videoid'   => '',
				'thumbnail' => '',
			],
			$atts,
			'zw_gr26_debat'
		);

		if ( $atts['naam'] ) {
			$this->debatten[] = [
				'naam'      => $atts['naam'],
				'datum'     => $atts['datum'],
				'kanaal'    => $atts['kanaal'],
				'videoid'   => $atts['videoid'],
				'thumbnail' => $atts['thumbnail'],
			];
		}

		return '';
	}
}
