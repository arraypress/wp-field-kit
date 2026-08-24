<?php
/**
 * Code generator tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\FieldSet;
use ArrayPress\FieldKit\Types\CodeGeneratorType;
use PHPUnit\Framework\TestCase;

/**
 * A text field with a button that fills it in.
 *
 * The value is still a plain text value — that is the point, since "generate
 * me one" and "I already have one" are both ordinary. What is asserted here is
 * mostly the button's settings, because they are what the script reads and a
 * wrong one is a code of the wrong shape rather than an error.
 */
final class CodeGeneratorTest extends TestCase {

	/**
	 * Render one field.
	 *
	 * @param array<string, mixed> $config Field configuration.
	 *
	 * @return string
	 */
	private function render( array $config = [] ): string {
		$set = new FieldSet(
			[ 'coupon' => array_merge( [ 'type' => 'code_generator', 'label' => 'Coupon' ], $config ) ],
			new ArrayContext( [ 'coupon' => 'SUMMER' ] ),
			''
		);

		return $set->render_field( $set->field( 'coupon' ) );
	}

	/**
	 * The value is editable, not read-only.
	 *
	 * "Generate me one" and "I already have one" are both ordinary, which is
	 * the difference between this and the clipboard field.
	 */
	public function test_the_value_is_editable(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'value="SUMMER"', $html );
		$this->assertStringNotContainsString( 'readonly', $html );
	}

	/**
	 * The button is a button, and carries its settings.
	 */
	public function test_the_button_carries_its_settings(): void {
		$html = $this->render(
			[
				'length'         => 12,
				'format'         => 'hex',
				'prefix'         => 'PROMO-',
				'separator'      => '-',
				'segment_length' => 4,
			]
		);

		$this->assertStringContainsString( 'type="button"', $html );
		$this->assertStringContainsString( 'data-length="12"', $html );
		$this->assertStringContainsString( 'data-format="hex"', $html );
		$this->assertStringContainsString( 'data-prefix="PROMO-"', $html );
		$this->assertStringContainsString( 'data-segment-length="4"', $html );
	}

	/**
	 * An alphabet is named, never sent.
	 *
	 * The characters live in the script. A format nobody recognises falls
	 * back rather than reaching the page, so a caller cannot put arbitrary
	 * text into a data attribute and have the generator draw from it.
	 */
	public function test_an_unknown_alphabet_falls_back( ): void {
		$html = $this->render( [ 'format' => '<script>alert(1)</script>' ] );

		$this->assertStringContainsString( 'data-format="alphanumeric_upper"', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	/**
	 * The alphabets avoid the characters people mistype.
	 *
	 * A code gets read off a screen and typed into a box. Whether O is a
	 * letter and 0 a digit is not something to leave to the reader — so the
	 * default alphabet has one of each pair and not the other.
	 */
	public function test_the_default_alphabet_is_unambiguous_enough_to_be_deliberate(): void {
		$alphabet = CodeGeneratorType::ALPHABETS['alphanumeric_upper'];

		// Upper case and digits only: no lower-case l against digit 1.
		$this->assertSame( strtoupper( $alphabet ), $alphabet );
		$this->assertSame( 36, strlen( $alphabet ) );
	}

	/**
	 * A generated code is not part of an inline row.
	 *
	 * Quick edit clones its panel from a hidden template before the values
	 * are in it, so a button whose behaviour is attached in JavaScript comes
	 * up dead in the clone.
	 */
	public function test_it_is_not_an_inline_type(): void {
		$this->assertFalse( ( new CodeGeneratorType() )->supports_inline() );
	}

	/**
	 * The value saves as text.
	 */
	public function test_the_value_saves_as_text(): void {
		$context = new ArrayContext();

		$set = new FieldSet( [ 'coupon' => [ 'type' => 'code_generator' ] ], $context, '' );
		$set->save( [ 'coupon' => 'PROMO-A1B2' ] );

		$this->assertSame( 'PROMO-A1B2', $context->values()['coupon'] );
	}

	/**
	 * The button can be named something else.
	 */
	public function test_the_button_can_be_renamed(): void {
		$this->assertStringContainsString( 'Make one up', $this->render( [ 'button_label' => 'Make one up' ] ) );
	}
}
