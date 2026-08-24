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

/**
 * Everything that renders as a single `<input>`.
 *
 * Subclasses supply the `type` attribute and, where relevant, a sanitizer.
 * Every one of them accepts a placeholder, which is the point of the shared
 * base: it was previously implemented per type and several types simply
 * omitted it.
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
		$attributes->set( 'type', $this->input_type() );
		$attributes->set( 'value', $this->render_value( $field ) );
		// field-kit__* stays as a scripting hook; the *visual* class comes
		// from core, so inputs match every other admin screen and inherit
		// admin colour schemes and future restyles without being restyled.
		$attributes->add_class( $this->width_class( $field ), 'field-kit__input', 'field-kit__input--' . $this->id() );

		$this->apply_constraints( $field, $attributes );

		return sprintf( '<input%s />', $attributes->render() );
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
}
