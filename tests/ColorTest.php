<?php
/**
 * Colour contrast tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Support\Color;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A colour field stores a hex value and something has to render with it. That
 * thing needs text on top, and whether the text is black or white is not a
 * decision the person picking the colour made.
 *
 * Core sanitizes a hex value and stops there, which is why this exists and why
 * it is only this.
 */
final class ColorTest extends TestCase {

	/**
	 * A hex colour is read in any of the forms a field stores.
	 */
	public function test_a_hex_colour_is_read_in_any_form(): void {
		$this->assertSame( array( 255, 0, 0 ), Color::rgb( '#ff0000' ) );
		$this->assertSame( array( 255, 0, 0 ), Color::rgb( 'ff0000' ) );
		$this->assertSame( array( 255, 0, 0 ), Color::rgb( '#FF0000' ) );

		// The short form, which core's picker will happily hand you.
		$this->assertSame( array( 255, 0, 0 ), Color::rgb( '#f00' ) );
		$this->assertSame( array( 170, 187, 204 ), Color::rgb( '#abc' ) );
	}

	/**
	 * Anything that is not a colour is null, not black.
	 */
	public function test_anything_else_is_null(): void {
		foreach ( array( '', '#', 'red', '#gggggg', '#ff00', '#ff00000', 'rgb(1,2,3)' ) as $bad ) {
			$this->assertNull( Color::rgb( $bad ), sprintf( '"%s" was read as a colour.', $bad ) );
		}
	}

	/**
	 * Black text on light, white text on dark.
	 *
	 * @param string $background The colour picked.
	 * @param string $expect     What should sit on it.
	 */
	#[DataProvider( 'contrastProvider' )]
	public function test_the_readable_text_colour_is_chosen( string $background, string $expect ): void {
		$this->assertSame( $expect, Color::contrast( $background ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function contrastProvider(): array {
		return array(
			'white'        => array( '#ffffff', '#000000' ),
			'black'        => array( '#000000', '#ffffff' ),
			'a light grey' => array( '#eeeeee', '#000000' ),
			'a dark grey'  => array( '#222222', '#ffffff' ),
			'yellow'       => array( '#ffff00', '#000000' ),
			'navy'         => array( '#001f3f', '#ffffff' ),

			// Green is the one that catches naive brightness formulas: it
			// carries most of the luminance, so pure green is light.
			'green'        => array( '#00ff00', '#000000' ),
			'blue'         => array( '#0000ff', '#ffffff' ),

			// Two hundred and sixty-six colours get a different answer from
			// the YIQ coefficients than from WCAG's, even with the gamma
			// correction applied. These are two of them, and they are not
			// exotic -- a link blue and a brand teal.
			'a link blue'  => array( '#0066ff', '#ffffff' ),
			'a brand teal' => array( '#008866', '#000000' ),
		);
	}

	/**
	 * A colour it cannot read gets black, which is legible on the page's own
	 * background.
	 */
	public function test_an_unreadable_colour_gets_black(): void {
		$this->assertSame( '#000000', Color::contrast( 'nonsense' ) );
	}

	/**
	 * The ratio is WCAG's, not an approximation of it.
	 *
	 * Black on white is 21:1 exactly, which is the definition's own upper
	 * bound and the easiest way to tell a real implementation from a
	 * plausible one.
	 */
	public function test_the_ratio_is_the_real_one(): void {
		$this->assertEqualsWithDelta( 21.0, Color::ratio( '#000000', '#ffffff' ), 0.01 );
		$this->assertEqualsWithDelta( 1.0, Color::ratio( '#123456', '#123456' ), 0.01 );

		// Order does not matter.
		$this->assertSame( Color::ratio( '#000000', '#ffffff' ), Color::ratio( '#ffffff', '#000000' ) );

		$this->assertNull( Color::ratio( '#ffffff', 'nonsense' ) );
	}

	/**
	 * The mid-grey that the YIQ approximation gets wrong.
	 *
	 * #767676 is the WCAG AA boundary against white -- 4.54:1, which passes.
	 * A brightness-based formula reads it as light and puts black on it, at
	 * 4.62:1. Both are near the line, and only one of them is the number an
	 * audit will use.
	 */
	public function test_the_mid_grey_agrees_with_wcag(): void {
		$against_white = Color::ratio( '#767676', '#ffffff' );
		$against_black = Color::ratio( '#767676', '#000000' );

		$this->assertNotNull( $against_white );
		$this->assertNotNull( $against_black );

		$this->assertEqualsWithDelta( 4.54, $against_white, 0.05 );
		$this->assertEqualsWithDelta( 4.62, $against_black, 0.05 );

		// Black wins, narrowly, and that is what contrast() should say.
		$this->assertSame( '#000000', Color::contrast( '#767676' ) );
	}

	/**
	 * Readability is asked at the threshold the text size sets.
	 */
	public function test_readability_uses_the_right_threshold(): void {
		// 4.54:1 -- passes AA for body text, and for large text.
		$this->assertTrue( Color::readable( '#767676', '#ffffff' ) );
		$this->assertTrue( Color::readable( '#767676', '#ffffff', true ) );

		// 3.9:1 -- fails body text, passes large.
		$this->assertFalse( Color::readable( '#949494', '#ffffff' ) );
		$this->assertTrue( Color::readable( '#949494', '#ffffff', true ) );

		$this->assertFalse( Color::readable( '#ffffff', 'nonsense' ) );
	}
}
