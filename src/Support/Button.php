<?php
/**
 * Button
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
 * One button, in core's clothes.
 *
 * Every library in this set was building its own: `class="button button-{$x}"`
 * assembled by hand in a dozen places, a dashicon span written out again each
 * time, and `type` left off — which inside a form means submit, so an "Add
 * row" button saved the post.
 *
 * The variants are core's, because core has exactly two weights and one
 * destructive treatment:
 *
 * - `primary` — `.button-primary`, which follows the admin colour scheme.
 * - `secondary` — `.button`, the default.
 * - `link` — `.button-link`, a button that looks like a link.
 * - `destructive` — core's own delete treatment.
 *
 * What is deliberately not here is `success`, `warning` and `danger` as button
 * fills. Core has no such buttons. A library had them, in hardcoded green,
 * amber and red — not even core's notice palette, and blind to the admin
 * colour scheme the user picked. A green Save button is not a WordPress thing:
 * primary already means "this is the action", and a second colour saying it
 * again is decoration that stops meaning anything once two of them are on
 * screen together.
 *
 * Destructive is the exception because core makes it one, and because it is
 * the only case where colour carries information the label does not.
 */
final class Button {

	/**
	 * The class each variant renders with.
	 *
	 * @var array<string, string>
	 */
	private const VARIANTS = [
		'primary'     => 'button button-primary',
		'secondary'   => 'button button-secondary',
		'link'        => 'button-link',

		// Core's treatment, as used on the Site Icon screen: red text and a
		// red border, filling red on hover. Not `.button-link-delete`, which
		// is a red *link* with no border — combined with `.button` that gives
		// a blue label inside a red outline.
		'destructive' => 'button field-kit__button--delete',
	];

	/**
	 * Sizes core offers.
	 *
	 * @var string[]
	 */
	private const SIZES = [ 'small', 'large', 'hero', 'compact' ];

	/**
	 * Render a button.
	 *
	 * @param array{
	 *     label?: string,
	 *     variant?: string,
	 *     size?: string,
	 *     icon?: string,
	 *     type?: string,
	 *     disabled?: bool,
	 *     spinner?: bool,
	 *     class?: string,
	 *     attributes?: array<string, string|bool>
	 * } $config Button configuration.
	 *
	 * @return string
	 */
	public static function render( array $config ): string {
		$label   = (string) ( $config['label'] ?? '' );
		$variant = (string) ( $config['variant'] ?? 'secondary' );

		/*
		 * An icon only when there is no label to read instead.
		 *
		 * WordPress does not put a glyph in front of a button's text
		 * anywhere in its own admin -- not on Add New, not on Publish, not
		 * on Apply -- and a button carrying both says the same thing twice,
		 * the second time in a language the reader has to learn. Icon-only
		 * buttons are a different case: a row's remove control has nowhere
		 * to put a label, so it keeps its glyph and carries an accessible
		 * name instead.
		 *
		 * Passing both is not an error, because a caller who has a label is
		 * always better served by it.
		 */
		$icon = '' === $label ? (string) ( $config['icon'] ?? '' ) : '';

		$button = new Attributes();

		// Never left to the browser. The default for a button inside a form
		// is submit, so an "Add row" button written without this saves the
		// post — which is the single most common bug in this shape of code.
		$button->set( 'type', 'submit' === ( $config['type'] ?? '' ) ? 'submit' : 'button' );

		$button->add_class( self::VARIANTS[ $variant ] ?? self::VARIANTS['secondary'] );

		$size = (string) ( $config['size'] ?? '' );

		if ( in_array( $size, self::SIZES, true ) ) {
			$button->add_class( 'button-' . $size );
		}

		if ( '' !== (string) ( $config['class'] ?? '' ) ) {
			$button->add_class( (string) $config['class'] );
		}

		// Marked rather than detected with :has(), so the rule is deterministic
		// and works wherever the markup ends up.
		$button->set_if( '' !== $icon, 'class', 'field-kit__button--icon' );

		$button->set_if( ! empty( $config['disabled'] ), 'disabled', true );

		foreach ( (array) ( $config['attributes'] ?? [] ) as $name => $value ) {
			$button->set( (string) $name, $value );
		}

		// An icon-only button still has to announce itself.
		if ( '' !== $icon && ! $button->has( 'aria-label' ) ) {
			$button->set( 'aria-label', (string) ( $config['aria_label'] ?? $icon ) );
		}

		return sprintf(
			'<button%s>%s%s%s</button>',
			$button->render(),
			'' === $icon
				? ''
				: sprintf( '<span class="dashicons dashicons-%s" aria-hidden="true"></span>', esc_attr( $icon ) ),
			'' === $label ? '' : esc_html( $label ),
			empty( $config['spinner'] )
				? ''
				: '<span class="spinner field-kit__button-spinner" aria-hidden="true"></span>'
		);
	}
}
