<?php
/**
 * Attribute builder tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Attributes;
use PHPUnit\Framework\TestCase;

/**
 * The whole reason this class exists is that a pre-escaped attribute *string*
 * gets escaped a second time by a caller who cannot tell it apart from data.
 * These tests pin the behaviour that makes that impossible.
 */
final class AttributesTest extends TestCase {

	/**
	 * An empty set renders nothing, not a stray space.
	 */
	public function test_empty_renders_nothing(): void {
		$this->assertSame( '', ( new Attributes() )->render() );
	}

	/**
	 * A populated set leads with a space so it can be concatenated directly
	 * after a tag name.
	 */
	public function test_render_leads_with_a_space(): void {
		$attributes = new Attributes( [ 'type' => 'text' ] );

		$this->assertSame( ' type="text"', $attributes->render() );
		$this->assertSame( '<input type="text" />', sprintf( '<input%s />', $attributes ) );
	}

	/**
	 * Values are escaped exactly once.
	 */
	public function test_values_are_escaped_once(): void {
		$attributes = new Attributes( [ 'value' => 'a "quoted" & <tagged> value' ] );

		$rendered = $attributes->render();

		$this->assertStringContainsString( '&quot;quoted&quot;', $rendered );
		$this->assertStringContainsString( '&amp;', $rendered );
		$this->assertStringNotContainsString( '&amp;quot;', $rendered, 'Value was escaped twice.' );
	}

	/**
	 * An array value becomes JSON, escaped once — the exact case that broke
	 * conditional logic when a caller escaped the encoded string again.
	 */
	public function test_array_values_encode_to_parseable_json(): void {
		$conditions = [ [ 'field' => 'a', 'operator' => '=', 'value' => 1 ] ];

		$rendered = ( new Attributes( [ 'data-conditions' => $conditions ] ) )->render();

		$this->assertStringNotContainsString( '&amp;quot;', $rendered, 'JSON was escaped twice.' );

		// Recover the attribute value the way a browser would and re-parse it.
		preg_match( '/data-conditions="([^"]*)"/', $rendered, $m );
		$decoded = json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );

		$this->assertSame( $conditions, $decoded );
		$this->assertIsList( $decoded, 'A map would have no forEach in the browser.' );
	}

	/**
	 * True renders as a bare boolean attribute; false and null are omitted.
	 */
	public function test_boolean_attributes(): void {
		$attributes = new Attributes( [ 'required' => true, 'disabled' => false, 'readonly' => null ] );

		$this->assertSame( ' required', $attributes->render() );
	}

	/**
	 * Classes accumulate rather than replace.
	 */
	public function test_classes_accumulate_and_deduplicate(): void {
		$attributes = new Attributes( [ 'class' => 'one two' ] );
		$attributes->add_class( 'three' )->add_class( 'two' );

		$this->assertSame( ' class="one two three"', $attributes->render() );
	}

	/**
	 * set_if only sets when the condition holds.
	 */
	public function test_set_if(): void {
		$attributes = new Attributes();
		$attributes->set_if( false, 'a', '1' )->set_if( true, 'b', '2' );

		$this->assertFalse( $attributes->has( 'a' ) );
		$this->assertTrue( $attributes->has( 'b' ) );
	}

	/**
	 * Names are case-insensitive, so a caller cannot set the same attribute
	 * twice by varying case.
	 */
	public function test_names_are_case_insensitive(): void {
		$attributes = new Attributes( [ 'Type' => 'text' ] );
		$attributes->set( 'TYPE', 'email' );

		$this->assertSame( ' type="email"', $attributes->render() );
	}

}
