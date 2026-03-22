<?php
/**
 * Shared HTML rendering helpers.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds reusable HTML fragments for sections, cards, and the hero header.
 */
class Renderer {

	/**
	 * Image proxy for on-the-fly resizing.
	 *
	 * @var Image_Proxy
	 */
	private Image_Proxy $proxy;

	/**
	 * Constructor.
	 *
	 * @param Image_Proxy $proxy Image proxy service.
	 */
	public function __construct( Image_Proxy $proxy ) {
		$this->proxy = $proxy;
	}

	/**
	 * Builds an img tag with proxy URLs, responsive srcset, and sizes.
	 *
	 * When imgproxy is configured, generates srcset with w-descriptors at 1x
	 * and 2x of the base width. The browser uses the sizes attribute to pick
	 * the best variant for the current viewport and device pixel ratio.
	 *
	 * When imgproxy is not configured, outputs a plain img tag with the original URL.
	 *
	 * @param string $src       Original image URL.
	 * @param string $alt       Alt text.
	 * @param int    $width     Base display width in pixels (1x).
	 * @param int    $height    Base display height in pixels (1x).
	 * @param string $css_class Optional. CSS class. Default empty string.
	 * @param string $sizes     Optional. CSS sizes attribute for responsive selection. Default empty string.
	 * @return string HTML img element.
	 */
	public function img_tag( string $src, string $alt, int $width, int $height, string $css_class = '', string $sizes = '' ): string {
		$src_1x = $this->proxy->url( $src, $width, $height );
		$src_2x = $this->proxy->url( $src, $width * 2, $height * 2 );

		$html = '<img';
		if ( $css_class ) {
			$html .= ' class="' . esc_attr( $css_class ) . '"';
		}
		$html .= ' src="' . esc_url( $src_1x ) . '"';

		if ( $src_2x !== $src_1x ) {
			$w1    = $width;
			$w2    = $width * 2;
			$html .= ' srcset="' . esc_url( $src_1x ) . " {$w1}w, "
				. esc_url( $src_2x ) . " {$w2}w\"";
			if ( $sizes ) {
				$html .= ' sizes="' . esc_attr( $sizes ) . '"';
			}
		}

		$html .= ' width="' . $width . '" height="' . $height . '"';
		$html .= ' alt="' . esc_attr( $alt ) . '" loading="lazy" />';

		return $html;
	}

