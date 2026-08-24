<?php
/**
 * Oembed tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\FieldSet;
use PHPUnit\Framework\TestCase;

/**
 * A URL that resolves to an embed.
 *
 * The live preview is the script's business, but it needs three things in the
 * markup to find and fill: the wrapper, the input and the region. This pins
 * those, since a rename on the PHP side is silent — the field still renders,
 * the preview simply never happens.
 */
final class OembedTest extends TestCase {

	/**
	 * Render one field.
	 *
	 * @param string $value The stored URL.
	 *
	 * @return string
	 */
	private function render( string $value = '' ): string {
		$set = new FieldSet(
			[ 'video' => [ 'type' => 'oembed', 'label' => 'Video' ] ],
			new ArrayContext( '' === $value ? [] : [ 'video' => $value ] ),
			''
		);

		return $set->render_field( $set->field( 'video' ) );
	}

	/**
	 * The three hooks the script needs.
	 */
	public function test_the_script_can_find_its_parts(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'field-kit__oembed"', $html );
		$this->assertStringContainsString( 'field-kit__oembed-input', $html );
		$this->assertStringContainsString( 'field-kit__oembed-preview', $html );
	}

	/**
	 * The preview region announces itself.
	 *
	 * An embed appearing is a change only sighted users would otherwise
	 * notice.
	 */
	public function test_the_preview_region_is_announced(): void {
		$this->assertStringContainsString( 'aria-live="polite"', $this->render() );
	}

	/**
	 * The control is a URL input, so a phone offers the right keyboard and
	 * the browser does its own validation.
	 */
	public function test_it_is_a_url_input(): void {
		$this->assertStringContainsString( 'type="url"', $this->render() );
	}

	/**
	 * Nothing stored is an empty preview rather than a failure message.
	 *
	 * A field nobody has filled in has not failed at anything.
	 */
	public function test_an_empty_field_says_nothing(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'field-kit__oembed-preview" aria-live="polite"></div>', $html );
	}

	/**
	 * The endpoint handed to the script is core's, not one of ours.
	 *
	 * Core's proxy fetches only from WordPress's provider allowlist and is
	 * gated on `edit_posts`. An endpoint of our own taking a URL and fetching
	 * it would be a request forgery with a nonce on it.
	 */
	public function test_the_script_is_given_cores_own_endpoint(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Assets.php' );

		$this->assertStringContainsString( "rest_url( 'oembed/1.0/proxy' )", $source );

		$script = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/field-kit.js' );

		// And the script uses that rather than building a URL of its own.
		$this->assertStringContainsString( 'config.oembedUrl', $script );
		$this->assertStringNotContainsString( "'/oembed/", $script );
	}

	/**
	 * The URL is sent as a query parameter, encoded.
	 *
	 * A URL with an ampersand in it — which is most YouTube links — would
	 * otherwise arrive truncated at the first one.
	 */
	public function test_the_url_is_encoded(): void {
		$script = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/field-kit.js' );

		$this->assertStringContainsString( 'encodeURIComponent( url )', $script );
	}

	/**
	 * A slow lookup cannot overwrite a later one.
	 *
	 * Typing a URL fires an input event per character. Without a sequence
	 * number the lookup for half a URL can land after the one for the whole
	 * of it and replace the right answer with the wrong one.
	 */
	public function test_the_last_request_wins(): void {
		$script = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/field-kit.js' );

		$this->assertStringContainsString( 'ticket !== current()', $script );
	}
}
