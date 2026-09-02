<?php
/**
 * Gradient Field Type
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A gradient, chosen from the ones the site already has.
 *
 * WordPress defines gradient presets — a dozen of its own, plus whatever the
 * active theme adds in theme.json — and exposes them through
 * `wp_get_global_settings()`. They are the same gradients the block editor
 * offers, so a plugin picking from them lands somewhere the site already
 * looks like itself rather than inventing a palette beside the theme's.
 *
 * There is no non-React gradient control in core to reuse: `GradientPicker`
 * is a `@wordpress/components` React component and exists only inside the
 * block editor. This is a radio group underneath, exactly like `card_choice`
 * — each swatch is a label wrapping an input, painted with the gradient it
 * stands for — so it is operable from a keyboard with no script at all.
 *
 * What is stored is the CSS, not the slug. A slug is only meaningful while
 * the theme that defined it is active, and a stored gradient that renders as
 * nothing after a theme switch is worse than one that keeps working.
 */
final class GradientType extends AbstractType {

	/**
	 * The type's id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'gradient';
	}

	/**
	 * Config defaults.
	 *
	 * The options default to a callable that reads the site's presets, the
	 * way nav_menu's read its menus, rather than to nothing with the presets
	 * read as a fallback. The difference is what `Field::options()` answers:
	 * with nothing configured it answered an empty list, and the kit's own
	 * missing-options check reported every gradient field as having nothing
	 * to choose from while it drew a dozen swatches.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'columns' => 4,
			'options' => [ self::class, 'presets' ],
		];
	}

	/**
	 * The gradient presets the site offers, as CSS => name.
	 *
	 * Core's dozen plus the theme's, from the same global settings the block
	 * editor reads. Public and static because it is the options default, and
	 * a default is resolved by the field rather than by this type.
	 *
	 * @param Field $field The field asking. Unused: the presets are the
	 *                     site's, not the field's.
	 *
	 * @return array<string, string>
	 */
	public static function presets( Field $field ): array {
		unset( $field );

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return [];
		}

		$found = [];

		// Grouped by origin — default, theme, custom — and a later origin
		// overriding an earlier one is the point of the grouping.
		foreach ( (array) wp_get_global_settings( [ 'color', 'gradients' ] ) as $set ) {
			foreach ( (array) $set as $preset ) {
				if ( empty( $preset['gradient'] ) ) {
					continue;
				}

				$found[ (string) $preset['gradient'] ] = (string) ( $preset['name'] ?? $preset['slug'] ?? '' );
			}
		}

		return $found;
	}

	/**
	 * A radio group wants a fieldset and a legend, not a label pointing at
	 * one of its inputs.
	 *
	 * @return bool
	 */
	public function is_grouped(): bool {
		return true;
	}

	/**
	 * Render the swatches.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$name    = (string) $attributes->get( 'name' );
		$current = (string) $field->value();
		$markup  = '';
		$chosen  = false;

		foreach ( $this->gradients( $field ) as $gradient => $label ) {
			$checked = $gradient === $current;
			$chosen  = $chosen || $checked;

			$input = new Attributes();
			$input->set( 'type', 'radio' );
			$input->set( 'name', $name );
			$input->set( 'value', $gradient );
			$input->add_class( 'field-kit__gradient-input' );
			$input->set_if( $checked, 'checked', true );
			$input->set_if( (bool) $field->get( 'disabled' ), 'disabled', true );

			$markup .= sprintf(
				'<label class="field-kit__gradient"><input%s />' .
				'<span class="field-kit__gradient-swatch" style="background:%s" aria-hidden="true"></span>' .
				'<span class="field-kit__gradient-label">%s</span></label>',
				$input->render(),
				esc_attr( $gradient ),
				esc_html( $label )
			);
		}

		// A stored gradient the theme no longer offers still has to be
		// selectable, or opening the screen and saving it silently changes
		// the value to whichever preset happens to be first.
		if ( '' !== $current && ! $chosen ) {
			$input = new Attributes();
			$input->set( 'type', 'radio' );
			$input->set( 'name', $name );
			$input->set( 'value', $current );
			$input->add_class( 'field-kit__gradient-input' );
			$input->set( 'checked', true );

			// Painted from the stored value, which is only as trustworthy as
			// whatever last wrote it. A gradient has no use for a semicolon,
			// a brace or a url(), so one carrying any of them is listed but
			// not drawn.
			$paint = ! preg_match( '/[;{}<>]|url\s*\(|expression|javascript:/i', $current );

			$markup .= sprintf(
				'<label class="field-kit__gradient"><input%s />' .
				'<span class="field-kit__gradient-swatch"%s aria-hidden="true"></span>' .
				'<span class="field-kit__gradient-label">%s</span></label>',
				$input->render(),
				$paint ? ' style="background:' . esc_attr( $current ) . '"' : '',
				esc_html__( 'Current', 'arraypress' )
			);
		}

		// Somewhere to land when nothing is chosen, unless one must be.
		if ( ! $field->is_required() ) {
			$input = new Attributes();
			$input->set( 'type', 'radio' );
			$input->set( 'name', $name );
			$input->set( 'value', '' );
			$input->add_class( 'field-kit__gradient-input' );
			$input->set_if( '' === $current, 'checked', true );

			$markup = sprintf(
				'<label class="field-kit__gradient field-kit__gradient--none"><input%s />' .
				'<span class="field-kit__gradient-swatch" aria-hidden="true"></span>' .
				'<span class="field-kit__gradient-label">%s</span></label>',
				$input->render(),
				esc_html( (string) $field->get( 'empty_label', __( 'None', 'arraypress' ) ) )
			) . $markup;
		}

		return sprintf(
			'<div class="field-kit__gradients" style="--field-kit-gradient-columns:%d">%s</div>',
			absint( $field->get( 'columns', 4 ) ),
			$markup
		);
	}

	/**
	 * The gradients on offer, as CSS => label.
	 *
	 * The site's own by default — core's dozen plus the theme's — because a
	 * plugin that offers its own palette beside the theme's is a plugin that
	 * looks like a plugin. A caller with a reason can pass `options` and get
	 * exactly those instead; either way it is the field that resolves them.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, string>
	 */
	private function gradients( Field $field ): array {
		return $field->options();
	}

	/**
	 * Coerce a submitted value.
	 *
	 * Only a gradient that was on offer is accepted. The value lands in a
	 * style attribute, so anything else is a CSS injection with a form around
	 * it — `background: red; } body { display: none` is the shape of it.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public function sanitize( mixed $value, Field $field ): string {
		$value = is_scalar( $value ) ? (string) $value : '';

		if ( '' === $value ) {
			return '';
		}

		$offered = array_keys( $this->gradients( $field ) );

		// The value already stored counts as on offer: a theme switch should
		// not silently blank a field nobody touched.
		$offered[] = (string) $field->value();

		return in_array( $value, $offered, true ) ? $value : '';
	}

	/**
	 * The configuration keys this type reads.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		return array_merge(
			parent::config_keys(),
			[ 'columns', 'disabled', 'empty_label', 'options' ]
		);
	}
}
