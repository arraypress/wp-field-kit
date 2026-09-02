<?php
/**
 * The region field and its source.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Renderer;
use ArrayPress\FieldKit\Search\RegionSource;
use ArrayPress\FieldKit\Search\Sources;
use PHPUnit\Framework\TestCase;

/**
 * A state or province, narrowed to a country.
 *
 * The control depends on what is known about the country: a select where
 * it is fixed and has a list, a text input where it is fixed and has none,
 * and both -- one live, one waiting for the script -- where the country is
 * another field's business. What is pinned down here is which of those
 * each configuration gets, that only one of the pair can ever submit, and
 * that the source behind the swap answers with the whole list.
 */
final class RegionTest extends TestCase {

	/**
	 * Build a field the way FieldSet does, defaults merged in.
	 *
	 * @param array<string, mixed> $config Field configuration.
	 * @param mixed                $value  Its value.
	 *
	 * @return Field
	 */
	private function field( array $config = [], mixed $value = null ): Field {
		$type = ( new Registry() )->get( 'region' );

		return new Field(
			'region',
			$type,
			array_merge( $type->defaults(), [ 'label' => 'Region', 'input_name' => 'region' ], $config ),
			$value
		);
	}

	/**
	 * Render a region field.
	 *
	 * @param array<string, mixed> $config Field configuration.
	 * @param mixed                $value  Its value.
	 *
	 * @return string
	 */
	private function render( array $config = [], mixed $value = null ): string {
		return ( new Renderer() )->render( $this->field( $config, $value ) );
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
	 * A fixed country with a list is a select over it, code as the value.
	 */
	public function test_a_fixed_country_with_regions_is_a_select(): void {
		$html = $this->render( [ 'country' => 'US' ], 'NY' );

		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'field-kit__region-select', $html );
		$this->assertStringContainsString( '<option value="">Select a region</option>', $html );
		$this->assertStringContainsString( '<option value="CA">California</option>', $html );
		$this->assertStringContainsString( '<option value="NY" selected>New York</option>', $html );
		$this->assertStringNotContainsString( '<input', $html );

		// The select is the control, so the label points at it.
		$this->assertStringContainsString( '<label for="region">', $html );
		$this->assertStringContainsString( 'id="region"', $html );
	}

	/**
	 * The list is the allow-list, whatever case the code arrived in.
	 */
	public function test_a_fixed_country_enforces_membership(): void {
		$config = [ 'country' => 'US' ];

		$this->assertSame( 'CA', $this->sanitize( 'CA', $config ) );
		$this->assertSame( 'CA', $this->sanitize( 'ca', $config ) );
		$this->assertSame( 'CA', $this->sanitize( ' ca ', $config ) );
		$this->assertSame( '', $this->sanitize( 'ZZ', $config ) );
		$this->assertSame( '', $this->sanitize( 'California', $config ) );
		$this->assertSame( '', $this->sanitize( [ 'CA' ], $config ) );

		// Lower case in the config is the same country.
		$this->assertSame( 'CA', $this->sanitize( 'ca', [ 'country' => 'us' ] ) );
	}

	/**
	 * A fixed country with no list is a text input, and takes what is typed.
	 */
	public function test_a_fixed_country_without_regions_is_a_text_input(): void {
		$html = $this->render( [ 'country' => 'GB' ], 'Yorkshire' );

		$this->assertStringContainsString( '<input', $html );
		$this->assertStringContainsString( 'type="text"', $html );
		$this->assertStringContainsString( 'value="Yorkshire"', $html );
		$this->assertStringContainsString( 'field-kit__region-text', $html );
		$this->assertStringContainsString( 'id="region"', $html );
		$this->assertStringNotContainsString( '<select', $html );

		$this->assertSame( 'Yorkshire', $this->sanitize( 'Yorkshire', [ 'country' => 'GB' ] ) );
	}

	/**
	 * A country nobody has heard of is the same as none.
	 */
	public function test_an_unknown_fixed_country_is_a_text_input(): void {
		$html = $this->render( [ 'country' => 'ZZ' ], 'Anywhere' );

		$this->assertStringContainsString( 'type="text"', $html );
		$this->assertStringNotContainsString( '<select', $html );
		$this->assertSame( 'Anywhere', $this->sanitize( 'Anywhere', [ 'country' => 'ZZ' ] ) );
	}

	/**
	 * Following a sibling draws both controls, wired for the script.
	 */
	public function test_a_sibling_country_renders_both_controls(): void {
		$html = $this->render( [ 'country_key' => 'country' ], 'CA' );

		$this->assertMatchesRegularExpression(
			'/<span class="field-kit__region" data-country-key="country" data-search-endpoint="[^"]+\/search" data-search-nonce="[^"]+">/',
			$html
		);

		// The text input is live and carries the stored value.
		$this->assertMatchesRegularExpression( '/<input[^>]*field-kit__region-text[^>]*value="CA"/', $html );
		$this->assertDoesNotMatchRegularExpression( '/<input[^>]*field-kit__region-text[^>]*\sdisabled/', $html );

		// The select is empty and inert until the script fills it.
		$this->assertMatchesRegularExpression( '/<select[^>]*field-kit__region-select[^>]*\sdisabled[^>]*\shidden[^>]*><\/select>/', $html );
		$this->assertStringContainsString( 'data-placeholder="Select a region"', $html );
	}

