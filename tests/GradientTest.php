<?php
/**
 * Gradient tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\FieldSet;
use PHPUnit\Framework\TestCase;

/**
 * A gradient, chosen from the ones the site already has.
 *
 * The value lands in a style attribute, which is why most of what is asserted
 * here is that only a gradient the field offered can be stored: anything else
 * is a CSS injection with a form around it.
 */
final class GradientTest extends TestCase {

	/**
	 * Two gradients to choose from.
	 *
	 * @var array<string, string>
	 */
	private const OFFERED = [
		'linear-gradient(135deg,#06f 0%,#9b51e0 100%)' => 'Blue to purple',
		'linear-gradient(135deg,#7adcb4 0%,#00d082 100%)' => 'Green',
	];

	/**
	 * Render one field.
	 *
	 * @param array<string, mixed> $config Extra configuration.
	 * @param mixed                $stored What is stored.
	 *
	 * @return string
	 */
	private function render( array $config = [], mixed $stored = null ): string {
		$set = $this->set( $config, $stored );

		return $set->render_field( $set->field( 'shade' ) );
	}

	/**
	 * A set holding one gradient field.
	 *
	 * @param array<string, mixed> $config Extra configuration.
	 * @param mixed                $stored What is stored.
	 *
	 * @return FieldSet
	 */
	private function set( array $config = [], mixed $stored = null ): FieldSet {
		return new FieldSet(
			[
				'shade' => array_merge(
					[
						'type'    => 'gradient',
						'label'   => 'Shade',
						'options' => self::OFFERED,
					],
					$config
				),
			],
			new ArrayContext( null === $stored ? [] : [ 'shade' => $stored ] ),
			''
		);
	}

	/**
	 * Each gradient is painted with itself.
	 */
	public function test_each_swatch_shows_its_own_gradient(): void {
		$html = $this->render();

		foreach ( array_keys( self::OFFERED ) as $gradient ) {
			$this->assertStringContainsString( 'background:' . esc_attr( $gradient ), $html );
		}

		$this->assertStringContainsString( 'Blue to purple', $html );
	}

	/**
	 * It is a radio group underneath, so it works from a keyboard.
	 *
	 * The inputs are off-screen rather than display:none — which would take
	 * them out of the tab order and stop the arrow keys moving between them.
	 */
	public function test_it_is_a_radio_group(): void {
		$html = $this->render();

		$this->assertSame( 3, substr_count( $html, 'type="radio"' ) );
		$this->assertStringContainsString( 'name="shade"', $html );
		$this->assertStringNotContainsString( 'display: none', $html );
	}

