<?php
/**
 * Base Input Type
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
use ArrayPress\FieldKit\Support\Markup;

/**
 * Everything that renders as a single `<input>`.
 *
 * Subclasses supply the `type` attribute and, where relevant, a sanitizer.
 * Every one of them accepts a placeholder, which is the point of the shared
 * base: it was previously implemented per type and several types simply
 * omitted it.
 *
 * The same reasoning covers an affix and a character count. A unit inside
 * the box -- "$" before a price, "%" after a discount, "days" after a
 * number -- is wanted on a text as often as on a number, and a running count
 * against a `maxlength` on either, so both live here rather than on
 * whichever type asked first.
 */
abstract class AbstractInputType extends AbstractType {

	/**
	 * The HTML input type attribute.
	 *
	 * @return string
	 */
	abstract protected function input_type(): string;

	/**
	 * Render the input.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$value = $this->render_value( $field );

		$attributes->set( 'type', $this->input_type() );
		$attributes->set( 'value', $value );
		// field-kit__* stays as a scripting hook; the *visual* class comes
		// from core, so inputs match every other admin screen and inherit
		// admin colour schemes and future restyles without being restyled.
		$attributes->add_class( $this->width_class( $field ), 'field-kit__input', 'field-kit__input--' . $this->id() );

		$this->apply_constraints( $field, $attributes );

		return $this->control( $field, $attributes ) . $this->counter( $field, $value );
	}

	/**
	 * The control: the input, with any affix drawn inside its box.
	 *
	 * Separate from render() so a subclass can put something beside the
	 * input -- a password puts a reveal button there -- without also taking
	 * in the count that follows. The count belongs under the whole control,
	 * not between the input and its button.
	 *
	 * An affix is text in the input's own padding, the way the money field
	 * draws its symbol, and for the same reason: a unit set beside a
	 * full-width input in a narrow panel wraps onto a line of its own, which
	 * is a "%" floating above an unlabelled box. It is hidden from assistive
	 * technology as drawn and supplied again as a description of the input,
	 * so "10" in a box marked "%" is announced as ten percent rather than
	 * ten. A visually hidden span rather than a change to the field's
	 * description text: the description is the caller's prose, rendered by
	 * the renderer after the control and out of reach from here -- and the
	 * unit still has to be announced on a field that has no description.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	protected function control( Field $field, Attributes $attributes ): string {
		// A hidden input has no box to draw in.
		$prefix = 'hidden' === $this->input_type() ? '' : (string) $field->get( 'prefix', '' );
		$suffix = 'hidden' === $this->input_type() ? '' : (string) $field->get( 'suffix', '' );

		if ( '' === $prefix && '' === $suffix ) {
			return sprintf( '<input%s />', $attributes->render() );
		}

		$wrapper = new Attributes();
		$wrapper->add_class( 'field-kit__adorned' );

		// Which side has one, so the stylesheet reserves that padding and no
		// other; and how long it is, measured here rather than in CSS, which
		// cannot see it. "days" needs the room "%" does not.
		if ( '' !== $prefix ) {
			$wrapper->add_class( 'field-kit__adorned--prefix' );

			if ( mb_strlen( $prefix ) > 1 ) {
				$wrapper->add_class( 'field-kit__adorned--wide-prefix' );
			}
		}

		if ( '' !== $suffix ) {
			$wrapper->add_class( 'field-kit__adorned--suffix' );

			if ( mb_strlen( $suffix ) > 1 ) {
				$wrapper->add_class( 'field-kit__adorned--wide-suffix' );
			}
		}

		$id        = (string) $attributes->get( 'id', '' );
		$hidden    = '';
		$described = [];

		$affixes = [
			'prefix' => $prefix,
			'suffix' => $suffix,
		];

		foreach ( $affixes as $side => $text ) {
			// Without an id there is nothing for the input to point at. The
			// renderer always supplies one; this only covers a caller
			// building attributes by hand.
			if ( '' === $text || '' === $id ) {
				continue;
			}

			$described[] = $id . '__' . $side;
			$hidden     .= sprintf(
				'<span class="screen-reader-text" id="%s">%s</span>',
				esc_attr( $id . '__' . $side ),
				esc_html( $text )
			);
		}

		// Ahead of the description rather than after it: the unit qualifies
		// the value and is read beside it. "Ten, percent, applied at
		// checkout" rather than the unit arriving last.
		if ( [] !== $described ) {
			$attributes->set(
				'aria-describedby',
				trim( implode( ' ', $described ) . ' ' . (string) $attributes->get( 'aria-describedby', '' ) )
			);
		}

		return sprintf(
			'<span%s>%s<input%s />%s%s</span>',
			$wrapper->render(),
			'' === $prefix
				? ''
				: sprintf( '<span class="field-kit__adornment field-kit__adornment--prefix" aria-hidden="true">%s</span>', esc_html( $prefix ) ),
			$attributes->render(),
			'' === $suffix
				? ''
				: sprintf( '<span class="field-kit__adornment field-kit__adornment--suffix" aria-hidden="true">%s</span>', esc_html( $suffix ) ),
			$hidden
		);
	}

	/**
	 * A running count of characters, where there is a limit to count against.
	 *
	 * Only with a `maxlength`: a count with no limit is a number with nothing
	 * to compare it to. `counter => false` turns it off on a field that has
	 * a limit but no use for one -- a postcode is not something anyone runs
	 * out of room in.
	 *
	 * Counted from what the input is rendered with rather than the stored
	 * value, so a password -- which never renders its value -- starts at
	 * nought and gives nothing away about the length of what is saved.
	 *
	 * @param Field  $field The field.
	 * @param string $value The value as rendered into the input.
	 *
	 * @return string
	 */
	protected function counter( Field $field, string $value ): string {
		if ( 'hidden' === $this->input_type() ) {
			return '';
		}

		return Markup::counter(
			mb_strlen( $value ),
			(int) $field->get( 'maxlength', 0 ),
			(bool) $field->get( 'counter', true ),
			$this->width_class( $field )
		);
	}

