<?php
/**
 * Conditional logic tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Conditions;
use PHPUnit\Framework\TestCase;

/**
 * Conditions exist because the two predecessor libraries spelled and shaped
 * this differently, and because passing config straight through to the script
 * killed conditional logic on every settings screen with
 * "conditions.forEach is not a function" — the config was a map and the
 * script expected a list.
 */
final class ConditionsTest extends TestCase {

	/**
	 * The short map form is the one people write.
	 */
	public function test_short_map_form(): void {
		$conditions = Conditions::from( [ 'enabled' => 1 ] );

		$this->assertSame(
			[ [ 'field' => 'enabled', 'operator' => '=', 'value' => 1 ] ],
			$conditions->to_array()
		);
	}

	/**
	 * An array on the right of the short form means "one of".
	 */
	public function test_short_map_with_an_array_becomes_in(): void {
		$conditions = Conditions::from( [ 'mode' => [ 'a', 'b' ] ] );

		$this->assertSame( 'in', $conditions->to_array()[0]['operator'] );
	}

	/**
	 * The long list form passes through.
	 */
	public function test_long_list_form(): void {
		$conditions = Conditions::from(
			[ [ 'field' => 'count', 'operator' => '>', 'value' => 3 ] ]
		);

		$this->assertSame( '>', $conditions->to_array()[0]['operator'] );
	}

	/**
	 * A single long-form condition, not wrapped in a list.
	 */
	public function test_single_long_form(): void {
		$conditions = Conditions::from( [ 'field' => 'count', 'operator' => '>=', 'value' => 2 ] );

		$this->assertCount( 1, $conditions->to_array() );
		$this->assertSame( 'count', $conditions->to_array()[0]['field'] );
	}

	/**
	 * Whatever went in, what comes out is a JSON array — never an object.
	 *
	 * This is the property that broke in production.
	 */
	public function test_output_is_always_a_json_list(): void {
		foreach (
			[
				[ 'a' => 1 ],
				[ 'a' => 1, 'b' => 2 ],
				[ [ 'field' => 'a', 'value' => 1 ] ],
				[ 'field' => 'a', 'value' => 1 ],
			] as $input
		) {
			$encoded = wp_json_encode( Conditions::from( $input )->to_array() );

			$this->assertStringStartsWith( '[', (string) $encoded, 'A JSON object has no forEach.' );
			$this->assertIsList( Conditions::from( $input )->to_array() );
		}
	}

	/**
	 * An unknown operator falls back to equality rather than being honoured.
	 */
	public function test_unknown_operator_falls_back(): void {
		$conditions = Conditions::from( [ [ 'field' => 'a', 'operator' => 'DROP TABLE', 'value' => 1 ] ] );

		$this->assertSame( '=', $conditions->to_array()[0]['operator'] );
	}

	/**
	 * Comparison is loose, because a form value is a string and config is PHP.
	 */
	public function test_comparison_is_loose_between_string_and_int(): void {
		$this->assertTrue( Conditions::from( [ 'a' => 1 ] )->are_met( [ 'a' => '1' ] ) );
		$this->assertTrue( Conditions::from( [ 'a' => '1' ] )->are_met( [ 'a' => 1 ] ) );
		$this->assertFalse( Conditions::from( [ 'a' => 1 ] )->are_met( [ 'a' => '0' ] ) );
	}

	/**
	 * Every operator behaves.
	 */
	public function test_operators(): void {
		$cases = [
			[ '!=', 2, 1, true ],
			[ '>', 1, 2, true ],
			[ '>=', 2, 2, true ],
			[ '<', 2, 1, true ],
			[ '<=', 2, 2, true ],
			[ 'in', [ 'a', 'b' ], 'b', true ],
			[ 'not_in', [ 'a', 'b' ], 'c', true ],
			[ 'contains', 'ell', 'hello', true ],
			[ 'not_contains', 'zzz', 'hello', true ],
			[ 'empty', '', '', true ],
			[ 'not_empty', '', 'x', true ],
			[ '>', 5, 2, false ],
			[ 'in', [ 'a' ], 'z', false ],
		];

		foreach ( $cases as [ $operator, $expected, $current, $want ] ) {
			$conditions = Conditions::from( [ [ 'field' => 'f', 'operator' => $operator, 'value' => $expected ] ] );

			$this->assertSame(
				$want,
				$conditions->are_met( [ 'f' => $current ] ),
				sprintf( 'operator %s with current %s', $operator, var_export( $current, true ) )
			);
		}
	}

	/**
	 * Every condition must hold, not just one.
	 */
	public function test_conditions_are_combined_with_and(): void {
		$conditions = Conditions::from( [ 'a' => 1, 'b' => 2 ] );

		$this->assertTrue( $conditions->are_met( [ 'a' => 1, 'b' => 2 ] ) );
		$this->assertFalse( $conditions->are_met( [ 'a' => 1, 'b' => 9 ] ) );
	}

	/**
	 * No conditions means nothing to hide.
	 */
	public function test_empty_conditions(): void {
		$conditions = Conditions::from( [] );

		$this->assertTrue( $conditions->is_empty() );
		$this->assertTrue( $conditions->are_met( [] ) );
	}

}
