<?php
/**
 * Panel tab tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Support\PanelTabs;
use PHPUnit\Framework\TestCase;

/**
 * A tab list that is a row of divs and a click handler gives someone using a
 * keyboard a set of things that look like buttons, announce as nothing, and
 * do not respond to the arrow keys every other tab list in the admin does.
 *
 * So most of what is asserted here is the ARIA tabs pattern, which is the
 * part that is invisible when it is right and invisible when it is wrong.
 */
final class PanelTabsTest extends TestCase {

	/**
	 * Two panels to render.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function panels(): array {
		return [
			'files' => [
				'label'   => 'Files',
				'icon'    => 'media-default',
				'content' => '<p>File fields</p>',
			],
			'notes' => [
				'label'   => 'Notes',
				'content' => '<p>Note fields</p>',
			],
		];
	}

	/**
	 * No panels renders nothing.
	 */
	public function test_no_panels_renders_nothing(): void {
		$this->assertSame( '', PanelTabs::render( 'box', [] ) );
	}

	/**
	 * It is a tab list, and says which way it runs.
	 */
	public function test_it_is_an_orientated_tablist(): void {
		$html = PanelTabs::render( 'box', $this->panels() );

		$this->assertStringContainsString( 'role="tablist"', $html );
		$this->assertStringContainsString( 'aria-orientation="vertical"', $html );
		$this->assertSame( 2, substr_count( $html, 'role="tab"' ) );
		$this->assertSame( 2, substr_count( $html, 'role="tabpanel"' ) );
	}

	/**
	 * Each tab controls a panel, and each panel is named by its tab.
	 *
	 * Both halves of the association, because one without the other is a
	 * panel nobody can find or a panel with no name.
	 */
	public function test_tabs_and_panels_point_at_each_other(): void {
		$html = PanelTabs::render( 'box', $this->panels() );

		$this->assertStringContainsString( 'id="box-tab-files"', $html );
		$this->assertStringContainsString( 'aria-controls="box-panel-files"', $html );
		$this->assertStringContainsString( 'id="box-panel-files"', $html );
		$this->assertStringContainsString( 'aria-labelledby="box-tab-files"', $html );
	}

	/**
	 * The first panel is the open one, and the rest are hidden.
	 */
	public function test_the_first_panel_is_open(): void {
		$html = PanelTabs::render( 'box', $this->panels() );

		$this->assertSame( 1, substr_count( $html, 'aria-selected="true"' ) );
		$this->assertSame( 1, substr_count( $html, 'aria-selected="false"' ) );

		// The bare `hidden` attribute, not display:none in a stylesheet: a
		// panel hidden only by CSS is still read out. Matched precisely,
		// because aria-hidden on the icon contains the same word.
		$this->assertSame( 1, preg_match_all( '/\shidden(?=[\s>])/', $html ) );
	}

	/**
	 * One tab is in the tab order at a time.
	 *
	 * Arrow keys move between tabs; Tab leaves the list. Without this,
	 * reaching the panel from the first tab means tabbing through every
	 * other tab first.
	 */
	public function test_only_the_selected_tab_is_in_the_tab_order(): void {
		$html = PanelTabs::render( 'box', $this->panels() );

		preg_match_all( '/role="tab"[^>]*tabindex="(-?\d)"/', $html, $order );

		$this->assertSame( [ '0', '-1' ], $order[1] );
	}

	/**
	 * A panel is focusable, so leaving the tab list lands somewhere.
	 */
	public function test_a_panel_is_focusable(): void {
		$html = PanelTabs::render( 'box', $this->panels() );

		$this->assertMatchesRegularExpression( '/role="tabpanel"[^>]*tabindex="0"/', $html );
	}

	/**
	 * An icon is decorative; the label is the name.
	 */
	public function test_an_icon_is_hidden_from_assistive_technology(): void {
		$html = PanelTabs::render( 'box', $this->panels() );

		$this->assertStringContainsString( 'dashicons-media-default', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( '>Files</span>', $html );
	}

	/**
	 * A panel with no icon renders without one.
	 */
	public function test_a_panel_without_an_icon_renders_no_icon(): void {
		$html = PanelTabs::render( 'box', [ 'notes' => [ 'label' => 'Notes' ] ] );

		$this->assertStringNotContainsString( 'dashicons', $html );
	}

	/**
	 * A label is escaped; content is markup the caller already built.
	 */
	public function test_a_label_is_escaped(): void {
		$html = PanelTabs::render(
			'box',
			[ 'x' => [ 'label' => '<script>alert(1)</script>' ] ]
		);

		$this->assertStringNotContainsString( '<script', $html );
	}

	/**
	 * The selection follows the user's admin colour scheme.
	 *
	 * Being blue because blue is what core happened to ship with is the thing
	 * every one of these components has had to be corrected on.
	 */
	public function test_the_selection_uses_the_admin_theme_colour(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		preg_match(
			'/\.field-kit__panel-tab\[aria-selected="true"\]\s*\{([^}]*)\}/',
			$css,
			$rule
		);

		$this->assertNotEmpty( $rule, 'The selected tab has no rule.' );
		$this->assertStringContainsString( '--wp-admin-theme-color', $rule[1] );
	}
}
