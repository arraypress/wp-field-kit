<?php
/**
 * Card Choice Field Type
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
 * A choice made from cards rather than from a row of radios.
 *
 * The shape a plan picker, an onboarding step or a payment-method chooser
 * wants: each option gets a heading, a sentence saying what it means, and
 * optionally an icon, laid out in columns. A radio with a long label is the
 * alternative and it reads badly — the description ends up either crammed
 * onto the label or orphaned underneath the group where it belongs to none of
 * the options in particular.
 *
 * Underneath it is still a radio group. The label wraps its input, the
 * fieldset and legend come from the renderer, and arrow keys move between
 * options the way they do in any radio group — so the whole thing is
 * operable from a keyboard without a line of script, and the "card" is
 * entirely a matter of what the stylesheet does with a checked input.
 *
 * Options take the ordinary `value => label` shape, or `value => [ 'label',
 * 'description', 'icon' ]` where there is more to say. Both, in the same
 * list: an option with nothing to explain does not need the long form.
 */
final class CardChoiceType extends RadioType {

	/**
	 * The type's id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'card_choice';
	}

	/**
	 * How many across.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [ 'columns' => 3 ];
	}

	/**
	 * Render the cards.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$name     = (string) $attributes->get( 'name' );
		$current  = (string) $field->value();
		$required = $field->is_required();
		$markup   = '';

		foreach ( $field->options() as $value => $option ) {
			$input = new Attributes();

			$input->set( 'type', 'radio' );
			$input->set( 'name', $name );
			$input->set( 'value', (string) $value );
			$input->add_class( 'field-kit__card-input' );
			$input->set_if( (string) $value === $current, 'checked', true );
			$input->set_if( $required, 'required', true );
			$input->set_if( (bool) $field->get( 'disabled' ), 'disabled', true );

			$markup .= $this->render_card( $input, (array) ( is_array( $option ) ? $option : [ 'label' => $option ] ) );
		}

		return sprintf(
			'<div class="field-kit__cards" style="--field-kit-card-columns:%d">%s</div>',
			max( 1, (int) $field->get( 'columns', 3 ) ),
			$markup
		);
	}

	/**
	 * One card.
	 *
	 * @param Attributes           $input  The input's attributes.
	 * @param array<string, mixed> $option The option.
	 *
	 * @return string
	 */
	private function render_card( Attributes $input, array $option ): string {
		$icon        = (string) ( $option['icon'] ?? '' );
		$description = (string) ( $option['description'] ?? '' );

		return sprintf(
			// The label wraps the input, as core writes radios and as
			// RadioType does: no id to collide with the same option in
			// another repeater row, and the whole card is the hit area.
			'<label class="field-kit__card"><input%s />%s' .
			'<span class="field-kit__card-body">' .
			'<span class="field-kit__card-title">%s</span>%s' .
			'</span></label>',
			$input->render(),
			'' === $icon
				? ''
				: sprintf( '<span class="dashicons dashicons-%s field-kit__card-icon" aria-hidden="true"></span>', esc_attr( $icon ) ),
			esc_html( (string) ( $option['label'] ?? '' ) ),
			'' === $description
				? ''
				: sprintf( '<span class="field-kit__card-description">%s</span>', esc_html( $description ) )
		);
	}

	/**
	 * The values a card choice accepts.
	 *
	 * RadioType compares against the option keys, which is still right — the
	 * long form only changes what a label looks like, not what is stored.
	 *
	 * @param Field $field The field.
	 *
	 * @return string[]
	 */
	protected function allowed_values( Field $field ): array {
		return array_map( 'strval', array_keys( $field->options() ) );
	}

	/**
	 * Does not fit an inline row.
	 *
	 * A grid of cards with headings and sentences in them is the opposite of
	 * what one row of a list table has room for. The plain radio group it
	 * inherits from does fit, which is why this has to say so.
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
		return array_merge( parent::config_keys(), [ 'columns' ] );
	}
}
