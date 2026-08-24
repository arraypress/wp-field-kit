<?php
/**
 * Licence field tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\FieldSet;
use PHPUnit\Framework\TestCase;

/**
 * A licence field's state is the thing people get wrong twice over: they
 * convey it with a coloured dot and nothing else, and they colour the button
 * for the state the licence is *in* rather than for what the button does — a
 * green "Deactivate" being the classic way to lose a licence by accident.
 */
final class LicenseTest extends TestCase {

	/**
	 * Render a licence field.
	 *
	 * @param array<string, mixed> $config Extra configuration.
	 *
	 * @return string
	 */
	private function render( array $config = [] ): string {
		return ( new FieldSet(
			[ 'lic' => array_merge( [ 'type' => 'license', 'label' => 'Licence key' ], $config ) ],
			new ArrayContext( [ 'lic' => 'AP-1234-5678-9012' ] ),
			''
		) )->render();
	}

	/**
	 * An inactive licence offers activation, plainly.
	 *
	 * Not green: the button is an ordinary action, and colouring it for the
	 * outcome rather than the act is what makes the pair confusing.
	 */
	public function test_an_inactive_licence_offers_a_plain_activate(): void {
		$html = $this->render( [ 'is_active' => false ] );

		$this->assertStringContainsString( '>Activate</button>', $html );
		$this->assertStringNotContainsString( 'field-kit__button--delete', $html );

		// The badge is there, saying so. It used to be omitted entirely,
		// which left an activation nothing to write into and the field stuck
		// reading "Not active" until the page was reloaded.
		$this->assertStringContainsString( 'field-kit__license-state--inactive', $html );
		$this->assertStringContainsString( '>Not active</span>', $html );
	}

	/**
	 * An active licence says so, and its button is the destructive one.
	 */
	public function test_an_active_licence_says_so_and_deactivating_is_destructive(): void {
		$html = $this->render( [ 'is_active' => true ] );

		$this->assertStringContainsString( '>Deactivate</button>', $html );
		$this->assertStringContainsString( 'field-kit__button--delete', $html );

		$this->assertStringContainsString( 'field-kit__license-state--active', $html );
		$this->assertStringContainsString( '>Active</span>', $html );
	}

	/**
	 * The state is a word, not only a colour.
	 *
	 * The icon is decorative and the text carries the meaning, so the state
	 * survives being read out, printed, or looked at by someone who cannot
	 * tell the green from the grey.
	 */
	public function test_the_state_is_conveyed_by_text(): void {
		$html = $this->render( [ 'is_active' => true ] );

		$this->assertMatchesRegularExpression(
			'/field-kit__license-state[^>]*>\s*<span class="dashicons[^>]*aria-hidden="true"[^>]*><\/span>'
			. '<span class="field-kit__license-state-text">Active/',
			$html
		);
	}

	/**
	 * Site usage is shown when it is known.
	 *
	 * A licence active here and exhausted everywhere else is a different
	 * situation from one with room to spare, and this field is where someone
	 * looks to find that out.
	 */
	public function test_site_usage_is_shown_when_known(): void {
		$this->assertStringContainsString(
			'1 of 3 sites',
			$this->render( [ 'is_active' => true, 'sites' => [ 1, 3 ] ] )
		);

		// Not invented when it is not known. The span is there — the script
		// needs somewhere to put a count it is given — but it is empty and
		// carries `hidden`, so nothing is shown and nothing is read out.
		$this->assertStringContainsString(
			'<span class="field-kit__license-sites" hidden></span>',
			$this->render( [ 'is_active' => true ] )
		);
	}

	/**
	 * An active key is masked, and stays readable.
	 *
	 * Readonly rather than disabled: a disabled input cannot be focused, so
	 * its value could not be read by keyboard at all.
	 */
	public function test_an_active_key_is_masked_but_focusable(): void {
		$html = $this->render( [ 'is_active' => true ] );

		$this->assertStringContainsString( 'readonly', $html );
		$this->assertStringNotContainsString( 'disabled', $html );
		$this->assertStringNotContainsString( 'value="AP-1234-5678-9012"', $html );
		$this->assertStringContainsString( '9012', $html, 'The last four are kept, so the key is recognisable.' );
	}
}
