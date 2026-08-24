<?php
/**
 * Licence live-state tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\FieldSet;
use PHPUnit\Framework\TestCase;

/**
 * Activating a licence changes the field, not just the sentence under it.
 *
 * What shipped: the handler's answer went into the status line and nowhere
 * else, so a successful activation left the badge reading "Not active" and the
 * seat count at nought while the message beside it said the licence was active
 * on one of three sites. Two contradictory answers on the same row, and the
 * only way to find out which was true was to reload.
 *
 * The fix is that the field carries enough to be rewritten in place, and this
 * is the PHP half of it — the script reads a set of data attributes, and the
 * field has to write every one of them. That contract spans two languages and
 * nothing else checks it: renaming an attribute on either side leaves both
 * halves valid, both suites green, and the field permanently stuck in the
 * state it loaded in. So the last test here reads the script.
 */
final class LicenseStateTest extends TestCase {

	/**
	 * Render one licence field.
	 *
	 * @param array<string, mixed> $config Extra configuration.
	 *
	 * @return string
	 */
	private function render( array $config = [] ): string {
		$set = new FieldSet(
			[
				'licence' => array_merge(
					[
						'type'         => 'license',
						'label'        => 'Licence',
						'action_names' => [
							'activate'   => 'demo_activate',
							'deactivate' => 'demo_deactivate',
						],
					],
					$config
				),
			],
			new ArrayContext( [ 'licence' => 'AP-1234-5678-9012' ] ),
			''
		);

		return $set->render_field( $set->field( 'licence' ) );
	}

	/**
	 * The button knows both actions, so it can become the other one.
	 *
	 * Without this the button that just activated a licence still posts to
	 * the activate handler, and the second press activates it again.
	 */
	public function test_the_button_carries_both_action_names(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'data-action="demo_activate"', $html );
		$this->assertStringContainsString( 'data-action-activate="demo_activate"', $html );
		$this->assertStringContainsString( 'data-action-deactivate="demo_deactivate"', $html );
	}

	/**
	 * And both labels, because the script must not invent English.
	 */
	public function test_the_button_carries_both_labels(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'data-label-activate="Activate"', $html );
		$this->assertStringContainsString( 'data-label-deactivate="Deactivate"', $html );
	}

	/**
	 * The wrapper carries both badge labels, for the same reason.
	 */
	public function test_the_wrapper_carries_both_state_labels(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'data-label-active="Active"', $html );
		$this->assertStringContainsString( 'data-label-inactive="Not active"', $html );
	}

	/**
	 * The seat count is a template, not just a rendered sentence.
	 *
	 * The script rewrites the numbers and has no business knowing how the
	 * sentence is worded — or what language it is in. Both placeholders are
	 * asserted because a template that lost one silently prints a literal.
	 */
	public function test_the_seat_count_is_a_template(): void {
		$html = $this->render( [ 'sites' => [ 1, 3 ] ] );

		$this->assertMatchesRegularExpression(
			'/data-sites-template="[^"]*%1\$s[^"]*%2\$s[^"]*"/',
			$html
		);
	}

	/**
	 * The state row exists even with nothing to say yet.
	 *
	 * It used to be omitted entirely when a licence was inactive and no seat
	 * count was configured, which is the exact case an activation starts from
	 * — leaving the script nothing to write into. The seat span is present
	 * and hidden rather than absent.
	 */
	public function test_the_state_row_is_rendered_when_there_is_nothing_to_show(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'field-kit__license-meta', $html );
		$this->assertStringContainsString( 'field-kit__license-state-text', $html );
		$this->assertMatchesRegularExpression(
			'/class="field-kit__license-sites"\s+hidden/',
			$html
		);
	}

	/**
	 * A seat count that is known is shown rather than hidden.
	 */
	public function test_a_known_seat_count_is_visible(): void {
		$html = $this->render( [ 'sites' => [ 1, 3 ] ] );

		$this->assertStringContainsString( '<span class="field-kit__license-sites">1 of 3 sites</span>', $html );
	}

	/**
	 * Every attribute the script reads is one the field writes.
	 *
	 * The cross-language half of the contract. `licence.dataset.labelActive`
	 * in the script means `data-label-active` in the markup, and nothing
	 * connects the two but this — rename either and both suites stay green
	 * while the field stops responding.
	 */
	public function test_the_script_reads_only_attributes_the_field_writes(): void {
		$script = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/field-kit.js' );

		// applyState is the only function that reads them, so only its body
		// is scanned — elsewhere in the script `dataset.action` belongs to
		// other fields entirely.
		preg_match( '/applyState: function \( wrap, state \) \{(.*?)\n\t\t\}/s', $script, $body );

		$this->assertNotEmpty( $body, 'applyState is gone from the script.' );

		preg_match_all( '/dataset\.(\w+)/', $body[1], $reads );

		$attributes = array_unique(
			array_map(
				static fn( string $one ): string => strtolower( (string) preg_replace( '/([A-Z])/', '-$1', $one ) ),
				$reads[1]
			)
		);

		$this->assertNotEmpty( $attributes, 'applyState reads no attributes at all.' );

		$html = $this->render( [ 'sites' => [ 1, 3 ] ] );

		foreach ( $attributes as $attribute ) {
			$this->assertStringContainsString(
				sprintf( 'data-%s="', $attribute ),
				$html,
				sprintf( 'The script reads data-%s and the field never writes it.', $attribute )
			);
		}
	}
}
