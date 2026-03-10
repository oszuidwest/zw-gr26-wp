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
	 * Open a content section with a title and optional link.
	 *
	 * @param string $title     Section heading.
	 * @param string $link      Optional URL for the "more" link.
	 * @param string $link_text Optional label for the "more" link.
	 * @return string
	 */
	public function section_open( string $title, string $link = '', string $link_text = '' ): string {
		$html  = '<section class="zw-gr26-section">';
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
	 * Close a content section.
	 *
	 * @return string
	 */
	public function section_close(): string {
		return '</section>';
	}

	/**
	 * Render the hero header with background image.
	 *
	 * @param string $title    Main heading.
	 * @param string $subtitle Subtitle text.
	 * @param string $bg_image Background image URL.
	 * @return string
	 */
	public function hero( string $title, string $subtitle, string $bg_image ): string {
		$html  = '<header class="zw-gr26-hero">';
		$html .= '<div class="zw-gr26-hero__bg" style="background-image:url(' . esc_url( $bg_image ) . ')"></div>';
		$html .= '<div class="zw-gr26-hero__content">';
		$html .= '<h1 class="zw-gr26-hero__title">' . esc_html( $title ) . '</h1>';

		if ( $subtitle ) {
			$html .= '<p class="zw-gr26-hero__subtitle">' . esc_html( $subtitle ) . '</p>';
		}

		$html .= '</div></header>';

		return $html;
	}

	/**
	 * Render the red-white-blue decorative stripe.
	 *
	 * @return string
	 */
	public function stripe(): string {
		return '<div class="zw-gr26-stripe">'
			. '<span class="zw-gr26-stripe--red"></span>'
			. '<span class="zw-gr26-stripe--white"></span>'
			. '<span class="zw-gr26-stripe--blue"></span>'
			. '</div>';
	}

	/**
	 * Render a video card with thumbnail, title, and optional "coming soon" state.
	 *
	 * @param array $video       Video data with titel, thumbnail, url, and optional meta.
	 * @param bool  $coming_soon Whether the video is not yet available.
	 * @return string
	 */
	public function video_card( array $video, bool $coming_soon = false ): string {
		$tag  = $coming_soon ? 'div' : 'a';
		$href = $coming_soon ? '' : ' href="' . esc_url( $video['url'] ) . '"';

		$has_thumb = ! empty( $video['thumbnail'] );

		$html  = '<article class="zw-gr26-vcard' . ( $coming_soon ? ' zw-gr26-binnenkort' : '' ) . '">';
		$html .= '<' . $tag . $href . ' class="zw-gr26-vcard__link">';

		if ( $has_thumb ) {
			$html .= '<img class="zw-gr26-cover-img" src="' . esc_url( $video['thumbnail'] ) . '" alt="' . esc_attr( $video['titel'] ) . '" loading="lazy">';
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
	 * Render an explainer card (9:16 aspect ratio).
	 *
	 * @param array $video Video data with titel, thumbnail, url, and optional binnenkort flag.
	 * @return string
	 */
	public function explainer_card( array $video ): string {
		$coming_soon = ! empty( $video['binnenkort'] );
		$has_thumb   = ! empty( $video['thumbnail'] );
		$tag         = $coming_soon ? 'div' : 'a';
		$href        = $coming_soon ? '' : ' href="' . esc_url( $video['url'] ) . '"';

		$html  = '<article class="zw-gr26-ecard' . ( $coming_soon ? ' zw-gr26-ecard--binnenkort' : '' ) . '">';
		$html .= '<' . $tag . $href . ' class="zw-gr26-ecard__link">';

		if ( $has_thumb ) {
			$html .= '<img class="zw-gr26-cover-img" src="' . esc_url( $video['thumbnail'] ) . '" alt="' . esc_attr( $video['titel'] ) . '" loading="lazy">';
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
	 * Render a news article card.
	 *
	 * @param array $item Article data with url, titel, datum, datum_iso, afbeelding, and regio.
	 * @return string
	 */
	public function article_card( array $item ): string {
		$html  = '<article class="zw-gr26-acard">';
		$html .= '<a href="' . esc_url( $item['url'] ) . '" class="zw-gr26-acard__link">';
		$html .= '<div class="zw-gr26-acard__thumb">';

		if ( ! empty( $item['afbeelding'] ) ) {
			$html .= '<img class="zw-gr26-cover-img" src="' . esc_url( $item['afbeelding'] ) . '" alt="' . esc_attr( $item['titel'] ) . '" loading="lazy">';
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
