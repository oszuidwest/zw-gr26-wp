<?php
/**
 * Debatten shortcode.
 *
 * @package ZWGR26
 */

declare( strict_types = 1 );

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
	 * Data provider.
	 *
	 * @var Data_Provider
	 */
	private Data_Provider $data;

	/**
	 * Debates collected while processing child shortcodes.
	 *
	 * @var array
	 */
	private array $debatten = [];

	/**
	 * Constructor.
	 *
	 * @param Assets        $assets   Asset manager.
	 * @param Renderer      $renderer Shared renderer.
	 * @param Bunny_API     $bunny    Bunny CDN API client.
	 * @param Data_Provider $data     Data provider.
	 */
	public function __construct( Assets $assets, Renderer $renderer, Bunny_API $bunny, Data_Provider $data ) {
		$this->assets   = $assets;
		$this->renderer = $renderer;
		$this->bunny    = $bunny;
		$this->data     = $data;
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

		$videos = $this->resolve_all( $library_id );

		// Gemeente context: spotlight layout.
		$active_gemeente = Shortcode_Pagina::$active_gemeente;
		if ( $active_gemeente ) {
			$html           = $this->render_spotlight( $atts['titel'], $videos, $active_gemeente );
			$this->debatten = [];
			return $html;
		}

		$html  = $this->renderer->section_open( $atts['titel'] );
		$html .= '<div class="zw-gr26-video-grid">';

		foreach ( $videos as $item ) {
			$html .= $this->renderer->video_card( $item['video'], $item['coming_soon'] );
		}

		$html .= '</div>';
		$html .= $this->renderer->section_close();

		$this->debatten = [];

		return $html;
	}

	/**
	 * Resolves all collected debates into video card data.
	 *
	 * @param int $library_id Bunny library ID.
	 * @return array[] List of arrays with 'video' and 'coming_soon' keys.
	 */
	private function resolve_all( int $library_id ): array {
		$videos = [];

		foreach ( $this->debatten as $debat ) {
			$video = $this->bunny->resolve_video(
				$library_id,
				$debat['videoid'],
				[
					'titel'     => $debat['naam'],
					'thumbnail' => $debat['thumbnail'],
					'url'       => '',
					'meta'      => $debat['datum'] . ' • ' . $debat['kanaal'],
				]
			);

			$videos[] = [
				'video'       => $video,
				'coming_soon' => $video['binnenkort'],
			];
		}

		return $videos;
	}

	/**
	 * Renders the spotlight layout: gemeente debate large on the left, others stacked right.
	 *
	 * @param string $titel           Section title.
	 * @param array  $videos          Resolved video items.
	 * @param string $active_gemeente Municipality slug.
	 * @return string Section HTML.
	 */
	private function render_spotlight( string $titel, array $videos, string $active_gemeente ): string {
		$municipalities = $this->data->get_all_municipalities();
		$gemeente_naam  = $municipalities[ $active_gemeente ] ?? '';

		// Find the main debate by matching the gemeente name in the title.
		$main_index = null;
		if ( $gemeente_naam ) {
			foreach ( $videos as $i => $item ) {
				if ( stripos( $item['video']['titel'], $gemeente_naam ) !== false ) {
					$main_index = $i;
					break;
				}
			}
		}

		// Fallback: first debate is the main one.
		if ( null === $main_index ) {
			$main_index = 0;
		}

		$main   = $videos[ $main_index ];
		$others = array_values( array_filter( $videos, fn( $v, $i ) => $i !== $main_index, ARRAY_FILTER_USE_BOTH ) );
		$others = array_slice( $others, 0, 5 );

		$html  = $this->renderer->section_open( $titel );
		$html .= '<div class="zw-gr26-gem-debatten">';

		// Main debate (large).
		$html .= '<div class="zw-gr26-gem-debatten__main">';
		$html .= $this->renderer->video_card( $main['video'], $main['coming_soon'] );
		$html .= '</div>';

		// Sidebar with other debates.
		if ( ! empty( $others ) ) {
			$html .= '<div class="zw-gr26-gem-debatten__sidebar">';
			foreach ( $others as $item ) {
				$html .= $this->renderer->video_card( $item['video'], $item['coming_soon'] );
			}
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= $this->renderer->section_close();

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
