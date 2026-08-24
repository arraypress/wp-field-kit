<?php
/**
 * Licence layout tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\FieldSet;
use PHPUnit\Framework\TestCase;

/**
 * A licence field is three rows, not one line.
 *
 * The key and its button, then the state, then whatever the last action said.
 * All of it on a single flex row put a sentence of explanation out past the
 * right-hand edge of everything else on the screen, with the state badge
 * stranded between the button and the sentence and belonging to neither.
 */
final class LicenseLayoutTest extends TestCase {

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
						'type'           => 'license',
						'label'          => 'Licence',
						'sites'          => [ 1, 3 ],
						'status_message' => 'Enter a key and activate.',
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
	 * The three rows are there, in order.
	 */
	public function test_it_is_three_rows(): void {
		$html = $this->render();

		$control = strpos( $html, 'field-kit__license-control' );
		$meta    = strpos( $html, 'field-kit__license-meta' );
		$status  = strpos( $html, 'field-kit__license-status' );

		$this->assertNotFalse( $control, 'There is no control row.' );
		$this->assertNotFalse( $meta, 'There is no state row.' );
		$this->assertNotFalse( $status, 'There is no status row.' );

		$this->assertLessThan( $meta, $control );
		$this->assertLessThan( $status, $meta );
	}

	/**
	 * The key and its button share the first row; nothing else does.
	 */
	public function test_the_control_row_holds_the_key_and_the_button(): void {
		$html = $this->render();

		preg_match( '{<div class="field-kit__license-control">(.*?)</div>}s', $html, $row );

		$this->assertNotEmpty( $row, 'The control row is not a closed element.' );
		$this->assertStringContainsString( 'field-kit__license-key', $row[1] );
		$this->assertStringContainsString( '<button', $row[1] );
		$this->assertStringNotContainsString( 'license-state', $row[1] );
	}

	/**
	 * The badge and the seat count travel together.
	 */
	public function test_the_state_and_the_seat_count_share_a_row(): void {
		preg_match( '{<div class="field-kit__license-meta">(.*?)</div>\s*<p}s', $this->render(), $row );

		$this->assertNotEmpty( $row );
		$this->assertStringContainsString( 'license-state', $row[1] );
		$this->assertStringContainsString( '1 of 3 sites', $row[1] );
	}

	/**
	 * The rows stack rather than sitting on one line.
	 */
	public function test_the_stylesheet_stacks_them(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		preg_match( '/\.field-kit__license\s*\{([^}]*)\}/', $css, $rule );

		$this->assertNotEmpty( $rule, 'The licence has no rule of its own.' );
		$this->assertStringContainsString( 'flex-direction: column', $rule[1] );
	}

	/**
	 * The status is announced when it changes.
	 *
	 * It is the only place an activation says what happened, and a message
	 * that appears silently is a message a screen-reader user never gets.
	 */
	public function test_the_status_is_a_live_region(): void {
		$this->assertMatchesRegularExpression(
			'/field-kit__license-status[^>]*aria-live="polite"/',
			$this->render()
		);
	}

	/**
	 * Deactivating is drawn as the destructive action; activating is not.
	 *
	 * A button coloured for the state it is *in* rather than the action it
	 * performs is the classic way to get a licence deactivated by accident.
	 */
	public function test_only_deactivate_is_destructive(): void {
		$this->assertStringNotContainsString( 'field-kit__button--delete', $this->render() );
		$this->assertStringContainsString(
			'field-kit__button--delete',
			$this->render( [ 'is_active' => true ] )
		);
	}
}
