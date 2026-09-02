<?php
/**
 * Submitted shape tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A request is not obliged to send the shape the field expects.
 *
 * `field[]=x` where `field=x` was expected is one line of a crafted request,
 * and `(string) $value` on it stores the word "Array" with a warning. Every
 * text-shaped type has to read that as nothing.
 */
final class SubmittedShapeTest extends TestCase {

	/**
	 * Reset the stubbed capability list.
	 */
	protected function setUp(): void {
		$GLOBALS['fk_user_can'] = [];
	}

	/**
	 * Every type that stores text.
	 *
	 * @return array<string, array{string}>
	 */
	public static function text_types(): array {
		$types = [ 'text', 'email', 'url', 'tel', 'password', 'hidden', 'textarea', 'code', 'wysiwyg', 'date', 'time', 'datetime', 'color', 'oembed', 'file_url', 'license', 'money' ];

		return array_combine( $types, array_map( static fn( string $type ) => [ $type ], $types ) );
	}

	/**
	 * Sanitize a value through a type.
	 *
	 * @param string $type  Field type.
	 * @param mixed  $value Submitted value.
	 *
	 * @return mixed
	 */
	private function sanitize( string $type, mixed $value ): mixed {
		$registry = new Registry();
		$field    = new Field( 'demo', $registry->get( $type ), [ 'input_name' => 'demo' ], null );

		return $registry->get( $type )->sanitize( $value, $field );
	}

	/**
	 * An array where text was expected is nothing.
	 *
	 * @param string $type Field type.
	 */
	#[DataProvider( 'text_types' )]
	public function test_an_array_where_text_was_expected_is_nothing( string $type ): void {
		$clean = $this->sanitize( $type, [ 'x', 'y' ] );

		$this->assertNotSame( 'Array', $clean, $type );
		$this->assertNotSame( 'Array', is_array( $clean ) ? '' : (string) $clean, $type );
		$this->assertStringNotContainsString( 'Array', is_scalar( $clean ) ? (string) $clean : '', $type );
	}

	/**
	 * A code field is filtered for anyone without unfiltered_html.
	 *
	 * The same line core draws for post content: a contributor with a code
	 * field in a metabox must not be able to store a script the theme then
	 * prints.
	 */
	public function test_code_is_filtered_without_unfiltered_html(): void {
		$GLOBALS['fk_user_can']['unfiltered_html'] = false;

		$this->assertSame( 'alert(1)', $this->sanitize( 'code', '<script>alert(1)</script>' ) );
	}

	/**
	 * And stored as written for anyone with it.
	 */
	public function test_code_is_kept_with_unfiltered_html(): void {
		$this->assertSame( '<script>alert(1)</script>', $this->sanitize( 'code', '<script>alert(1)</script>' ) );
	}
}
