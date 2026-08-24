<?php
/**
 * Tooltip tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\FieldSet;
use ArrayPress\FieldKit\Support\Tooltip;
use PHPUnit\Framework\TestCase;

/**
 * EDD's tooltip is the shape being matched and deliberately not the
 * implementation: there the content goes into a `title` attribute and jQuery
 * UI picks it up, which means the raw markup flashes as a native tooltip
 * before the script runs, and the explanation is unreachable from a keyboard.
 *
 * So most of what is asserted here is that the panel is real markup, hidden
 * with the attribute rather than by a class, associated with a button that is
 * in the tab order. All of that has to be true before any script runs.
 */
final class TooltipTest extends TestCase {

	/**
	 * Render a field's tooltip through a set.
	 *
	 * @param mixed $tooltip Tooltip configuration.
	 *
	 * @return string
	 */
	private function render( mixed $tooltip ): string {
		return ( new FieldSet(
			[ 'key' => [ 'type' => 'text', 'label' => 'API key', 'tooltip' => $tooltip ] ],
			new ArrayContext( [] ),
			''
		) )->render();
	}

	/**
	 * Nothing to say, nothing rendered.
	 */
	public function test_an_empty_tooltip_renders_nothing(): void {
		foreach ( [ '', '   ', null, [], [ 'content' => '' ], false ] as $empty ) {
			$this->assertSame( '', Tooltip::render( $empty, 'x' ), 'An empty tooltip rendered something.' );
		}
	}

	/**
	 * A string is the content.
	 */
	public function test_a_string_is_the_content(): void {
		$html = $this->render( 'Found in your account settings.' );

		$this->assertStringContainsString( 'Found in your account settings.', $html );
		$this->assertStringContainsString( 'field-kit__tooltip-toggle', $html );
	}

	/**
	 * The panel is real markup, hidden with the attribute.
	 *
	 * Not display:none in a stylesheet and not built on demand: a panel that
	 * exists only once the script has run does not exist for anyone the
	 * script failed for, and one hidden only by CSS is read out anyway.
	 */
	public function test_the_panel_is_present_and_properly_hidden(): void {
		$html = $this->render( 'Some help.' );

		$this->assertMatchesRegularExpression(
			'/<span class="field-kit__tooltip-panel"[^>]*\shidden>/',
			$html
		);
	}

	/**
	 * The button is in the tab order and names the panel.
	 *
	 * A `title` attribute — which is what this replaces — is reachable by
	 * hovering and by nothing else.
	 */
	public function test_the_button_is_focusable_and_describes_the_panel(): void {
		$html = $this->render( 'Some help.' );

		$this->assertStringContainsString( '<button type="button"', $html );
		$this->assertStringContainsString( 'aria-describedby="key-tip"', $html );
		$this->assertStringContainsString( 'id="key-tip"', $html );
		$this->assertStringContainsString( 'role="tooltip"', $html );
		$this->assertStringContainsString( 'aria-expanded="false"', $html );

		// The icon is decorative; the button has a name of its own.
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( 'screen-reader-text', $html );
	}

	/**
	 * The button sits outside the label.
	 *
	 * A button inside a <label> takes the label's click, so pressing it would
	 * focus the field instead of opening the panel.
	 */
	public function test_the_button_is_not_inside_the_label(): void {
		$html = $this->render( 'Some help.' );

		preg_match( '/<label\b.*?<\/label>/s', $html, $label );

		$this->assertNotEmpty( $label );
		$this->assertStringNotContainsString( 'field-kit__tooltip', $label[0] );
	}

	/**
	 * A title is rendered above the content.
	 */
	public function test_a_title_is_rendered_above_the_content(): void {
		$html = $this->render( [ 'title' => 'Where to find it', 'content' => 'In your account.' ] );

		$this->assertMatchesRegularExpression(
			'/field-kit__tooltip-title">Where to find it<\/strong>In your account\./',
			$html
		);
	}

	/**
	 * Emphasis survives; anything else does not.
	 *
	 * The content is configuration rather than a request, but a plugin that
	 * builds it from a remote response must not be able to put a script on an
	 * admin screen through it.
	 */
	public function test_only_the_allowed_markup_survives(): void {
		$html = $this->render( 'Use <strong>this</strong> <em>one</em>. <script>alert(1)</script><iframe></iframe>' );

		$this->assertStringContainsString( '<strong>this</strong>', $html );
		$this->assertStringContainsString( '<em>one</em>', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<iframe', $html );
	}

	/**
	 * A grouped field puts it in the legend.
	 *
	 * A group has no single control to label, so the legend is its heading —
	 * and the help icon belongs beside a heading, not beside one of the
	 * controls underneath it.
	 */
	public function test_a_group_puts_it_in_the_legend(): void {
		$html = ( new FieldSet(
			[
				'size' => [
					'type'    => 'dimensions',
					'label'   => 'Size',
					'tooltip' => 'In centimetres.',
				],
			],
			new ArrayContext( [] ),
			''
		) )->render();

		preg_match( '/<legend\b.*?<\/legend>/s', $html, $legend );

		$this->assertNotEmpty( $legend, 'The group has no legend.' );
		$this->assertStringContainsString( 'field-kit__tooltip', $legend[0] );
	}

	/**
	 * A caller drawing its own heading gets the tooltip for it, not twice.
	 *
	 * The settings table, the term row and the user row all draw their own
	 * header cell. Rendering one here as well would put a help icon against
	 * the side of the control.
	 */
	public function test_a_caller_drawing_its_own_heading_gets_none_here(): void {
		$set   = new FieldSet(
			[ 'key' => [ 'type' => 'text', 'label' => 'API key', 'tooltip' => 'Some help.' ] ],
			new ArrayContext( [] ),
			''
		);
		$field = $set->field( 'key' );

		$this->assertStringNotContainsString(
			'field-kit__tooltip',
			$set->render_field( $field, '', false )
		);

		// And it is available to that caller.
		$this->assertStringContainsString( 'field-kit__tooltip', Tooltip::for_field( $field ) );
	}
}