	/**
	 * Opens a content section with a title and optional link.
	 *
	 * @param string $title     Section heading.
	 * @param string $link      Optional. URL for the "more" link. Default empty string.
	 * @param string $link_text Optional. Label for the "more" link. Default empty string.
	 * @return string Opening section HTML.
	 */
	public function section_open( string $title, string $link = '', string $link_text = '' ): string {
		$id    = sanitize_title( $title );
		$html  = '<section class="zw-gr26-section" id="' . esc_attr( $id ) . '">';
		$html .= '<div class="zw-gr26-section__header">';
		$html .= '<h2 class="zw-gr26-section__title">' . esc_html( $title ) . '</h2>';

		if ( $link ) {
			$html .= '<a href="' . esc_url( $link ) . '" class="zw-gr26-section__link">'
				. esc_html( $link_text ? $link_text : 'Bekijk meer' ) . ' &rarr;</a>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Closes a content section.
	 *
	 * @return string Closing section tag.
	 */
	public function section_close(): string {
		return '</section>';
	}

	/**
	 * Renders the hero header with background image.
	 *
	 * The subtitle may contain pre-escaped HTML (e.g. the gemeente navigation dropdown).
	 *
	 * @param string $title         Main heading.
	 * @param string $subtitle_html Pre-escaped subtitle HTML.
	 * @param string $bg_image      Background image URL.
	 * @return string Hero header HTML.
	 */
	public function hero( string $title, string $subtitle_html, string $bg_image ): string {
		$html  = '<header class="zw-gr26-hero">';
		$html .= '<div class="zw-gr26-hero__bg" style="background-image:url(' . esc_url( $this->proxy->url( $bg_image, 1920, 1080 ) ) . ')"></div>';
		$html .= '<div class="zw-gr26-hero__content">';
		$html .= '<h1 class="zw-gr26-hero__title">' . esc_html( $title ) . '</h1>';

		if ( $subtitle_html ) {
			$html .= '<p class="zw-gr26-hero__subtitle">' . $subtitle_html . '</p>';
		}

		$html .= '</div></header>';

		return $html;
	}

	/**
	 * Renders a gemeente navigation dropdown for use in the hero subtitle.
	 *
	 * @param string      $label          Visible label (e.g. "West-Brabant" or municipality name).
	 * @param string|null $current_slug   Current municipality slug, or null for main page.
	 * @param array       $gemeente_pages List of gemeente pages from Data_Provider::get_gemeente_pages().
	 * @param string      $main_page_url  URL of the main overview page.
	 * @return string Dropdown HTML.
	 */
	public function gemeente_nav( string $label, ?string $current_slug, array $gemeente_pages, string $main_page_url = '' ): string {
		if ( empty( $gemeente_pages ) ) {
			return esc_html( $label );
		}

		$html = '<span class="zw-gr26-hero__nav">';

		$html .= '<button class="zw-gr26-hero__nav-trigger" type="button" aria-expanded="false">';
		$html .= esc_html( $label ) . ' <span class="zw-gr26-hero__nav-arrow">' . Icons::get( 'chevron-down' ) . '</span>';
		$html .= '</button>';

		$html .= '<span class="zw-gr26-hero__nav-dropdown" role="list">';

		// Main page link (West-Brabant overview).
		if ( null === $current_slug && $main_page_url ) {
			$html .= '<span class="zw-gr26-hero__nav-item zw-gr26-hero__nav-item--current" role="listitem">'
				. 'West-Brabant</span>';
		} elseif ( $main_page_url ) {
			$html .= '<a href="' . esc_url( $main_page_url ) . '" class="zw-gr26-hero__nav-item" role="listitem">'
				. 'West-Brabant</a>';
		}

		foreach ( $gemeente_pages as $page ) {
			if ( $page['slug'] === $current_slug ) {
				$html .= '<span class="zw-gr26-hero__nav-item zw-gr26-hero__nav-item--current" role="listitem">'
					. esc_html( $page['naam'] ) . '</span>';
			} else {
				$html .= '<a href="' . esc_url( $page['url'] ) . '" class="zw-gr26-hero__nav-item" role="listitem">'
					. esc_html( $page['naam'] ) . '</a>';
			}
		}
		$html .= '</span>';
		$html .= '</span>';

		return $html;
	}

	/**
	 * Renders the red-white-blue decorative stripe.
	 *
	 * @return string Stripe HTML.
	 */
	public function stripe(): string {
		return '<div class="zw-gr26-stripe">'
			. '<span class="zw-gr26-stripe--red"></span>'
			. '<span class="zw-gr26-stripe--white"></span>'
			. '<span class="zw-gr26-stripe--blue"></span>'
			. '</div>';
	}

	/**
	 * Renders a video card with thumbnail, title, and optional coming-soon state.
	 *
	 * @param array $video {
	 *     Video data.
	 *
	 *     @type string $titel      Video title.
	 *     @type string $thumbnail  Thumbnail URL.
	 *     @type string $url        Video player page URL (fallback for no-JS).
	 *     @type string $stream_url Optional. HLS stream URL for inline playback.
	 *     @type string $meta       Optional. Meta text.
	 * }
	 * @param bool  $coming_soon Whether the video is not yet available.
	 * @return string Video card HTML.
	 */
	public function video_card( array $video, bool $coming_soon = false ): string {
		$tag        = $coming_soon ? 'div' : 'a';
		$href       = $coming_soon ? '' : ' href="' . esc_url( $video['url'] ) . '"';
		$stream     = ! empty( $video['stream_url'] ) ? ' data-stream="' . esc_url( $video['stream_url'] ) . '"' : '';
		$poster_url = ! empty( $video['poster'] ) ? $video['poster'] : ( $video['thumbnail'] ?? '' );
		$poster     = $poster_url ? ' data-poster="' . esc_url( $poster_url ) . '"' : '';

		$has_thumb = ! empty( $video['thumbnail'] );

		$html  = '<article class="zw-gr26-vcard' . ( $coming_soon ? ' zw-gr26-binnenkort' : '' ) . '">';
		$html .= '<' . $tag . $href . $stream . $poster . ' class="zw-gr26-vcard__link">';

		if ( $has_thumb ) {
			$html .= $this->img_tag(
				$video['thumbnail'],
				$video['titel'],
				300,
				188,
				'zw-gr26-cover-img',
				'(max-width: 480px) calc(100vw - 40px), (max-width: 768px) calc(50vw - 28px), 300px'
			);
		} else {
			$html .= '<div class="zw-gr26-cover-img zw-gr26-vcard__placeholder"></div>';
		}

		if ( $coming_soon ) {
			$html .= '<span class="zw-gr26-binnenkort__badge">Binnenkort</span>';
		} else {
			$html .= '<div class="zw-gr26-vcard__play"><span>&#9654;&#xFE0E;</span> Bekijk</div>';
		}

		$html .= '<div class="zw-gr26-vcard__overlay">';
		$html .= '<h3 class="zw-gr26-vcard__title">' . esc_html( $video['titel'] ) . '</h3>';

		if ( ! empty( $video['meta'] ) ) {
			$html .= '<div class="zw-gr26-vcard__meta">' . esc_html( $video['meta'] ) . '</div>';
		}

		$html .= '</div>';
		$html .= '</' . $tag . '>';
		$html .= '</article>';

		return $html;
	}

	/**
	 * Renders an explainer card (9:16 aspect ratio).
	 *
	 * @param array $video {
	 *     Video data.
	 *
	 *     @type string $titel      Video title.
	 *     @type string $thumbnail  Thumbnail URL.
	 *     @type string $url        Video player page URL (fallback for no-JS).
	 *     @type string $stream_url Optional. HLS stream URL for inline playback.
	 *     @type bool   $binnenkort Optional. Whether the video is upcoming.
	 * }
	 * @return string Explainer card HTML.
	 */
	public function explainer_card( array $video ): string {
		$coming_soon = ! empty( $video['binnenkort'] );
		$has_thumb   = ! empty( $video['thumbnail'] );
		$tag         = $coming_soon ? 'div' : 'a';
		$href        = $coming_soon ? '' : ' href="' . esc_url( $video['url'] ) . '"';
		$stream      = ! empty( $video['stream_url'] ) ? ' data-stream="' . esc_url( $video['stream_url'] ) . '"' : '';
		$poster_url  = ! empty( $video['poster'] ) ? $video['poster'] : ( $video['thumbnail'] ?? '' );
		$poster      = $poster_url ? ' data-poster="' . esc_url( $poster_url ) . '"' : '';

		$html  = '<article class="zw-gr26-ecard' . ( $coming_soon ? ' zw-gr26-ecard--binnenkort' : '' ) . '">';
		$html .= '<' . $tag . $href . $stream . $poster . ' class="zw-gr26-ecard__link">';

		if ( $has_thumb ) {
			$html .= $this->img_tag(
				$video['thumbnail'],
				$video['titel'],
				200,
				356,
				'zw-gr26-cover-img',
				'clamp(150px, 8vw + 110px, 200px)'
			);
		}

		if ( $coming_soon ) {
			$html .= '<span class="zw-gr26-binnenkort__badge">Binnenkort</span>';
		}

		$html .= '<div class="zw-gr26-ecard__info">';
		$html .= '<h3 class="zw-gr26-ecard__title">' . esc_html( $video['titel'] ) . '</h3>';
		$html .= '</div>';
		$html .= '</' . $tag . '>';
		$html .= '</article>';

		return $html;
	}

	/**
	 * Renders the donut chart center with total and coalition labels.
	 *
	 * @param string $id_prefix    Element ID prefix (e.g. 'zwgr26' or 'zwgr26Gem').
	 * @param string $total_content Optional. Initial content for the total display. Default empty.
	 * @param string $extra_class   Optional. Extra CSS class for the donut wrapper. Default empty.
	 * @return string Donut chart HTML.
	 */
	public static function donut_chart( string $id_prefix, string $total_content = '', string $extra_class = '' ): string {
		$class = 'zw-gr26-donut' . ( $extra_class ? ' ' . $extra_class : '' );
		$html  = '<div class="' . esc_attr( $class ) . '" id="' . esc_attr( $id_prefix . 'Donut' ) . '">';
		$html .= '<div class="zw-gr26-donut-center">';
		$html .= '<div class="zw-gr26-donut-total" id="' . esc_attr( $id_prefix . 'DonutTotal' ) . '">' . $total_content . '</div>';
		$html .= '<div class="zw-gr26-donut-label">zetels</div>';
		$html .= '<div class="zw-gr26-donut-coal-label" id="' . esc_attr( $id_prefix . 'DonutCoalLabel' ) . '"></div>';
		$html .= '<div class="zw-gr26-donut-majority-label">Meerderheid!</div>';
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Renders the coalition builder toggle button and status bar.
	 *
	 * @param string $id_prefix Element ID prefix (e.g. 'zwgr26' or 'zwgr26Gem').
	 * @return string Coalition builder HTML.
	 */
	public static function coalition_builder( string $id_prefix ): string {
		$html  = '<button class="zw-gr26-coal-toggle" id="' . esc_attr( $id_prefix . 'CoalToggle' ) . '" type="button">Bouw coalitie</button>';
		$html .= '<div class="zw-gr26-coal-status" id="' . esc_attr( $id_prefix . 'CoalStatus' ) . '">';
		$html .= '<span class="zw-gr26-coal-status__text" id="' . esc_attr( $id_prefix . 'CoalStatusText' ) . '">'
			. 'Klik op partijen om een coalitie te vormen</span>';
		$html .= '<button class="zw-gr26-coal-status__reset" id="' . esc_attr( $id_prefix . 'CoalReset' ) . '" type="button">Wissen</button>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Renders the results table with standard header row.
	 *
	 * @param string $tbody_id ID for the tbody element.
	 * @return string Table HTML.
	 */
	public static function results_table( string $tbody_id ): string {
		$html  = '<table class="zw-gr26-tbl">';
		$html .= '<thead><tr><th colspan="2">Partij</th><th>Zetels</th><th>+/&minus;</th></tr></thead>';
		$html .= '<tbody id="' . esc_attr( $tbody_id ) . '"></tbody>';
		$html .= '</table>';

		return $html;
	}

	/**
	 * Renders a municipality dropdown select element.
	 *
	 * @param array<string, string> $items Map of element-id => display name.
	 * @return string Select HTML with wrapping div.
	 */
	public static function municipality_dropdown( array $items ): string {
		$html  = '<div class="zw-gr26-programma__select-wrap">';
		$html .= '<select class="zw-gr26-programma__select" data-zw-gr26-programma-select aria-label="Kies je gemeente">';
		$html .= '<option value="">Kies je gemeente...</option>';

		foreach ( $items as $id => $naam ) {
			$html .= '<option value="' . esc_attr( $id ) . '">' . esc_html( $naam ) . '</option>';
		}

		$html .= '</select></div>';

		return $html;
	}

	/**
	 * Cleans shortcode content by removing stray `<br>` and empty `<p>` tags injected by wpautop.
	 *
	 * @param string $html Raw shortcode output.
	 * @return string Cleaned HTML.
	 */
	public static function clean_shortcode_html( string $html ): string {
		$html = preg_replace( '/<br\s*\/?>\s*/', '', $html );
		$html = preg_replace( '#<p>\s*</p>#', '', $html );
		return $html;
	}

	/**
	 * Registers the shared video modal in wp_footer (once).
	 *
	 * @return void
	 */
	public static function video_modal(): void {
		if ( Shortcode_Pagina::$video_modal_added ) {
			return;
		}
		Shortcode_Pagina::$video_modal_added = true;
		add_action(
			'wp_footer',
			static function () {
				echo '<div class="zw-gr26-modal-backdrop" id="zwgr26VideoModal">';
				echo '<div class="zw-gr26-video-modal" role="dialog" aria-modal="true" aria-label="Video" tabindex="-1">';
				echo '<button class="zw-gr26-modal__close" type="button" aria-label="Sluiten">&times;</button>';
				echo '<video class="video-js vjs-fill vjs-big-play-centered" id="zwgr26VideoPlayer" playsinline controls></video>';
				echo '</div></div>';
			}
		);
	}

	/**
	 * Renders a single program row (link or disabled placeholder).
	 *
	 * @param array $partij {
	 *     Party data.
	 *
	 *     @type string $naam Party name.
	 *     @type string $url  Program URL (empty string if unavailable).
	 * }
	 * @return string Program row HTML.
	 */
	public static function programma_row( array $partij ): string {
		if ( $partij['url'] ) {
			$html  = '<a href="' . esc_url( $partij['url'] ) . '" class="zw-gr26-prow" target="_blank" rel="noopener noreferrer">';
			$html .= '<span class="zw-gr26-prow__partij">' . esc_html( $partij['naam'] ) . '</span>';
			$html .= '<span class="zw-gr26-prow__link-text">Lees programma</span>';
			$html .= '</a>';
			return $html;
		}
		$html  = '<div class="zw-gr26-prow zw-gr26-prow--disabled">';
		$html .= '<span class="zw-gr26-prow__partij">' . esc_html( $partij['naam'] ) . '</span>';
		$html .= '<span class="zw-gr26-prow__link-text">Geen programma</span>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Renders a news article card.
	 *
	 * @param array $item {
	 *     Article data.
	 *
	 *     @type string $url        Article permalink.
	 *     @type string $titel      Article title.
	 *     @type string $datum      Display date.
	 *     @type string $datum_iso  ISO 8601 date.
	 *     @type string $afbeelding Featured image URL.
	 *     @type string $regio      Region name.
	 * }
	 * @return string Article card HTML.
	 */
	public function article_card( array $item ): string {
		$html  = '<article class="zw-gr26-acard">';
		$html .= '<a href="' . esc_url( $item['url'] ) . '" class="zw-gr26-acard__link">';
		$html .= '<div class="zw-gr26-acard__thumb">';

		if ( ! empty( $item['afbeelding'] ) ) {
			$html .= $this->img_tag(
				$item['afbeelding'],
				$item['titel'],
				300,
				169,
				'zw-gr26-cover-img',
				'(max-width: 768px) calc(100vw - 40px), 300px'
			);
		}

		$html .= '</div>';
		$html .= '<div class="zw-gr26-acard__info">';

		if ( ! empty( $item['regio'] ) ) {
			$html .= '<span class="zw-gr26-acard__tag">' . esc_html( $item['regio'] ) . '</span>';
		}

		$html .= '<h3 class="zw-gr26-acard__title">' . esc_html( $item['titel'] ) . '</h3>';
		$html .= '<time class="zw-gr26-acard__meta" datetime="' . esc_attr( $item['datum_iso'] ) . '">'
			. esc_html( $item['datum'] ) . '</time>';
		$html .= '</div>';
		$html .= '</a></article>';

		return $html;
	}
}
