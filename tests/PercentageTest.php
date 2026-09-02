<?php
/**
 * The percentage field.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\FieldSet;
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Types\NumberType;
use PHPUnit\Framework\TestCase;

/**
 * A share of something is a number from nought to a hundred with the sign
 * in the box. Everything else is the number field's, and has to stay so:
 * the clamp, the schema, the step deciding the stored type.
 *
 * Built through a field set rather than a bare Field, because the bounds
 * are defaults and a field set is where defaults are merged.
 */
final class PercentageTest extends TestCase {

	public function test_it_is_registered_as_a_kind_of_number(): void {
		$registry = new Registry();

		$this->assertTrue( $registry->has( 'percentage' ) );
		$this->assertInstanceOf( NumberType::class, $registry->get( 'percentage' ) );
		$this->assertSame( 'percentage', $registry->get( 'percentage' )->id() );
	}

	public function test_it_renders_as_a_bounded_number_with_the_sign_in_the_box(): void {
		$html = $this->render( [], '10' );
		$id   = $this->field()->input_id();

		$this->assertStringContainsString( 'type="number"', $html );
		$this->assertStringContainsString( 'min="0"', $html );
		$this->assertStringContainsString( 'max="100"', $html );
		$this->assertStringContainsString( 'step="1"', $html );
		$this->assertStringContainsString( 'small-text', $html );
		$this->assertStringContainsString( 'field-kit__adornment--suffix" aria-hidden="true">%</span>', $html );
		$this->assertStringContainsString( 'aria-describedby="' . $id . '__suffix"', $html );
	}

	public function test_it_clamps_to_the_hundred(): void {
		$field = $this->field();
		$type  = $field->type();

		$this->assertSame( 100, $type->sanitize( 150, $field ) );
		$this->assertSame( 0, $type->sanitize( -5, $field ) );
		$this->assertSame( 42, $type->sanitize( '42', $field ) );
		$this->assertSame( 0, $type->sanitize( 'lots', $field ) );
	}

	/**
	 * Saving goes through the same clamp.
	 */
	public function test_a_saved_value_is_clamped(): void {
		$context = new ArrayContext();
		$set     = new FieldSet( [ 'rate' => [ 'type' => 'percentage', 'label' => 'Rate' ] ], $context, 'demo' );

		$set->save( [ 'rate' => 150 ] );

		$this->assertSame( 100, $context->values()['rate'] );
	}

	public function test_the_schema_is_the_bounded_integer(): void {
		$field = $this->field();

		$this->assertSame(
			[
				'type'    => 'integer',
				'minimum' => 0,
				'maximum' => 100,
			],
			$field->type()->schema( $field )
		);
	}

	/**
	 * A caller that needs "2.5%" sets the step, and the stored type follows.
	 */
	public function test_a_caller_may_ask_for_fractions(): void {
		$field = $this->field( [ 'step' => 0.01 ] );
		$type  = $field->type();

		$this->assertSame( 12.5, $type->sanitize( '12.5', $field ) );
		$this->assertSame( 100.0, $type->sanitize( '150', $field ) );
		$this->assertSame( 'number', $type->schema( $field )['type'] );
		$this->assertStringContainsString( 'step="0.01"', $this->render( [ 'step' => 0.01 ], '12.5' ) );
	}

	public function test_a_caller_may_narrow_the_range(): void {
		$field = $this->field( [ 'max' => 50 ] );

		$this->assertSame( 50, $field->type()->sanitize( 80, $field ) );
		$this->assertStringContainsString( 'max="50"', $this->render( [ 'max' => 50 ], '5' ) );
	}

	/**
	 * A percentage field as a field set builds it, defaults merged.
	 *
	 * @param array<string, mixed> $config Field configuration.
	 *
	 * @return Field
	 */
	private function field( array $config = [] ): Field {
		$set = new FieldSet(
			[ 'rate' => array_merge( [ 'type' => 'percentage', 'label' => 'Rate' ], $config ) ],
			new ArrayContext(),
			'demo'
		);

		$field = $set->field( 'rate' );

		$this->assertInstanceOf( Field::class, $field );

		return $field;
	}

	/**
	 * Render a percentage field.
	 *
	 * @param array<string, mixed> $config Field configuration.
	 * @param string               $value  Its value.
	 *
	 * @return string
	 */
	private function render( array $config, string $value ): string {
		$set = new FieldSet(
			[ 'rate' => array_merge( [ 'type' => 'percentage', 'label' => 'Rate' ], $config ) ],
			new ArrayContext(),
			'demo'
		);

		return $set->render_field( $this->field( $config )->with_value( $value ) );
	}
}
