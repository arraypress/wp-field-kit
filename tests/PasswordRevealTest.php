<?php
/**
 * The password reveal button.
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
 * A key pasted into a box of dots cannot be checked against the one it was
 * copied from. The button that shows it is core's own from the profile
 * screen, so it is the one people have already met -- and showing what has
 * been typed must never become a way of reading back what was saved.
 */
final class PasswordRevealTest extends TestCase {

	public function test_the_toggle_is_cores_own(): void {
		$html = $this->render( [] );

		$this->assertStringContainsString( '<span class="field-kit__password"><input', $html );
		$this->assertStringContainsString(
			'<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js field-kit__password-toggle" aria-label="Show password" aria-pressed="false" data-toggle="0"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button></span>',
			$html
		);
	}

	public function test_it_is_on_unless_turned_off(): void {
		$html = $this->render( [ 'reveal' => false ] );

		$this->assertStringNotContainsString( 'field-kit__password', $html );
		$this->assertStringNotContainsString( '<button', $html );
		$this->assertStringContainsString( 'type="password"', $html );
	}

	public function test_the_stored_value_is_still_never_sent_to_the_page(): void {
		$html = $this->render( [], 'hunter2' );

		$this->assertStringContainsString( 'value=""', $html );
		$this->assertStringNotContainsString( 'hunter2', $html );
	}

	/**
	 * An affix goes round the input, and the reveal round both.
	 */
	public function test_an_affix_sits_inside_the_reveal(): void {
		$this->assertMatchesRegularExpression(
			'/<span class="field-kit__password"><span class="field-kit__adorned[^"]*">.*<\/span><button/s',
			$this->render( [ 'prefix' => 'sk_' ] )
		);
	}

	/**
	 * The label the script swaps to is translatable, and the script reads
	 * the translation.
	 */
	public function test_both_labels_are_localised(): void {
		$js     = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/field-kit.js' );
		$assets = (string) file_get_contents( dirname( __DIR__ ) . '/src/Assets.php' );

		foreach ( [ 'showPassword', 'hidePassword' ] as $key ) {
			$this->assertStringContainsString( "t( '" . $key . "'", $js, "The script does not read $key." );
			$this->assertStringContainsString( "'" . $key . "'", $assets, "$key is not localised." );
		}
	}

	/**
	 * Render a password field.
	 *
	 * @param array<string, mixed> $config Field configuration.
	 * @param string               $value  Its stored value.
	 *
	 * @return string
	 */
	private function render( array $config, string $value = '' ): string {
		$field = new Field(
			'secret',
			( new Registry() )->get( 'password' ),
			array_merge( [ 'label' => 'Secret', 'input_name' => 'secret' ], $config ),
			null
		);

		return ( new Renderer() )->render( $field->with_value( $value ) );
	}
}
