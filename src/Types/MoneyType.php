<?php
/**
 * Money Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * An amount, with its currency symbol inside the control.
 *
 * One field rather than the two `amount_type` draws. That one is built for a
 * unit the person chooses -- a percentage or a flat amount -- so it puts a
 * select beside the number, and its fixed-unit form still sits the symbol
 * outside the input as a separate element on the same line. Beside a
 * full-width input in a narrow panel the symbol had nowhere to go and wrapped
 * onto a line of its own, which is a currency symbol floating above an
 * unlabelled box.
 *
 * A store's currency is a setting, not a per-price decision, so there is no
 * control to change it here. It can still differ from row to row -- prices
 * synced back from a gateway carry whatever they were created in -- so the
 * symbol can be driven by a sibling field holding the code rather than fixed
 * at render: give `currencies` the codes you support and `currency_key` the
 * sibling to read, and the script keeps the symbol in step.
 *
 * The input is text with an `inputmode`, not `type="number"`. A number input
 * puts spinners in a control this narrow, refuses a pasted "1,999.00"
 * outright, and reports a value the browser has already reformatted; money is
 * typed, and what was typed is what the caller parses.
 */
final class MoneyType extends AbstractType {

	/**
	 * The type id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'money';
	}

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'symbol'       => '',
			'currency'     => '',
			'currencies'   => [],
			'currency_key' => '',
		];
	}

	/**
	 * Render the control.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->set( 'type', 'text' );
		$attributes->set( 'value', (string) $field->value() );

		// Decimal rather than numeric: numeric is a digits-only keypad on
		// iOS, with no way to type the separator in a price.
		$attributes->set( 'inputmode', 'decimal' );
		$attributes->set( 'autocomplete', 'off' );
		$attributes->add_class( 'field-kit__money-value' );
		$attributes->set_if( '' !== $field->placeholder(), 'placeholder', $field->placeholder() );

		$symbol = $this->symbol( $field );

		$wrapper = new Attributes();
		$wrapper->add_class( 'field-kit__money' );

		// A three-letter code needs more room than "£". Measured here rather
		// than in CSS, which cannot see how long the affix is.
		if ( mb_strlen( $symbol ) > 1 ) {
			$wrapper->add_class( 'field-kit__money--wide' );
		}

		// Only when there is something to switch between. A single-currency
		// store gets static text and no script.
		$currencies = (array) $field->get( 'currencies', [] );
		$sibling    = (string) $field->get( 'currency_key', '' );

		if ( '' !== $sibling && count( $currencies ) > 1 ) {
			$wrapper->set( 'data-currency-key', $sibling );
			$wrapper->set( 'data-symbols', $currencies );
		}

		return sprintf(
			'<div%s><span class="field-kit__money-symbol" aria-hidden="true">%s</span><input%s /></div>',
			$wrapper->render(),
			esc_html( $symbol ),
			$attributes->render()
		);
	}

	/**
	 * The symbol to draw.
	 *
	 * `symbol` outright where one is given, otherwise the entry in
	 * `currencies` for the current code. Falls back to the code itself, which
	 * is wrong-looking but readable -- an empty affix is a control with a gap
	 * where its symbol should be and no way to tell what it is denominated
	 * in.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function symbol( Field $field ): string {
		$symbol = (string) $field->get( 'symbol', '' );

		if ( '' !== $symbol ) {
			return $symbol;
		}

		$code       = (string) $field->get( 'currency', '' );
		$currencies = (array) $field->get( 'currencies', [] );

		return (string) ( $currencies[ $code ] ?? $code );
	}

	/**
	 * Coerce a submitted value.
	 *
	 * Kept as the text it was typed as, less anything that cannot be part of
	 * an amount. Parsing it is the caller's, because how many minor units a
	 * major one holds is a property of the currency -- yen has none -- and
	 * this field has no currency table to consult.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public function sanitize( mixed $value, Field $field ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		// Digits, separators and a leading minus. A refund or an adjustment
		// is a negative amount, so the sign survives.
		return trim( (string) preg_replace( '/[^0-9.,\-]/', '', $this->scalar( $value ) ) );
	}

	/**
	 * It has a placeholder like any other text input.
	 *
	 * @return bool
	 */
	public function supports_placeholder(): bool {
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
			[ 'currencies', 'currency', 'currency_key', 'symbol' ]
		);
	}
}
