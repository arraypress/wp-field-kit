<?php
/**
 * Tab and accordion sections.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\OptionContext;
use ArrayPress\FieldKit\FieldSet;
use ArrayPress\FieldKit\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Covers the tab and accordion markers, and the grouping they drive.
 */
final class SectionsTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['fk_meta']    = [];
		$GLOBALS['fk_options'] = [];
	}

	/**
	 * Render a set over one option.
	 *
	 * @param array $configs Field configuration.
	 *
	 * @return string
	 */
	private function render( array $configs ): string {
		return ( new FieldSet( $configs, new OptionContext( 'fk_test' ), 'fk_test' ) )->render();
	}

	/**
	 * A set with no markers renders exactly as it always did.
	 *
	 * @return void
	 */
	public function test_no_markers_means_no_sections(): void {
		$markup = $this->render( [
			'one' => [ 'type' => 'text', 'label' => 'One' ],
			'two' => [ 'type' => 'text', 'label' => 'Two' ],
		] );

		$this->assertStringNotContainsString( 'field-kit__panel-tabs', $markup );
		$this->assertStringContainsString( 'name="fk_test[one]"', $markup );
		$this->assertStringContainsString( 'name="fk_test[two]"', $markup );
	}

	/**
	 * Two markers make tabs, and every field still renders.
	 *
	 * @return void
	 */
	public function test_two_tabs_build_a_tablist(): void {
		$markup = $this->render( [
			'general_tab' => [ 'type' => 'tab', 'label' => 'General' ],
			'name'        => [ 'type' => 'text', 'label' => 'Name' ],
			'advanced_tab' => [ 'type' => 'tab', 'label' => 'Advanced' ],
			'secret'      => [ 'type' => 'text', 'label' => 'Secret' ],
		] );

		$this->assertStringContainsString( 'field-kit__panel-tabs', $markup );
		$this->assertStringContainsString( 'role="tablist"', $markup );
		$this->assertStringContainsString( '>General<', $markup );
		$this->assertStringContainsString( '>Advanced<', $markup );
		$this->assertStringContainsString( 'name="fk_test[name]"', $markup );
		$this->assertStringContainsString( 'name="fk_test[secret]"', $markup );
	}

	/**
	 * A tab strip with one tab is furniture, so a lone marker is ignored.
	 *
	 * @return void
	 */
	public function test_a_single_marker_is_not_a_layout(): void {
		$markup = $this->render( [
			'only_tab' => [ 'type' => 'tab', 'label' => 'Only' ],
			'name'     => [ 'type' => 'text', 'label' => 'Name' ],
		] );

		$this->assertStringNotContainsString( 'field-kit__panel-tabs', $markup );
		$this->assertStringNotContainsString( '>Only<', $markup );
		$this->assertStringContainsString( 'name="fk_test[name]"', $markup );

		// And it leaves nothing behind. The marker draws no control, so
		// rendering it anyway emits an empty wrapper -- which a form table
		// turns into a blank row with a blank label beside it.
		$this->assertStringNotContainsString( 'field-kit__field--tab', $markup );
	}

	/**
	 * Fields before the first marker render ahead of the tabs.
	 *
	 * A set can open with a couple of fields and then start tabbing; those
	 * fields belong to no tab and must not be swallowed by the first one.
	 *
	 * @return void
	 */
	public function test_fields_before_the_first_marker_are_kept(): void {
		$markup = $this->render( [
			'title'    => [ 'type' => 'text', 'label' => 'Title' ],
			'one_tab'  => [ 'type' => 'tab', 'label' => 'One' ],
			'alpha'    => [ 'type' => 'text', 'label' => 'Alpha' ],
			'two_tab'  => [ 'type' => 'tab', 'label' => 'Two' ],
			'beta'     => [ 'type' => 'text', 'label' => 'Beta' ],
		] );

		$this->assertStringContainsString( 'name="fk_test[title]"', $markup );
		$this->assertLessThan(
			strpos( $markup, 'field-kit__panel-tabs' ),
			strpos( $markup, 'name="fk_test[title]"' ),
			'the lead field should render before the tabs'
		);
	}

	/**
	 * Each field lands in the tab it follows.
	 *
	 * @return void
	 */
	public function test_fields_land_in_the_right_tab(): void {
		$markup = $this->render( [
			'one_tab' => [ 'type' => 'tab', 'label' => 'One' ],
			'alpha'   => [ 'type' => 'text', 'label' => 'Alpha' ],
			'two_tab' => [ 'type' => 'tab', 'label' => 'Two' ],
			'beta'    => [ 'type' => 'text', 'label' => 'Beta' ],
		] );

		$panels = explode( 'role="tabpanel"', $markup );

		$this->assertCount( 3, $panels, 'expected two panels' );
		$this->assertStringContainsString( 'fk_test[alpha]', $panels[1] );
		$this->assertStringNotContainsString( 'fk_test[beta]', $panels[1] );
		$this->assertStringContainsString( 'fk_test[beta]', $panels[2] );
	}

	/**
	 * Two accordion markers make disclosure regions, not tabs.
	 *
	 * The markup is core's own accordion -- the privacy guide's -- so the
	 * sections look like the rest of the admin and edit.css styles them.
	 *
	 * @return void
	 */
	public function test_accordions_use_cores_accordion(): void {
		$markup = $this->render( [
			'one_section' => [ 'type' => 'accordion', 'label' => 'First' ],
			'alpha'       => [ 'type' => 'text', 'label' => 'Alpha' ],
			'two_section' => [ 'type' => 'accordion', 'label' => 'Second' ],
			'beta'        => [ 'type' => 'text', 'label' => 'Beta' ],
		] );

		$this->assertSame( 2, substr_count( $markup, 'privacy-settings-accordion-trigger' ) );
		$this->assertSame( 2, substr_count( $markup, 'class="field-kit__accordion-panel"' ) );
		$this->assertStringNotContainsString( 'privacy-settings-accordion-panel', $markup );
		$this->assertStringContainsString( '<div class="privacy-settings-accordion field-kit__accordion">', $markup );
		$this->assertStringNotContainsString( '<details', $markup );
		$this->assertStringNotContainsString( 'field-kit__panel-tabs', $markup );
	}

	/**
	 * The first accordion is open, the rest are closed.
	 *
	 * @return void
	 */
	public function test_the_first_accordion_starts_open(): void {
		$markup = $this->render( [
			'one_section' => [ 'type' => 'accordion', 'label' => 'First' ],
			'alpha'       => [ 'type' => 'text' ],
			'two_section' => [ 'type' => 'accordion', 'label' => 'Second' ],
			'beta'        => [ 'type' => 'text' ],
		] );

		$this->assertSame( 1, substr_count( $markup, 'aria-expanded="true"' ) );
		$this->assertSame( 1, substr_count( $markup, 'aria-expanded="false"' ) );
		$this->assertSame( 1, substr_count( $markup, ' hidden>' ) );
		$this->assertMatchesRegularExpression( '/aria-expanded="true"[^>]*aria-controls="([^"]+)"[^>]*>.*?<div id="\\1" class="[^"]+">/s', $markup );
	}

	/**
	 * `open` overrides which sections start expanded.
	 *
	 * @return void
	 */
	public function test_open_can_be_set_per_section(): void {
		$markup = $this->render( [
			'one_section' => [ 'type' => 'accordion', 'label' => 'First', 'open' => false ],
			'alpha'       => [ 'type' => 'text' ],
			'two_section' => [ 'type' => 'accordion', 'label' => 'Second', 'open' => true ],
			'beta'        => [ 'type' => 'text' ],
		] );

		$this->assertSame( 1, substr_count( $markup, 'aria-expanded="true"' ) );
		$this->assertMatchesRegularExpression( '/aria-expanded="true"[^>]*><span class="title">Second<\/span>/', $markup );
	}

	/**
	 * Markers store nothing, so they never appear in saved values.
	 *
	 * @return void
	 */
	public function test_markers_store_nothing(): void {
		$set = new FieldSet(
			[
				'one_tab' => [ 'type' => 'tab', 'label' => 'One' ],
				'name'    => [ 'type' => 'text', 'label' => 'Name' ],
				'two_tab' => [ 'type' => 'tab', 'label' => 'Two' ],
				'other'   => [ 'type' => 'text', 'label' => 'Other' ],
			],
			new OptionContext( 'fk_test' ),
			'fk_test'
		);

		$set->save( [ 'name' => 'Ada', 'other' => 'Grace', 'one_tab' => 'nonsense' ] );

		$stored = $GLOBALS['fk_options']['fk_test'] ?? [];

		$this->assertArrayNotHasKey( 'one_tab', $stored );
		$this->assertArrayNotHasKey( 'two_tab', $stored );
		$this->assertSame( 'Ada', $stored['name'] ?? null );
	}

	/**
	 * Both markers report what they open, and nothing else does.
	 *
	 * @return void
	 */
	public function test_only_the_markers_open_sections(): void {
		$registry = new Registry();
		$openers  = [];

		foreach ( $registry->ids() as $id ) {
			$opens = $registry->get( $id )->opens_section();

			if ( '' !== $opens ) {
				$openers[ $id ] = $opens;
			}
		}

		ksort( $openers );

		$this->assertSame( [ 'accordion' => 'accordion', 'tab' => 'tab' ], $openers );
	}


	/**
	 * The accordion's frame and trigger are core's; the panel is ours.
	 *
	 * edit.css paints the heading, trigger and chevron on every admin
	 * screen; a second set of rules here would drift from it the next time
	 * core changed. The panel cannot take core's class -- it carries the
	 * privacy guide's suggested-text quotation onto every child -- so the
	 * sheet paints one to match, and the old <details> styling is gone
	 * rather than left as dead weight.
	 */
	public function test_the_accordion_is_styled_by_core(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		$this->assertMatchesRegularExpression(
			'/\.field-kit__accordion-panel\s*\{[^}]*background:\s*#fff/',
			$css
		);
		$this->assertMatchesRegularExpression( '/\.field-kit__accordion-panel\[hidden\]\s*\{[^}]*display:\s*none/', $css );
		$this->assertStringNotContainsString( 'field-kit__accordion-body', $css );
		$this->assertStringNotContainsString( 'field-kit__accordion-summary', $css );
	}
}