	/**
	 * Both share the name; the select owns the id and the text input names itself.
	 *
	 * The label the renderer writes points at the field's id, which the
	 * select takes; the text input has an id of its own and an aria-label,
	 * so it is named while it is the live control and no script has to
	 * move anything.
	 */
	public function test_the_pair_shares_a_name_and_is_labelled_either_way(): void {
		$html = $this->render( [ 'country_key' => 'country', 'description' => 'Where.' ], 'CA' );

		$this->assertSame( 2, substr_count( $html, 'name="region"' ) );

		$this->assertMatchesRegularExpression( '/<select[^>]*\sid="region"/', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*\sid="region_text"/', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*aria-label="Region"/', $html );
		$this->assertStringContainsString( '<label for="region">', $html );

		// The description reaches whichever is live.
		$this->assertSame( 2, substr_count( $html, 'aria-describedby="region__description"' ) );
	}

	/**
	 * A locked field has nothing to swap, so it is drawn once, disabled.
	 */
	public function test_a_disabled_field_does_not_offer_a_swap(): void {
		$html = $this->render( [ 'country_key' => 'country', 'disabled' => true ], 'CA' );

		$this->assertStringNotContainsString( 'data-country-key', $html );
		$this->assertStringNotContainsString( '<select', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*\sdisabled/', $html );
	}

	/**
	 * A fixed country wins over a sibling: there is nothing to follow.
	 */
	public function test_a_fixed_country_wins_over_a_sibling(): void {
		$html = $this->render( [ 'country' => 'US', 'country_key' => 'country' ], 'NY' );

		$this->assertStringNotContainsString( 'data-country-key', $html );
		$this->assertStringContainsString( '<option value="NY" selected>New York</option>', $html );
	}

	/**
	 * With no country to check against, the value is bounded text.
	 */
	public function test_free_text_is_trimmed_and_limited(): void {
		$config = [ 'country_key' => 'country' ];

		$this->assertSame( 'Yorkshire', $this->sanitize( '  Yorkshire  ', $config ) );
		$this->assertSame( 'CA', $this->sanitize( 'CA', $config ) );
		$this->assertSame( str_repeat( 'a', 32 ), $this->sanitize( str_repeat( 'a', 40 ), $config ) );
		$this->assertSame( 'x', $this->sanitize( '<b>x</b>', $config ) );
		$this->assertSame( '', $this->sanitize( [ 'CA' ], $config ) );
		$this->assertSame( '', $this->sanitize( null, $config ) );

		// And the same with nothing configured at all.
		$this->assertSame( 'Yorkshire', $this->sanitize( 'Yorkshire' ) );
	}

	/**
	 * The keys are the type's own, so setting them is not a mistake.
	 */
	public function test_the_keys_are_known(): void {
		$this->assertSame( [], $this->field( [ 'country' => 'US', 'country_key' => 'country' ] )->unknown_keys() );
		$this->assertSame( 'region', ( new Registry() )->get( 'region' )->id() );
	}

	/**
	 * The source answers with every region of the country, limit or no limit.
	 *
	 * The United States has more subdivisions on file than the endpoint's
	 * largest page, and a select needs all of them.
	 */
	public function test_the_source_returns_every_region(): void {
		$found = ( new RegionSource() )->search( '', [ 'country' => 'US' ], 1, 10 );

		$this->assertFalse( $found['more'] );
		$this->assertGreaterThan( 50, count( $found['results'] ) );
		$this->assertContains( [ 'id' => 'CA', 'text' => 'California' ], $found['results'] );

		// Case is not the request's problem.
		$this->assertSame( $found, ( new RegionSource() )->search( '', [ 'country' => 'us' ], 1, 10 ) );
	}

	/**
	 * A term narrows the list by name or by code, in either case.
	 */
	public function test_the_source_filters_by_term(): void {
		$source = new RegionSource();

		$by_name = array_column( $source->search( 'calif', [ 'country' => 'US' ], 1, 50 )['results'], 'id' );
		$this->assertSame( [ 'CA' ], $by_name );

		// "dc" is in no state's name; it is the code alone that matches.
		$by_code = array_column( $source->search( 'dc', [ 'country' => 'US' ], 1, 50 )['results'], 'id' );
		$this->assertSame( [ 'DC' ], $by_code );

		$this->assertSame( [], $source->search( 'zzzz', [ 'country' => 'US' ], 1, 50 )['results'] );
	}

	/**
	 * An unknown country, or none, is an empty list rather than an error.
	 */
	public function test_the_source_honours_an_unknown_country(): void {
		$source = new RegionSource();
		$empty  = [
			'results' => [],
			'more'    => false,
		];

		$this->assertSame( $empty, $source->search( '', [ 'country' => 'ZZ' ], 1, 50 ) );
		$this->assertSame( $empty, $source->search( '', [ 'country' => 'GB' ], 1, 50 ) );
		$this->assertSame( $empty, $source->search( '', [], 1, 50 ) );
		$this->assertSame( $empty, $source->search( '', [ 'country' => [ 'US' ] ], 1, 50 ) );
	}

	/**
	 * Public data needs no more than `read`, and the endpoint can find it.
	 */
	public function test_the_source_is_public_and_registered(): void {
		$this->assertSame( 'read', ( new RegionSource() )->capability() );
		$this->assertSame( 'region', ( new RegionSource() )->name() );

		$this->assertTrue( Sources::shared()->has( 'region' ) );
		$this->assertInstanceOf( RegionSource::class, Sources::shared()->get( 'region' ) );
	}
}
