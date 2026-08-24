<?php
/**
 * Amount tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\FieldSet;
use PHPUnit\Framework\TestCase;

/**
 * A number with a unit attached.
 *
 * Two shapes that are the same control: a unit the person chooses, which is a
 * select and gets written somewhere, and a unit that is fixed, which is text
 * and does not. The flyouts library had the second as a component of its own —
 * a `unit_input` with 376 lines and its own hand-rolled chevron — before it
 * was this.
 */
final class AmountTest extends TestCase {

	/**
	 * Render one field.
	 *
	 * @param array<string, mixed> $config Field configuration.
	 *
	 * @return string
	 */
	private function render( array $config ): string {
		$set = new FieldSet(
			[ 'rate' => array_merge( [ 'type' => 'amount_type', 'label' => 'Rate' ], $config ) ],
			new ArrayContext(),
			''
		);

		return $set->render_field( $set->field( 'rate' ) );
	}

	/**
	 * A chosen unit is a select, and is written to its own key.
	 *
	 * Consumers query and sort on it independently of the amount, which is
	 * why it is a second column rather than a suffix on the value.
	 */
	public function test_a_chosen_unit_is_a_select(): void {
		$html = $this->render( [] );

		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'name="rate_type"', $html );
		$this->assertStringContainsString( 'field-kit__amount-unit', $html );
	}

	/**
	 * The unit select is labelled.
	 *
	 * A bare select beside a number is announced as an unlabelled combo box,
	 * which tells someone using a screen reader nothing about what it does.
	 */
	public function test_the_unit_select_is_labelled(): void {
		$this->assertStringContainsString( 'for="rate_unit"', $this->render( [] ) );
	}

	/**
	 * A fixed unit is text, not a control.
	 *
	 * And hidden from assistive technology: it is decoration on a field whose
	 * label already says what it measures, and announcing "percent" after the
	 * number adds nothing.
	 */
	public function test_a_fixed_unit_is_text(): void {
		$html = $this->render( [ 'unit' => '%' ] );

		$this->assertStringNotContainsString( '<select', $html );
		$this->assertStringContainsString( 'field-kit__amount-unit--fixed', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( '>%<', $html );

		// And nothing is written for it, because there is nothing to write.
		$this->assertStringNotContainsString( 'name="rate_type"', $html );
	}

	/**
	 * The unit can sit on the left.
	 *
	 * Which is what a currency wants in most of the world's locales.
	 */
	public function test_the_unit_can_come_first(): void {
		$html = $this->render( [ 'unit' => '$', 'unit_position' => 'prefix' ] );

		$this->assertStringContainsString( 'field-kit__amount--prefix', $html );

		// The unit really is before the input, not just classed as if it were.
		$this->assertLessThan(
			strpos( $html, '<input' ),
			strpos( $html, 'field-kit__amount-unit--fixed' ),
			'The prefix unit renders after the input.'
		);
	}

	/**
	 * A fixed unit is escaped.
	 */
	public function test_a_fixed_unit_is_escaped(): void {
		$this->assertStringNotContainsString(
			'<script',
			$this->render( [ 'unit' => '<script>alert(1)</script>' ] )
		);
	}

	/**
	 * The amount is clamped by its own bounds.
	 */
	public function test_the_amount_is_clamped(): void {
		$context = new ArrayContext();

		$set = new FieldSet(
			[
				'rate' => [
					'type' => 'amount_type',
					'min'  => 0,
					'max'  => 100,
				],
			],
			$context,
			''
		);

		$set->save( [ 'rate' => '150' ] );
		$this->assertSame( 100.0, $context->values()['rate'] );

		$set->save( [ 'rate' => '-5' ] );
		$this->assertSame( 0.0, $context->values()['rate'] );
	}

	/**
	 * Something that is not a number stores nothing rather than zero.
	 *
	 * A rate of zero is a real rate; an empty box is not one, and storing 0.0
	 * for it would make "no discount" and "a discount of nothing" the same
	 * row. The field set deletes rather than writes an empty value, so the
	 * key is absent — which is what a later read turns back into the default.
	 */
	public function test_a_non_number_stores_nothing_rather_than_zero(): void {
		$context = new ArrayContext();

		$set = new FieldSet( [ 'rate' => [ 'type' => 'amount_type' ] ], $context, '' );
		$set->save( [ 'rate' => 'nonsense' ] );

		$this->assertArrayNotHasKey( 'rate', $context->values() );
	}

	/**
	 * A rate of zero is stored, because it is a rate.
	 */
	public function test_zero_is_stored(): void {
		$context = new ArrayContext();

		$set = new FieldSet( [ 'rate' => [ 'type' => 'amount_type' ] ], $context, '' );
		$set->save( [ 'rate' => '0' ] );

		$this->assertSame( 0.0, $context->values()['rate'] );
	}
}
