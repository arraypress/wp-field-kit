<?php
/**
 * The money field.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * An amount and its symbol are one control, not two elements on a line.
 *
 * The distinction is the whole reason this type exists: amount_type draws the
 * symbol beside the input, and beside a full-width input in a narrow panel it
 * wrapped onto a line of its own.
 */
final class MoneyTest extends TestCase {

	public function test_the_symbol_sits_inside_the_control(): void {
		$html = $this->render( [ 'symbol' => '£' ], '19.99' );

		$this->assertStringContainsString( 'field-kit__money-symbol', $html );
		$this->assertStringContainsString( '£', $html );
		$this->assertStringContainsString( 'value="19.99"', $html );

		// The symbol precedes the input, and both are inside one wrapper.
		$this->assertMatchesRegularExpression(
			'/<div class="field-kit__money">.*money-symbol.*<input/s',
			$html
		);
	}

	/**
	 * A number input puts spinners in a control this narrow, refuses a pasted
	 * "1,999.00", and reports a value the browser has already reformatted.
	 */
	public function test_the_input_is_text_with_a_decimal_keypad(): void {
		$html = $this->render( [ 'symbol' => '$' ], '10' );

		$this->assertStringContainsString( 'type="text"', $html );
		$this->assertStringContainsString( 'inputmode="decimal"', $html );
		$this->assertStringNotContainsString( 'type="number"', $html );
	}

	/**
	 * A store's currency is a setting, so there is nothing here to change it
	 * with -- which is what separates this from amount_type.
	 */
	public function test_there_is_no_currency_control(): void {
		$html = $this->render(
			[ 'currency' => 'GBP', 'currencies' => [ 'GBP' => '£', 'USD' => '$' ] ],
			'5'
		);

		$this->assertStringNotContainsString( '<select', $html );
		$this->assertStringContainsString( '£', $html );
	}

	/**
	 * Prices synced back from a gateway carry whatever they were created in,
	 * so a row can be denominated differently from the store.
	 */
	public function test_a_row_can_carry_its_own_currency(): void {
		$html = $this->render(
			[
				'currency'     => 'USD',
				'currencies'   => [ 'GBP' => '£', 'USD' => '$' ],
				'currency_key' => 'currency',
			],
			'5'
		);

		$this->assertStringContainsString( '$', $html );
		$this->assertStringContainsString( 'data-currency-key="currency"', $html );
		$this->assertStringContainsString( 'GBP', $html );
	}

	/**
	 * One currency needs no script and no data to drive it.
	 */
	public function test_a_single_currency_store_carries_no_switching_data(): void {
		$html = $this->render(
			[ 'currency' => 'GBP', 'currencies' => [ 'GBP' => '£' ], 'currency_key' => 'currency' ],
			'5'
		);

		$this->assertStringNotContainsString( 'data-currency-key', $html );
		$this->assertStringNotContainsString( 'data-symbols', $html );
	}

	/**
	 * An unknown code is drawn as itself. An empty affix is a control with a
	 * gap where its symbol should be and no way to tell what it is in.
	 */
	public function test_an_unknown_currency_falls_back_to_its_code(): void {
		$this->assertStringContainsString( 'XYZ', $this->render( [ 'currency' => 'XYZ' ], '1' ) );
	}

	/**
	 * A code standing in for a symbol needs the room a symbol does not, and
	 * CSS cannot see how long the affix is.
	 */
	public function test_a_long_affix_gets_more_room(): void {
		$this->assertStringContainsString(
			'field-kit__money--wide',
			$this->render( [ 'symbol' => 'CHF' ], '1' )
		);
		$this->assertStringNotContainsString(
			'field-kit__money--wide',
			$this->render( [ 'symbol' => '£' ], '1' )
		);
	}

	/**
	 * What was typed is what the caller parses: how many minor units a major
	 * one holds is a property of the currency, and this field has no table.
	 */
	public function test_it_keeps_what_was_typed_less_anything_that_is_not_money(): void {
		$type  = ( new Registry() )->get( 'money' );
		$field = new Field( 'amount', $type, [ 'label' => 'Amount' ], null );

		$this->assertSame( '1,999.00', $type->sanitize( '£1,999.00', $field ) );
		$this->assertSame( '19.99', $type->sanitize( ' 19.99 ', $field ) );
		$this->assertSame( '-5.00', $type->sanitize( '-5.00', $field ) );
		$this->assertSame( '', $type->sanitize( 'free', $field ) );
		$this->assertSame( '', $type->sanitize( [ 'x' ], $field ) );
	}

	/**
	 * Render a money field.
	 *
	 * @param array<string, mixed> $config Field configuration.
	 * @param string               $value  Its value.
	 *
	 * @return string
	 */
	private function render( array $config, string $value ): string {
		$field = new Field(
			'amount',
			( new Registry() )->get( 'money' ),
			array_merge( [ 'label' => 'Amount', 'input_name' => 'amount' ], $config ),
			null
		);

		return ( new Renderer() )->render( $field->with_value( $value ) );
	}
}
