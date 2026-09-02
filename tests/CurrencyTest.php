<?php
/**
 * The currency field.
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
 * A select over every currency, storing the ISO code.
 *
 * The list comes from wp-money -- stood in for here, since the package is
 * suggested rather than required -- and a store that supports three
 * currencies passes its own. Either way the option list is the allow-list,
 * and a code is the same code in any case.
 */
final class CurrencyTest extends TestCase {

	/**
	 * Build a field the way FieldSet does, defaults merged in.
	 *
	 * @param array<string, mixed> $config Field configuration.
	 * @param mixed                $value  Its value.
	 *
	 * @return Field
	 */
	private function field( array $config = [], mixed $value = null ): Field {
		$type = ( new Registry() )->get( 'currency' );

		return new Field(
			'currency',
			$type,
			array_merge( $type->defaults(), [ 'label' => 'Currency', 'input_name' => 'currency' ], $config ),
			$value
		);
	}

	/**
	 * Sanitise through the type.
	 *
	 * @param mixed                $value  Submitted value.
	 * @param array<string, mixed> $config Field configuration.
	 *
	 * @return mixed
	 */
	private function sanitize( mixed $value, array $config = [] ): mixed {
		$field = $this->field( $config );

		return $field->type()->sanitize( $value, $field );
	}

	/**
	 * The options are wp-money's, code as value and the full label shown.
	 */
	public function test_it_renders_the_currencies(): void {
		$html = ( new Renderer() )->render( $this->field() );

		$this->assertStringContainsString( '<option value="GBP">GBP - British Pound (£)</option>', $html );
		$this->assertStringContainsString( '<option value="USD">USD - US Dollar ($)</option>', $html );
		$this->assertStringContainsString( '<option value="">Select a currency</option>', $html );
	}

	/**
	 * A code arrives in any case and is stored in upper case.
	 */
	public function test_a_lowercase_code_is_uppercased(): void {
		$this->assertSame( 'GBP', $this->sanitize( 'gbp' ) );
		$this->assertSame( 'GBP', $this->sanitize( 'Gbp ' ) );
		$this->assertSame( 'GBP', $this->sanitize( 'GBP' ) );
	}

	/**
	 * A code that is not on offer is stored as nothing.
	 */
	public function test_an_unknown_code_becomes_nothing(): void {
		$this->assertSame( '', $this->sanitize( 'XXX' ) );
		$this->assertSame( '', $this->sanitize( 'pounds' ) );
		$this->assertSame( '', $this->sanitize( [ 'GBP' ] ) );
	}

	/**
	 * A store passes its own list, and the list is the allow-list.
	 *
	 * This path has to work without wp-money at all, which is why nothing
	 * beyond the options is consulted.
	 */
	public function test_a_custom_list_works(): void {
		$config = [ 'options' => [ 'ZAR' => 'Rand', 'NGN' => 'Naira' ] ];

		$html = ( new Renderer() )->render( $this->field( $config ) );

		$this->assertStringContainsString( '<option value="ZAR">Rand</option>', $html );
		$this->assertStringNotContainsString( 'GBP', $html );

		$this->assertSame( 'ZAR', $this->sanitize( 'zar', $config ) );
		$this->assertSame( '', $this->sanitize( 'GBP', $config ) );
	}

	/**
	 * Several currencies at once, each raised and each checked.
	 */
	public function test_it_can_take_several(): void {
		$this->assertSame(
			[ 'GBP', 'USD' ],
			$this->sanitize( [ 'gbp', 'xxx', 'usd' ], [ 'multiple' => true ] )
		);
	}

	/**
	 * The stored code is selected on the way back.
	 */
	public function test_the_stored_code_is_selected(): void {
		$html = ( new Renderer() )->render( $this->field( [], 'USD' ) );

		$this->assertStringContainsString( '<option value="USD" selected>', $html );
		$this->assertSame( 'currency', ( new Registry() )->get( 'currency' )->id() );
	}
}