	/**
	 * The value as it should appear in the `value` attribute.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function render_value( Field $field ): string {
		$value = $field->value();

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Copy validation constraints from config onto the input.
	 *
	 * These are real HTML validation attributes, so the browser enforces them
	 * and assistive technology announces them without any extra ARIA.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Attributes being built.
	 *
	 * @return void
	 */
	protected function apply_constraints( Field $field, Attributes $attributes ): void {
		foreach ( [ 'minlength', 'maxlength', 'pattern', 'inputmode', 'step', 'min', 'max' ] as $constraint ) {
			$attributes->set_if( $field->has( $constraint ), $constraint, $field->get( $constraint ) );
		}
	}

	/**
	 * Core's width class for this field.
	 *
	 * @param \ArrayPress\FieldKit\Field $field The field.
	 *
	 * @return string
	 */
	protected function width_class( Field $field ): string {
		return match ( (string) $field->get( 'size', 'regular' ) ) {
			'tiny'  => 'tiny-text',
			'small' => 'small-text',
			'large' => 'large-text',
			'none'  => '',
			default => 'regular-text',
		};
	}

	/**
	 * Every single-input type takes a placeholder.
	 *
	 * @return bool
	 */
	public function supports_placeholder(): bool {
		return true;
	}

	/**
	 * Fits an inline row.
	 *
	 * A plain input needs nothing started in JavaScript and fits a row.
	 *
	 * @return bool
	 */
	public function supports_inline(): bool {
		return true;
	}

	/**
	 * The configuration keys this type reads.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		return array_merge(
			parent::config_keys(),
			[ 'counter', 'maxlength', 'prefix', 'size', 'suffix' ]
		);
	}
}
