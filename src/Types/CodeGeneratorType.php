<?php
/**
 * Code Generator Field Type
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
use ArrayPress\FieldKit\Support\Button;

/**
 * A text field with a button that fills it in for you.
 *
 * A discount code, an API key, a licence: something a person may type but
 * usually cannot be bothered to invent. The value is still editable, because
 * "generate me one" and "I already have one" are both ordinary.
 *
 * The generating happens in the browser, which is the one decision here worth
 * stating: a round trip to invent eight random characters is a round trip for
 * nothing, and the value is not secret until it is saved. Anything that must
 * be unpredictable — a password, a nonce, a token — should not use this.
 */
final class CodeGeneratorType extends AbstractInputType {

	/**
	 * The alphabets a code can be drawn from.
	 *
	 * Ambiguity is the reason these are not just "letters and numbers": a
	 * code gets read off a screen and typed into a box, and O/0 and I/1/l are
	 * where that goes wrong. Upper-case alphanumeric is the default for the
	 * same reason a licence key is.
	 *
	 * @var array<string, string>
	 */
	public const ALPHABETS = [
		'alphanumeric_upper' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
		'alphanumeric'       => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
		'alpha_upper'        => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
		'hex'                => '0123456789ABCDEF',
		'numeric'            => '0123456789',
	];

	/**
	 * The type's id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'code_generator';
	}

	/**
	 * The control is a text box.
	 *
	 * @return string
	 */
	protected function input_type(): string {
		return 'text';
	}

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'length'         => 8,
			'format'         => 'alphanumeric_upper',
			'prefix'         => '',
			'separator'      => '',
			'segment_length' => 0,
			'button_label'   => '',
		];
	}

	/**
	 * Render the input and its button.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->set( 'type', 'text' );
		$attributes->set( 'value', (string) $field->value() );
		$attributes->add_class( 'regular-text', 'code', 'field-kit__code-value' );
		$attributes->set_if( '' !== $field->placeholder(), 'placeholder', $field->placeholder() );

		$format = (string) $field->get( 'format', 'alphanumeric_upper' );

		return sprintf(
			'<div class="field-kit__code-generator"><input%s />%s</div>',
			$attributes->render(),
			Button::render(
				[
					'label'      => (string) ( $field->get( 'button_label' ) ?: __( 'Generate', 'arraypress' ) ),
					'icon'       => 'update',
					'class'      => 'field-kit__code-generate',
					'attributes' => [
						// The alphabet is named rather than sent, so a caller
						// cannot put arbitrary characters into a page and have
						// the script draw from them.
						'data-format'         => isset( self::ALPHABETS[ $format ] ) ? $format : 'alphanumeric_upper',
						'data-length'         => (string) absint( $field->get( 'length', 8 ) ),
						'data-prefix'         => (string) $field->get( 'prefix', '' ),
						'data-separator'      => (string) $field->get( 'separator', '' ),
						'data-segment-length' => (string) absint( $field->get( 'segment_length', 0 ) ),
					],
				]
			)
		);
	}

	/**
	 * Not in an inline row.
	 *
	 * Quick edit clones its panel from a hidden template before the values
	 * are in it, so a button whose behaviour is attached in JavaScript comes
	 * up dead in the clone — which is the exact failure this whitelist
	 * exists to prevent.
	 *
	 * @return bool
	 */
	public function supports_inline(): bool {
		return false;
	}

	/**
	 * The configuration keys this type reads.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		return array_merge(
			parent::config_keys(),
			[ 'button_label', 'format', 'length', 'prefix', 'segment_length', 'separator' ]
		);
	}
}
