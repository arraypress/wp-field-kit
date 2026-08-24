<?php
/**
 * Page header tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Support\PageHeader;
use PHPUnit\Framework\TestCase;

/**
 * The header reuses core's own classes, so what is worth asserting is that it
 * keeps reusing them — and the pieces core does not supply: the notice
 * marker, the selected tab, and the branding a commercial plugin puts in it.
 */
final class PageHeaderTest extends TestCase {

	/**
	 * Two tabs to render.
	 *
	 * @return array<string, array{label: string, url: string}>
	 */
	private function tabs(): array {
		return [
			'general'  => [
				'label' => 'General',
				'url'   => 'https://example.test/wp-admin/admin.php?page=x&tab=general',
			],
			'advanced' => [
				'label' => 'Advanced',
				'url'   => 'https://example.test/wp-admin/admin.php?page=x&tab=advanced',
			],
		];
	}

	/**
	 * The header uses core's classes rather than classes of its own.
	 *
	 * The point of the whole file: core styles these in a stylesheet the
	 * wp-admin bundle already loads, so the header matches core exactly and
	 * follows the user's colour scheme for no CSS. Renaming them silently
	 * loses all of that and still looks fine in a diff.
	 */
	public function test_the_header_uses_cores_own_classes(): void {
		$html = PageHeader::render( [ 'title' => 'Settings' ] );

		$this->assertStringContainsString( 'class="privacy-settings-header field-kit__page-header"', $html );
		$this->assertStringContainsString( 'class="privacy-settings-title-section"', $html );
		$this->assertStringContainsString( '<h1>Settings</h1>', $html );
	}

	/**
	 * The screen carries a body class, and the stylesheet acts on it.
	 *
	 * The header only spans the screen if #wpcontent's left padding is
	 * removed, and core does that with a body class rather than on the header
	 * itself. Without the class the header sits 20px in from the menu and
	 * looks nothing like core's — which is the only reason for using core's
	 * markup. Reported from the live page as "the margin on the left not
	 * pulling the header across".
	 */
	public function test_the_body_class_is_named_and_styled(): void {
		$class = PageHeader::body_class();

		$this->assertSame( 'field-kit__page-screen', $class );

		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		// Both selectors: .auto-fold raises the specificity for the
		// collapsed-menu state, and core writes both for the same reason.
		$this->assertStringContainsString( '.' . $class . ' #wpcontent', $css );
		$this->assertStringContainsString( '.' . $class . '.auto-fold #wpcontent', $css );

		// The padding removed has to be given back to whatever holds the body,
		// or the content sits flush against the menu.
		$this->assertStringContainsString( '.' . $class . ' .wrap', $css );
	}

	/**
	 * The notice marker is present.
	 *
	 * common.js moves admin notices to sit after it. Without one they are
	 * appended after the first heading on the page, which on a tabbed screen
	 * means somewhere arbitrary.
	 */
	public function test_the_notice_marker_is_always_rendered(): void {
		$this->assertStringEndsWith( '<hr class="wp-header-end">', PageHeader::render() );
	}

	/**
	 * The active tab is conveyed to assistive technology, not only drawn.
	 */
	public function test_the_active_tab_carries_aria_current(): void {
		$html = PageHeader::render(
			[
				'title'   => 'Settings',
				'tabs'    => $this->tabs(),
				'current' => 'advanced',
			]
		);

		$this->assertMatchesRegularExpression( '/tab=advanced"[^>]*aria-current="true"/', $html );
		$this->assertSame( 1, substr_count( $html, 'aria-current' ) );
		$this->assertSame( 1, substr_count( $html, 'active' ) );
	}

	/**
	 * With no current tab named, the first one is the active one.
	 */
	public function test_the_first_tab_is_active_by_default(): void {
		$html = PageHeader::render(
			[
				'title' => 'Settings',
				'tabs'  => $this->tabs(),
			]
		);

		$this->assertMatchesRegularExpression( '/tab=general"[^>]*aria-current="true"/', $html );
	}

