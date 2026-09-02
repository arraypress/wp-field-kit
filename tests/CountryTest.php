<?php
/**
 * The country field.
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
 * A select over every country, storing the ISO code.
 *
 * The code is the contract. A country field that stores "UK" or "Britain"
 * is a country field every other library will misread, so what is pinned
 * down here is that nothing but a real code gets through, whatever the
 * option list looked like.
 */
final class CountryTest extends TestCase {

	/**
	 * Build a field the way FieldSet does, defaults merged in.
	 *
	 * @param array<string, mixed> $config Field configuration.
	 * @param mixed                $value  Its value.
	 *
	 * @return Field
	 */
	private function field( array $config = [], mixed $value = null ): Field {
		$type = ( new Registry() )->get( 'country' );

		return new Field(
			'country',
			$type,
			array_merge( $type->defaults(), [ 'label' => 'Country', 'input_name' => 'country' ], $config ),
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
	 * It renders the real list, with the code as the value.
	 */
	public function test_it_renders_every_country(): void {
		$html = ( new Renderer() )->render( $this->field() );

		$this->assertStringContainsString( '<option value="GB">United Kingdom</option>', $html );
		$this->assertStringContainsString( '<option value="JP">Japan</option>', $html );
		$this->assertStringContainsString( '<option value="">Select a country</option>', $html );
	}

	/**
	 * The chosen country is selected on the way back.
	 */
	public function test_the_stored_code_is_selected(): void {
		$html = ( new Renderer() )->render( $this->field( [], 'GB' ) );

		$this->assertStringContainsString( '<option value="GB" selected>United Kingdom</option>', $html );
	}

	/**
	 * A valid code round-trips, whatever case it arrived in.
	 */
	public function test_a_valid_code_round_trips(): void {
		$this->assertSame( 'GB', $this->sanitize( 'GB' ) );
		$this->assertSame( 'GB', $this->sanitize( 'gb' ) );
		$this->assertSame( 'GB', $this->sanitize( ' gb ' ) );
	}

	/**
	 * Anything that is not a country code is stored as nothing.
	 */
	public function test_an_invalid_code_becomes_nothing(): void {
		$this->assertSame( '', $this->sanitize( 'ZZ' ) );
		$this->assertSame( '', $this->sanitize( 'United Kingdom' ) );
		$this->assertSame( '', $this->sanitize( '<script>' ) );
		$this->assertSame( '', $this->sanitize( [ 'GB' ] ) );
	}

	/**
	 * With `continents` on, the list is grouped, and a code still saves.
	 */
	public function test_continents_group_the_list(): void {
		$html = ( new Renderer() )->render( $this->field( [ 'continents' => true ] ) );

		$this->assertStringContainsString( '<optgroup label="Europe">', $html );
		$this->assertStringContainsString( '<optgroup label="Asia">', $html );
		$this->assertMatchesRegularExpression( '/<optgroup label="Europe">.*value="GB".*<\/optgroup>/s', $html );

		$this->assertSame( 'GB', $this->sanitize( 'GB', [ 'continents' => true ] ) );
	}

	/**
	 * Without `continents`, there are no groups.
	 */
	public function test_the_list_is_flat_by_default(): void {
		$this->assertStringNotContainsString( '<optgroup', ( new Renderer() )->render( $this->field() ) );
	}

	/**
	 * A custom option list narrows the choice, and still stores codes only.
	 *
	 * A store that ships to three countries lists three. A key in that list
	 * that is not a country code is refused, because the contract is the
	 * code and not the list.
	 */
	public function test_a_custom_list_still_stores_codes_only(): void {
		$config = [ 'options' => [ 'GB' => 'Britain', 'IE' => 'Ireland', 'UK' => 'Not a code' ] ];

		$this->assertStringContainsString( '>Britain<', ( new Renderer() )->render( $this->field( $config ) ) );
		$this->assertSame( 'GB', $this->sanitize( 'gb', $config ) );
		$this->assertSame( '', $this->sanitize( 'FR', $config ) );
		$this->assertSame( '', $this->sanitize( 'UK', $config ) );
	}

	/**
	 * Several countries at once, for a shipping-zone field.
	 */
	public function test_it_can_take_several(): void {
		$this->assertSame(
			[ 'GB', 'IE' ],
			$this->sanitize( [ 'gb', 'ZZ', 'IE' ], [ 'multiple' => true ] )
		);
	}

	/**
	 * `continents` is the type's own key, so setting it is not a mistake.
	 */
	public function test_continents_is_a_known_key(): void {
		$this->assertSame( [], $this->field( [ 'continents' => true ] )->unknown_keys() );
		$this->assertSame( 'country', ( new Registry() )->get( 'country' )->id() );
	}
}
