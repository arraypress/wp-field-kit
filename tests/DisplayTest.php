<?php
/**
 * Display tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Support\Display;
use PHPUnit\Framework\TestCase;

/**
 * Showing a value rather than collecting one.
 *
 * This existed twice before it existed here, and the two disagreed about zero:
 * a price of 0.00 read as an em-dash in a flyout and as 0.00 in the list table
 * beside it. Which is the whole reason the emptiness rule is pinned down here
 * rather than left to whichever `empty()` a component reached for.
 */
final class DisplayTest extends TestCase {

	/**
	 * Zero is a value, in every type it arrives in.
	 *
	 * A count of nought, a price of nought and a rate of nought are all
	 * facts. `0.0` is the one the flyouts version got wrong, because
	 * `empty()` is a question about truthiness and this is not.
	 *
	 * @dataProvider valueProvider
	 *
	 * @param mixed  $value The value.
	 * @param string $label What it is, for the failure message.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'valueProvider' )]
	public function test_a_value_is_not_nothing( mixed $value, string $label ): void {
		$this->assertFalse( Display::is_empty( $value ), sprintf( '%s reads as nothing.', $label ) );
	}

	/**
	 * Things that are values.
	 *
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function valueProvider(): array {
		return [
			'integer zero' => [ 0, 'Integer zero' ],
			'float zero'   => [ 0.0, 'Float zero' ],
			'string zero'  => [ '0', 'String zero' ],
			'empty array'  => [ [], 'An empty array' ],
			'a string'     => [ 'x', 'A string' ],
		];
	}

	/**
	 * Three things mean nothing was put here.
	 */
	public function test_what_counts_as_nothing(): void {
		$this->assertTrue( Display::is_empty( null ) );
		$this->assertTrue( Display::is_empty( '' ) );

		// What a lookup returns when it finds nothing, which is why it is not
		// "No" — see the class docblock.
		$this->assertTrue( Display::is_empty( false ) );
	}

	/**
	 * Nothing renders a placeholder a screen reader can read.
	 *
	 * A bare em-dash is announced as "dash" by some and passed over by
	 * others, and neither says what it means.
	 */
	public function test_nothing_renders_a_readable_placeholder(): void {
		$html = Display::text( null );

		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( 'screen-reader-text', $html );
		$this->assertStringContainsString( '&mdash;', $html );
	}

	/**
	 * A caller can say what nothing looks like, and it is escaped.
	 *
	 * An info grid used to escape the result a second time, so the default
	 * placeholder's own markup printed as `&lt;em&gt;` on the page. One
	 * parameter, escaped here, is what makes that impossible in both
	 * directions: a caller cannot smuggle markup through it and cannot have
	 * this class's markup escaped by escaping the result.
	 */
	public function test_a_placeholder_can_be_given_and_is_escaped(): void {
		$this->assertSame( 'None yet', Display::text( '', 'None yet' ) );
		$this->assertStringNotContainsString( '<em>', Display::text( '', '<em>none</em>' ) );
	}

	/**
	 * Zero prints as zero rather than as a dash.
	 *
	 * The bug this class exists to make impossible.
	 */
	public function test_zero_prints_as_zero(): void {
		$this->assertSame( '0', Display::text( 0 ) );
		$this->assertSame( '0', Display::text( 0.0 ) );
		$this->assertSame( '0', Display::text( '0' ) );
	}

	/**
	 * A list is joined.
	 */
	public function test_a_list_is_joined(): void {
		$this->assertSame( 'a, b', Display::text( [ 'a', 'b' ] ) );

		// An empty list is a value — a fact about the row — so it prints as
		// nothing at all rather than as a dash.
		$this->assertSame( '', Display::text( [] ) );
	}

	/**
	 * An object says its own name if it can, and nothing if it cannot.
	 *
	 * Casting one that cannot is a fatal, and printing its class name tells a
	 * reader nothing.
	 */
	public function test_an_object_is_used_only_when_it_can_speak(): void {
		$speaks = new class {
			public function __toString(): string {
				return 'Ada Lovelace';
			}
		};

		$this->assertSame( 'Ada Lovelace', Display::text( $speaks ) );
		$this->assertStringContainsString( 'screen-reader-text', Display::text( new \stdClass() ) );
	}

	/**
	 * A value out of somebody's database is escaped.
	 *
	 * Every caller is putting the result straight into a page.
	 */
	public function test_a_value_is_escaped(): void {
		$this->assertStringNotContainsString( '<script', Display::text( '<script>alert(1)</script>' ) );
		$this->assertStringNotContainsString( '<script', Display::text( [ '<script>alert(1)</script>' ] ) );
	}
}
