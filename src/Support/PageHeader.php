<?php
/**
 * Admin Page Header
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Support;

use ArrayPress\FieldKit\Attributes;

/**
 * The header WordPress uses on its own tabbed settings screens.
 *
 * `options-privacy.php` and `site-health.php` both render the same shape, and
 * core styles them from one shared rule in `wp-admin/css/edit.css` — which is
 * a dependency of the `wp-admin` bundle, so it is present on every admin
 * page. Reusing the class means the header matches core exactly, follows the
 * user's colour scheme and costs nothing in CSS.
 *
 * Two things do not carry over, both checked rather than assumed:
 *
 * - `.privacy-settings-tabs-wrapper` is `grid-template-columns: 1fr 1fr`,
 *   hardcoded to the two tabs that screen has. Site Health declares its own
 *   at four. Neither generalises, so the wrapper here is the kit's own and is
 *   the one place this costs a few lines of CSS. The individual
 *   `.privacy-settings-tab` links carry no such assumption and are reused.
 * - `<hr class="wp-header-end">` is not decoration. `common.js` looks for it
 *   and moves admin notices to sit directly after it; without one, notices
 *   are appended after the first `h1` or `h2` on the page, which on a tabbed
 *   screen means somewhere arbitrary.
 */
final class PageHeader {

	/**
	 * The body class a screen using this header must carry.
	 *
	 * The header only spans the screen if `#wpcontent`'s 20px left padding is
	 * removed, and core removes it with a body class rather than on the header
	 * itself — `.privacy-settings #wpcontent { padding-left: 0 }` in
	 * wp-admin/css/edit.css. Without the class the header sits 20px in from
	 * the menu and does not look like core's, which is the only reason for
	 * using core's markup.
	 *
	 * Named here rather than by the consumer so the rule and the class cannot
	 * drift apart.
	 *
	 * @return string
	 */
	public static function body_class(): string {
		return 'field-kit__page-screen';
	}

	/**
	 * Render the header.
	 *
	 * Configured by array rather than by position: a header grew a logo and a
	 * badge the moment it was used for a commercial plugin, and a fifth and
	 * sixth positional argument is how a call becomes unreadable.
	 *
	 * @param array{
	 *     title?: string,
	 *     logo?: string,
	 *     badge?: string|array{text?: string, class?: string}|callable,
	 *     tabs?: array<string, array{label: string, url: string}>,
	 *     current?: string,
	 *     actions?: string
	 * } $config Header configuration.
	 *
	 * @return string
	 */
	public static function render( array $config = [] ): string {
		$title   = (string) ( $config['title'] ?? '' );
		$tabs    = (array) ( $config['tabs'] ?? [] );
		$actions = (string) ( $config['actions'] ?? '' );

		// The title section is a centred flex row in core, so a logo and a
		// badge sit beside the heading without a rule of our own.
		$markup = '<div class="privacy-settings-header field-kit__page-header">'
			. '<div class="privacy-settings-title-section">'
			. self::logo( (string) ( $config['logo'] ?? '' ) )
			. sprintf( '<h1>%s</h1>', esc_html( $title ) )
			. self::badge( $config['badge'] ?? '' )
			. '</div>';

		if ( '' !== $actions ) {
			$markup .= sprintf( '<div class="field-kit__page-actions">%s</div>', $actions );
		}

		if ( [] !== $tabs ) {
			$markup .= self::tabs( $tabs, (string) ( $config['current'] ?? '' ) );
		}

		// Where admin notices land. Not optional.
		return $markup . '</div><hr class="wp-header-end">';
	}

	/**
	 * Render the logo, if there is one.
	 *
	 * Alt text is empty on purpose. The heading beside it already says the
	 * same thing, and a screen reader announcing the plugin's name twice is
	 * worse than not describing a decorative image.
	 *
	 * @param string $url Image URL.
	 *
	 * @return string
	 */
	private static function logo( string $url ): string {
		if ( '' === $url ) {
			return '';
		}

		$image = new Attributes();
		$image->set( 'src', $url );
		$image->set( 'alt', '' );
		$image->add_class( 'field-kit__page-logo' );

		return sprintf( '<img%s />', $image->render() );
	}

	/**
	 * Render the badge, if there is one.
	 *
	 * @param string|array{text?: string, class?: string}|callable $badge The badge.
	 *
	 * @return string
	 */
	private static function badge( mixed $badge ): string {
		if ( is_callable( $badge ) ) {
			// Through kses, because a callable returning raw markup is a
			// callable returning whatever a filter put into it.
			return wp_kses_post( (string) $badge() );
		}

		$text  = is_array( $badge ) ? (string) ( $badge['text'] ?? '' ) : (string) $badge;
		$class = is_array( $badge ) ? (string) ( $badge['class'] ?? '' ) : '';

		if ( '' === $text ) {
			return '';
		}

		$span = new Attributes();
		$span->add_class( 'field-kit__page-badge' );
		$span->add_class( ...array_filter( [ $class ] ) );

		return sprintf( '<span%s>%s</span>', $span->render(), esc_html( $text ) );
	}

	/**
	 * Render the tab navigation.
	 *
	 * @param array<string, array{label: string, url: string}> $tabs    Tabs, keyed by slug.
	 * @param string                                           $current Slug of the active tab.
	 *
	 * @return string
	 */
	private static function tabs( array $tabs, string $current ): string {
		$current = '' === $current ? (string) array_key_first( $tabs ) : $current;
		$links   = '';

		foreach ( $tabs as $slug => $tab ) {
			$active = (string) $slug === $current;

			$link = new Attributes();
			$link->set( 'href', (string) ( $tab['url'] ?? '#' ) );
			$link->add_class( 'privacy-settings-tab', 'field-kit__page-tab' );
			$link->set_if( $active, 'class', 'active' );

			// aria-current is what conveys the selection; the class is only
			// how it is drawn.
			$link->set_if( $active, 'aria-current', 'true' );

			$links .= sprintf( '<a%s>%s</a>', $link->render(), esc_html( (string) ( $tab['label'] ?? $slug ) ) );
		}

		return sprintf(
			// Labelled, because a page can hold more than one nav and an
			// unlabelled one is announced only as "navigation".
			'<nav class="field-kit__page-tabs hide-if-no-js" aria-label="%s">%s</nav>',
			esc_attr__( 'Secondary menu', 'arraypress' ),
			$links
		);
	}
}