	/**
	 * The tab navigation is labelled.
	 *
	 * A page can hold more than one nav, and an unlabelled one is announced
	 * only as "navigation".
	 */
	public function test_the_tab_navigation_is_labelled(): void {
		$html = PageHeader::render(
			[
				'tabs' => $this->tabs(),
			]
		);

		$this->assertStringContainsString( '<nav class="field-kit__page-tabs hide-if-no-js" aria-label="', $html );
	}

	/**
	 * No tabs means no navigation element at all.
	 */
	public function test_no_tabs_renders_no_navigation(): void {
		$this->assertStringNotContainsString( '<nav', PageHeader::render( [ 'title' => 'Settings' ] ) );
	}

	/**
	 * A logo renders with empty alt text.
	 *
	 * The heading beside it says the same thing, and announcing the plugin's
	 * name twice is worse than not describing a decorative image.
	 */
	public function test_a_logo_renders_with_empty_alt_text(): void {
		$html = PageHeader::render(
			[
				'title' => 'My Plugin',
				'logo'  => 'https://example.test/logo.svg',
			]
		);

		$this->assertStringContainsString( 'src="https://example.test/logo.svg"', $html );
		$this->assertStringContainsString( 'alt=""', $html );
		$this->assertStringContainsString( 'field-kit__page-logo', $html );
	}

	/**
	 * No logo renders no image.
	 */
	public function test_no_logo_renders_no_image(): void {
		$this->assertStringNotContainsString( '<img', PageHeader::render( [ 'title' => 'My Plugin' ] ) );
	}

	/**
	 * A badge renders as a string, and its text is escaped.
	 */
	public function test_a_string_badge_renders_escaped(): void {
		$html = PageHeader::render(
			[
				'title' => 'My Plugin',
				'badge' => 'v2.1.0 <script>',
			]
		);

		$this->assertStringContainsString( 'class="field-kit__page-badge"', $html );
		$this->assertStringContainsString( 'v2.1.0 &lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * A badge given as an array keeps its own class alongside the kit's.
	 */
	public function test_an_array_badge_keeps_its_class(): void {
		$html = PageHeader::render(
			[
				'badge' => [
					'text'  => 'Pro',
					'class' => 'my-badge',
				],
			]
		);

		$this->assertStringContainsString( 'class="field-kit__page-badge my-badge"', $html );
		$this->assertStringContainsString( '>Pro</span>', $html );
	}

	/**
	 * A callable badge is rendered through kses.
	 *
	 * A callable returning raw markup is a callable returning whatever a
	 * filter put into it.
	 */
	public function test_a_callable_badge_is_filtered(): void {
		$html = PageHeader::render(
			[
				'badge' => static fn() => '<span class="x">Beta</span><script>alert(1)</script>',
			]
		);

		$this->assertStringContainsString( 'Beta', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	/**
	 * An empty badge renders nothing at all.
	 */
	public function test_an_empty_badge_renders_nothing(): void {
		foreach ( [ '', [], [ 'text' => '' ] ] as $badge ) {
			$this->assertStringNotContainsString(
				'field-kit__page-badge',
				PageHeader::render(
					[
						'title' => 'My Plugin',
						'badge' => $badge,
					]
				)
			);
		}
	}

	/**
	 * Action markup is placed in its own region.
	 */
	public function test_actions_render_in_their_own_region(): void {
		$html = PageHeader::render(
			[
				'title'   => 'Settings',
				'actions' => '<form></form>',
			]
		);

		$this->assertStringContainsString( '<div class="field-kit__page-actions"><form></form></div>', $html );
	}

	/**
	 * A tab URL is escaped.
	 */
	public function test_a_tab_url_is_escaped(): void {
		$html = PageHeader::render(
			[
				'tabs' => [
					'one' => [
						'label' => 'One',
						'url'   => 'https://example.test/?a=1&b=2',
					],
				],
			]
		);

		$this->assertStringContainsString( 'href="https://example.test/?a=1&amp;b=2"', $html );
	}
}