	/**
	 * A field that need not be filled in has a way back to nothing.
	 */
	public function test_there_is_a_none_option(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'field-kit__gradient--none', $html );
		$this->assertStringContainsString( 'value=""', $html );
	}

	/**
	 * A required one does not.
	 */
	public function test_a_required_field_has_no_none_option(): void {
		$html = $this->render( [ 'required' => true ] );

		$this->assertStringNotContainsString( 'field-kit__gradient--none', $html );
	}

	/**
	 * The stored gradient is the checked one.
	 */
	public function test_the_stored_gradient_is_checked(): void {
		$gradient = array_key_first( self::OFFERED );
		$html     = $this->render( [], $gradient );

		$this->assertMatchesRegularExpression(
			'/value="' . preg_quote( esc_attr( $gradient ), '/' ) . '"[^>]*checked/',
			$html
		);
	}

	/**
	 * A gradient the theme no longer offers is still selectable.
	 *
	 * Otherwise opening the screen and saving it changes the value to
	 * whichever preset happens to be first, without anyone touching it.
	 */
	public function test_a_gradient_no_longer_offered_survives(): void {
		$html = $this->render( [], 'linear-gradient(90deg,#000,#fff)' );

		$this->assertStringContainsString( 'linear-gradient(90deg,#000,#fff)', $html );
		$this->assertSame( 4, substr_count( $html, 'type="radio"' ) );
	}

	/**
	 * Only a gradient that was offered can be stored.
	 *
	 * The value goes into a style attribute, so anything else is a CSS
	 * injection with a form around it.
	 */
	public function test_a_value_that_was_not_offered_is_refused(): void {
		$context = new ArrayContext();

		$set = new FieldSet(
			[ 'shade' => [ 'type' => 'gradient', 'options' => self::OFFERED ] ],
			$context,
			''
		);

		$set->save( [ 'shade' => 'red; } body { display: none } .x {' ] );

		$this->assertArrayNotHasKey( 'shade', $context->values() );
	}

	/**
	 * And one that was is stored as it stands.
	 */
	public function test_an_offered_gradient_is_stored(): void {
		$context  = new ArrayContext();
		$gradient = array_key_first( self::OFFERED );

		$set = new FieldSet(
			[ 'shade' => [ 'type' => 'gradient', 'options' => self::OFFERED ] ],
			$context,
			''
		);

		$set->save( [ 'shade' => $gradient ] );

		$this->assertSame( $gradient, $context->values()['shade'] );
	}

	/**
	 * The value already stored counts as offered.
	 *
	 * A theme switch should not blank a field nobody touched.
	 */
	public function test_the_stored_value_counts_as_offered(): void {
		$context = new ArrayContext( [ 'shade' => 'linear-gradient(90deg,#000,#fff)' ] );

		$set = new FieldSet(
			[ 'shade' => [ 'type' => 'gradient', 'options' => self::OFFERED ] ],
			$context,
			''
		);

		$set->save( [ 'shade' => 'linear-gradient(90deg,#000,#fff)' ] );

		$this->assertSame( 'linear-gradient(90deg,#000,#fff)', $context->values()['shade'] );
	}

	/**
	 * Nothing chosen stores nothing.
	 */
	public function test_nothing_chosen_stores_nothing(): void {
		$context = new ArrayContext();

		$set = new FieldSet(
			[ 'shade' => [ 'type' => 'gradient', 'options' => self::OFFERED ] ],
			$context,
			''
		);

		$set->save( [ 'shade' => '' ] );

		$this->assertArrayNotHasKey( 'shade', $context->values() );
	}

	/**
	 * With no options of its own it asks the site.
	 *
	 * Core defines a dozen gradient presets and a theme adds its own; a
	 * plugin offering a palette beside the theme's is a plugin that looks
	 * like a plugin.
	 */
	public function test_it_falls_back_to_the_sites_own_presets(): void {
		$GLOBALS['fk_global_settings'] = [
			[
				[ 'slug' => 'sunrise', 'name' => 'Sunrise', 'gradient' => 'linear-gradient(135deg,#f00,#ff0)' ],
			],
		];

		$set = new FieldSet( [ 'shade' => [ 'type' => 'gradient' ] ], new ArrayContext(), '' );

		$html = $set->render_field( $set->field( 'shade' ) );

		unset( $GLOBALS['fk_global_settings'] );

		$this->assertStringContainsString( 'Sunrise', $html );
	}

	/**
	 * A stored gradient the theme no longer offers is painted.
	 */
	public function test_a_stored_gradient_no_longer_offered_is_painted(): void {
		$html = $this->render( [], 'linear-gradient(90deg,#000 0%,#fff 100%)' );

		$this->assertStringContainsString( 'style="background:linear-gradient(90deg,#000 0%,#fff 100%)"', $html );
	}

	/**
	 * A stored value that is not a gradient is listed but not painted.
	 *
	 * A swatch's style attribute is the one place a stored value reaches
	 * CSS, and a semicolon there is a second declaration.
	 */
	public function test_a_stored_value_that_is_not_a_gradient_is_not_painted(): void {
		$html = $this->render( [], 'red;position:fixed;inset:0;background:url(https://evil.example/b)' );

		$this->assertStringContainsString( 'Current', $html );
		$this->assertStringNotContainsString( 'style="background:red', $html );
		$this->assertMatchesRegularExpression( '/checked \/><span class="field-kit__gradient-swatch" aria-hidden/', $html );
	}
}
