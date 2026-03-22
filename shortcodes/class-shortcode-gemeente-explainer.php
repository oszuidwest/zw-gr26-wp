<?php
declare( strict_types = 1 );
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

		$video = $this->bunny->resolve_video(
			$library_id,
			$atts['videoid'],
			[
				'titel'     => $atts['naam'],
				'thumbnail' => $atts['thumbnail'],
				'url'       => '',
			]
		);

		$tekst = ! empty( $content ) ? trim( $content ) : $atts['tekst'];

		$html  = $this->renderer->section_open( $atts['titel'] );
		$html .= $this->render_player( $video, $video['binnenkort'], $tekst );
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
			$html .= $this->renderer->img_tag(
				$video['thumbnail'],
				$video['titel'] ?? '',
				800,
				450,
				'zw-gr26-gem-explainer__thumb',
				'(max-width: 768px) 100vw, 800px'
			);
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
}
