<?php
/**
 * Gemeente explainer shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_gemeente_explainer] — a single 16:9 explainer video.
 */
class Shortcode_Gemeente_Explainer {

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
	 * Image proxy.
	 *
	 * @var Image_Proxy
	 */
	private Image_Proxy $proxy;

	/**
	 * Constructor.
	 *
	 * @param Assets      $assets   Asset manager.
	 * @param Renderer    $renderer Shared renderer.
	 * @param Bunny_API   $bunny    Bunny CDN API client.
	 * @param Image_Proxy $proxy    Image proxy.
	 */
	public function __construct( Assets $assets, Renderer $renderer, Bunny_API $bunny, Image_Proxy $proxy ) {
		$this->assets   = $assets;
		$this->renderer = $renderer;
		$this->bunny    = $bunny;
		$this->proxy    = $proxy;
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Enclosed shortcode content (used as text panel).
	 * @return string Shortcode HTML output.
	 */
	public function render( $atts, ?string $content = null ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			[
				'titel'       => 'Explainer',
				'videoid'     => '',
				'naam'        => '',
				'bibliotheek' => '',
				'thumbnail'   => '',
				'tekst'       => 'In deze video leggen we in een paar minuten uit hoe de gemeenteraad werkt,'
					. ' wat er op het spel staat bij de verkiezingen en waarom jouw stem ertoe doet.',
			],
			$atts,
			'zw_gr26_gemeente_explainer'
		);

		if ( ! $atts['videoid'] ) {
			return '';
		}

		$library_id = (int) $atts['bibliotheek'];

		$video = [
			'titel'     => $atts['naam'],
			'thumbnail' => $atts['thumbnail'],
			'url'       => '',
		];

		$coming_soon = false;
		if ( $library_id ) {
			$resolved = $this->bunny->resolve_video_card( $library_id, $atts['videoid'], $video['thumbnail'] );

			$video['thumbnail']  = $resolved['thumbnail'];
			$video['poster']     = $resolved['poster'];
			$video['url']        = $resolved['url'];
			$video['stream_url'] = $resolved['stream_url'];
			$coming_soon         = $resolved['binnenkort'];
		}

		$tekst = ! empty( $content ) ? trim( $content ) : $atts['tekst'];

		$html  = $this->renderer->section_open( $atts['titel'] );
		$html .= $this->render_player( $video, $coming_soon, $tekst );
		$html .= $this->renderer->section_close();

		return $html;
	}

	/**
	 * Renders the explainer player with background and text panel.
	 *
	 * @param array  $video      Video data with thumbnail, url, stream_url, poster.
	 * @param bool   $coming_soon Whether the video is upcoming.
	 * @param string $tekst      Descriptive text shown next to the video.
	 * @return string Player HTML.
	 */
	private function render_player( array $video, bool $coming_soon, string $tekst ): string {
		$has_thumb  = ! empty( $video['thumbnail'] );
		$poster_url = ! empty( $video['poster'] ) ? $video['poster'] : ( $video['thumbnail'] ?? '' );
		$stream     = ! empty( $video['stream_url'] ) ? ' data-stream="' . esc_url( $video['stream_url'] ) . '"' : '';
		$poster     = $poster_url ? ' data-poster="' . esc_url( $poster_url ) . '"' : '';

		$html = '<div class="zw-gr26-gem-explainer">';

		$html .= '<div class="zw-gr26-gem-explainer__grid">';

		// Video column.
		$tag   = $coming_soon ? 'div' : 'a';
		$href  = $coming_soon ? '' : ' href="' . esc_url( $video['url'] ) . '"';
		$class = 'zw-gr26-gem-explainer__inner' . ( $coming_soon ? '' : ' zw-gr26-vcard__link' );

		$html .= '<' . $tag . $href . $stream . $poster . ' class="' . $class . '">';

		if ( $has_thumb ) {
			$html .= $this->img_tag( $video['thumbnail'], $video['titel'] ?? '' );
		}

		if ( $coming_soon ) {
			$html .= '<span class="zw-gr26-binnenkort__badge">Binnenkort</span>';
		} else {
			$html .= '<span class="zw-gr26-gem-explainer__play">&#9654;&#xFE0E;</span>';
		}

		$html .= '</' . $tag . '>';

		// Text column.
		if ( $tekst ) {
			$html .= '<div class="zw-gr26-gem-explainer__text">'
				. '<p>' . wp_kses_post( $tekst ) . '</p>'
				. '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Renders a responsive image tag with srcset via the image proxy.
	 *
	 * @param string $src Source URL.
	 * @param string $alt Alt text.
	 * @return string Image HTML.
	 */
	private function img_tag( string $src, string $alt ): string {
		$width  = 800;
		$height = 450;
		$src_1x = $this->proxy->url( $src, $width, $height );
		$src_2x = $this->proxy->url( $src, $width * 2, $height * 2 );

		$html = '<img class="zw-gr26-gem-explainer__thumb" src="' . esc_url( $src_1x ) . '"';

		if ( $src_2x !== $src_1x ) {
			$html .= ' srcset="' . esc_url( $src_1x ) . " {$width}w, "
				. esc_url( $src_2x ) . ' ' . ( $width * 2 ) . 'w"'
				. ' sizes="(max-width: 768px) 100vw, 800px"';
		}

		$html .= ' width="' . $width . '" height="' . $height . '"';
		$html .= ' alt="' . esc_attr( $alt ) . '" loading="lazy" />';

		return $html;
	}
}
