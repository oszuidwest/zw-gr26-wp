<?php
/**
 * Podcast shortcode.
 *
 * @package ZWGR26
 */

declare( strict_types = 1 );

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_podcast] — a podcast promotion card with polaroid-stack cover art.
 */
class Shortcode_Podcast {

	/**
	 * Instance counter for scoping cover data per shortcode.
	 *
	 * @var int
	 */
	private static int $instance_count = 0;

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
	 * Data provider for podcast feed.
	 *
	 * @var Data_Provider
	 */
	private Data_Provider $data;

	/**
	 * Image proxy service.
	 *
	 * @var Image_Proxy
	 */
	private Image_Proxy $proxy;

	/**
	 * Constructor.
	 *
	 * @param Assets        $assets   Asset manager.
	 * @param Renderer      $renderer Shared renderer.
	 * @param Data_Provider $data     Data provider.
	 * @param Image_Proxy   $proxy    Image proxy service.
	 */
	public function __construct( Assets $assets, Renderer $renderer, Data_Provider $data, Image_Proxy $proxy ) {
		$this->assets   = $assets;
		$this->renderer = $renderer;
		$this->data     = $data;
		$this->proxy    = $proxy;
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
				'titel'        => 'Podcast',
				'naam'         => 'Het Fractiehuis',
				'label'        => '',
				'beschrijving' => '',
				'feed'         => '',
				'filter'       => '',
				'spotify'      => '',
				'apple'        => '',
			],
			$atts,
			'zw_gr26_podcast'
		);

		if ( empty( $atts['feed'] ) ) {
			return '';
		}

		$covers = $this->data->get_podcast_covers( $atts['feed'], $atts['filter'] );

		++self::$instance_count;
		$instance_id = self::$instance_count;

		$html = $this->renderer->section_open( $atts['titel'] );

		$html .= '<div class="zw-gr26-podcast__card" data-podcast-id="' . esc_attr( (string) $instance_id ) . '">';

		// Polaroid stack — render with srcset via Renderer::img_tag().
		$html .= '<div class="zw-gr26-podcast__polaroids">';
		for ( $i = 0; $i < 3; $i++ ) {
			$cover_url = isset( $covers[ $i ] ) ? $covers[ $i ] : '';
			$html     .= '<div class="zw-gr26-podcast__polaroid">';
			$html     .= $this->renderer->img_tag( $cover_url, 'Aflevering', 110, 110 );
			$html     .= '</div>';
		}
		$html .= '</div>';

		// Info section.
		$html .= '<div class="zw-gr26-podcast__info">';

		if ( ! empty( $atts['label'] ) ) {
			$html .= '<div class="zw-gr26-podcast__label">' . esc_html( $atts['label'] ) . '</div>';
		}

		$html .= '<div class="zw-gr26-podcast__title">' . esc_html( $atts['naam'] ) . '</div>';

		if ( ! empty( $atts['beschrijving'] ) ) {
			$html .= '<div class="zw-gr26-podcast__desc">' . wp_kses_post( $atts['beschrijving'] ) . '</div>';
		}

		// Buttons.
		$html .= '<div class="zw-gr26-podcast__buttons">';

		if ( ! empty( $atts['spotify'] ) ) {
			$html .= $this->render_button(
				$atts['spotify'],
				'spotify',
				'Spotify'
			);
		}

		if ( ! empty( $atts['apple'] ) ) {
			$html .= $this->render_button(
				$atts['apple'],
				'apple',
				'Apple Podcasts'
			);
		}

		$html .= '</div>'; // buttons.
		$html .= '</div>'; // info.
		$html .= '</div>'; // card.

		$html .= $this->renderer->section_close();

		// Pass covers to JS for shuffle, scoped per instance.
		if ( ! empty( $covers ) ) {
			$js_covers = [];
			foreach ( $covers as $url ) {
				$src_1x      = $this->proxy->url( $url, 110, 110 );
				$src_2x      = $this->proxy->url( $url, 220, 220 );
				$js_covers[] = [
					'src'    => $src_1x,
					'src2x'  => $src_2x,
					'srcset' => $src_1x . ' 110w, ' . $src_2x . ' 220w',
				];
			}
			wp_add_inline_script(
				'zw-gr26',
				'window.zwGr26PodcastInstances = window.zwGr26PodcastInstances || {};'
				. ' zwGr26PodcastInstances[' . $instance_id . '] = ' . wp_json_encode( $js_covers ) . ';',
				'before'
			);
		}

		return $html;
	}

	/**
	 * Renders a podcast platform button with icon.
	 *
	 * @param string $url      Platform URL.
	 * @param string $platform Platform key ('spotify' or 'apple').
	 * @param string $label    Button label text.
	 * @return string Button HTML.
	 */
	private function render_button( string $url, string $platform, string $label ): string {
		$class = 'zw-gr26-podcast__btn zw-gr26-podcast__btn--' . $platform;

		$html  = '<a href="' . esc_url( $url ) . '"';
		$html .= ' class="' . esc_attr( $class ) . '"';
		$html .= ' target="_blank" rel="noopener noreferrer">';
		$html .= Icons::get( $platform );
		$html .= esc_html( $label );
		$html .= '</a>';

		return $html;
	}
}
