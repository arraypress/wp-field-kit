<?php
/**
 * Field and Section Badge
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Support;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A small pill beside a label, marking a field the current install cannot use.
 *
 * The upsell shape a commercial plugin needs: the field is rendered so the
 * feature is visible, the badge names the tier that unlocks it, and the
 * control is locked so nobody can set a value the install will not honour.
 *
 * The condition is inverted from what reads naturally, and it is worth being
 * explicit about it. `disabled` **hides** the badge. It is the answer to "does
 * this install already have the feature?" — true means yes, so there is
 * nothing to sell and nothing to lock. A callable is accepted so the answer
 * can be a licence check made at render time rather than at registration.
 */
final class Badge {

	/**
	 * Normalize a badge configuration.
	 *
	 * @param mixed $badge String, array, or nothing.
	 *
	 * @return array{text: string, url: string, class: string, icon: string}|null
	 *         Null when there is no badge to show.
	 */
	public static function resolve( mixed $badge ): ?array {
		if ( is_string( $badge ) ) {
			$badge = [ 'text' => $badge ];
		}

		if ( ! is_array( $badge ) || '' === (string) ( $badge['text'] ?? '' ) ) {
			return null;
		}

		$suppress = $badge['disabled'] ?? false;

		if ( is_callable( $suppress ) ) {
			$suppress = $suppress();
		}

		if ( $suppress ) {
			return null;
		}

		return [
			'text'  => (string) $badge['text'],
			'url'   => (string) ( $badge['url'] ?? '' ),
			'class' => (string) ( $badge['class'] ?? '' ),
			'icon'  => (string) ( $badge['icon'] ?? '' ),
		];
	}

	/**
	 * Whether a field's badge is showing, and so the field is locked.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	public static function locks( Field $field ): bool {
		return null !== self::resolve( $field->get( 'badge' ) );
	}

	/**
	 * Render a resolved badge.
	 *
	 * @param array{text: string, url: string, class: string, icon: string} $badge Resolved badge.
	 *
	 * @return string
	 */
	public static function render( array $badge ): string {
		$icon = '' === $badge['icon']
			// Decorative: the badge text beside it already says what it means.
			? ''
			: sprintf( '<span class="dashicons dashicons-%s" aria-hidden="true"></span>', esc_attr( $badge['icon'] ) );

		$attributes = new Attributes();
		$attributes->add_class( 'field-kit__badge' );
		$attributes->add_class( ...array_filter( [ $badge['class'] ] ) );

		if ( '' === $badge['url'] ) {
			return sprintf( '<span%s>%s%s</span>', $attributes->render(), $icon, esc_html( $badge['text'] ) );
		}

		$attributes->set( 'href', $badge['url'] );

		// An upgrade page is somewhere else, and a link that leaves the admin
		// mid-configuration should not take the settings screen with it.
		$attributes->set( 'target', '_blank' );
		$attributes->set( 'rel', 'noopener noreferrer' );

		return sprintf(
			'<a%s>%s%s<span class="screen-reader-text">%s</span></a>',
			$attributes->render(),
			$icon,
			esc_html( $badge['text'] ),
			/* translators: accessibility text appended to a link that opens in a new tab */
			esc_html__( '(opens in a new tab)', 'arraypress' )
		);
	}

	/**
	 * Render a field's badge, if it has one showing.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public static function for_field( Field $field ): string {
		$badge = self::resolve( $field->get( 'badge' ) );

		return null === $badge ? '' : self::render( $badge );
	}
}
