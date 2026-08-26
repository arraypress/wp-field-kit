<?php
/**
 * Nav menu and icon field types.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Renderer;
use ArrayPress\FieldKit\Types\IconType;
use PHPUnit\Framework\TestCase;

/**
 * Covers NavMenuType and IconType.
 */
final class NavMenuIconTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['fk_nav_menus'] = [];
	}

	/**
	 * Render a field.
	 *
	 * @param string $type   Field type.
	 * @param array  $config Field configuration.
	 *
	 * @return string
	 */
	private function render( string $type, array $config = [], mixed $value = null ): string {
		return ( new Renderer() )->render( $this->field( $type, $config, $value ) );
	}

	/**
	 * Build a field the way FieldSet does.
	 *
	 * The type's defaults() are merged in first, which is where a type that
	 * supplies its own options puts them. Constructing Field directly skips
	 * that, and the field comes up with no options at all.
	 *
	 * @param string $type   Field type.
	 * @param array  $config Field configuration.
	 *
	 * @return Field
	 */
	private function field( string $type, array $config = [], mixed $value = null ): Field {
		$resolved = ( new Registry() )->get( $type );

		return new Field(
			'demo',
			$resolved,
			array_merge( $resolved->defaults(), [ 'label' => 'Demo', 'input_name' => 'demo' ], $config ),
			$value
		);
	}

	/**
	 * Sanitise through a type.
	 *
	 * @param string $type   Field type.
	 * @param mixed  $value  Submitted value.
	 * @param array  $config Field configuration.
	 *
	 * @return mixed
	 */
	private function sanitize( string $type, mixed $value, array $config = [] ) {
		return ( new Registry() )->get( $type )->sanitize( $value, $this->field( $type, $config ) );
	}

	/**
	 * A menu becomes an option, keyed by term id.
	 *
	 * The id survives a rename, which the slug does not.
	 *
	 * @return void
	 */
	public function test_menus_are_listed_by_id(): void {
		$GLOBALS['fk_nav_menus'] = [
			(object) [ 'term_id' => 7, 'slug' => 'primary', 'name' => 'Primary Menu' ],
			(object) [ 'term_id' => 9, 'slug' => 'footer', 'name' => 'Footer Menu' ],
		];

		$markup = $this->render( 'nav_menu' );

		$this->assertStringContainsString( 'value="7"', $markup );
		$this->assertStringContainsString( '>Primary Menu<', $markup );
		$this->assertStringContainsString( 'value="9"', $markup );
	}

	/**
	 * return => slug stores the slug instead.
	 *
	 * @return void
	 */
	public function test_menus_can_be_keyed_by_slug(): void {
		$GLOBALS['fk_nav_menus'] = [
			(object) [ 'term_id' => 7, 'slug' => 'primary', 'name' => 'Primary Menu' ],
		];

		$markup = $this->render( 'nav_menu', [ 'return' => 'slug' ] );

		$this->assertStringContainsString( 'value="primary"', $markup );
		$this->assertStringNotContainsString( 'value="7"', $markup );
	}

	/**
	 * A site with no menus renders an empty control, not a warning.
	 *
	 * @return void
	 */
	public function test_no_menus_is_not_an_error(): void {
		$markup = $this->render( 'nav_menu' );

		$this->assertStringContainsString( '<select', $markup );
	}

	/**
	 * A filter that returns something other than an array does not fatal.
	 *
	 * wp_get_nav_menus() is apply_filters( 'wp_get_nav_menus', get_terms( ... ) ),
	 * so any plugin can make it return anything at all -- and get_terms()
	 * itself returns a WP_Error for a taxonomy that is not registered.
	 * foreach over either is a fatal on the menu screen.
	 *
	 * @return void
	 */
	public function test_a_filtered_non_array_is_survived(): void {
		foreach ( [ false, null, 'nonsense', 42 ] as $returned ) {
			$GLOBALS['fk_nav_menus'] = $returned;

			$markup = $this->render( 'nav_menu' );

			$this->assertStringContainsString( '<select', $markup, var_export( $returned, true ) );
		}
	}

	/**
	 * An option key that is not a usable class name is refused.
	 *
	 * The stored value is printed into a class attribute. array_key_exists()
	 * on its own would happily accept a key someone put markup in, so the
	 * value is reduced to a class name first and then has to still match.
	 *
	 * @return void
	 */
	public function test_icon_refuses_an_unusable_option_key(): void {
		$config = [ 'options' => [ '"><script>alert(1)</script>' => 'Nasty', 'my-icon' => 'Fine' ] ];

		$this->assertSame( '', $this->sanitize( 'icon', '"><script>alert(1)</script>', $config ) );
		$this->assertSame( 'my-icon', $this->sanitize( 'icon', 'my-icon', $config ) );
	}

	/**
	 * The icon field offers the curated set.
	 *
	 * @return void
	 */
	public function test_icon_offers_dashicons(): void {
		$markup = $this->render( 'icon' );

		$this->assertStringContainsString( 'value="dashicons-cart"', $markup );
		$this->assertStringContainsString( '>Cart<', $markup );
	}

	/**
	 * The chosen icon is shown beside the control.
	 *
	 * @return void
	 */
	public function test_icon_shows_a_preview(): void {
		$markup = $this->render( 'icon', [], 'dashicons-cart' );

		$this->assertStringContainsString( 'field-kit__icon-preview', $markup );
		$this->assertStringContainsString( 'dashicons dashicons-cart', $markup );
	}

	/**
	 * With nothing chosen the preview shows a muted placeholder.
	 *
	 * class="dashicons " renders nothing at all, so the control came up with
	 * an invisible gap where the swatch should be and read as broken before
	 * it had been touched.
	 *
	 * @return void
	 */
	public function test_the_empty_preview_is_visible(): void {
		$markup = $this->render( 'icon' );

		$this->assertStringContainsString( 'field-kit__icon-preview--empty', $markup );
		$this->assertStringContainsString( 'dashicons-art', $markup );
		$this->assertDoesNotMatchRegularExpression( '/class="[^"]*dashicons "/', $markup );
	}

	/**
	 * The preview is hidden from assistive technology.
	 *
	 * It repeats the select's own value, so announcing it says everything
	 * twice.
	 *
	 * @return void
	 */
	public function test_the_preview_is_decorative(): void {
		$this->assertStringContainsString( 'aria-hidden="true"', $this->render( 'icon' ) );
		$this->assertStringContainsString( 'aria-hidden="true"', $this->render( 'icon', [], 'dashicons-cart' ) );
	}

	/**
	 * Only an icon that is on offer is stored.
	 *
	 * The value is printed into a class attribute, so a typed one has to be
	 * rejected rather than escaped and kept.
	 *
	 * @return void
	 */
	public function test_icon_rejects_anything_not_offered(): void {
		$this->assertSame( 'dashicons-cart', $this->sanitize( 'icon', 'dashicons-cart' ) );
		$this->assertSame( '', $this->sanitize( 'icon', 'dashicons-not-a-real-icon' ) );
		$this->assertSame( '', $this->sanitize( 'icon', '" onload="alert(1)' ) );
		$this->assertSame( '', $this->sanitize( 'icon', [ 'array' ] ) );
	}

	/**
	 * A custom option list is honoured, and bounds the sanitiser too.
	 *
	 * @return void
	 */
	public function test_icon_accepts_a_custom_set(): void {
		$config = [ 'options' => [ 'my-icon-star' => 'Star', 'my-icon-moon' => 'Moon' ] ];

		$this->assertStringContainsString( 'value="my-icon-star"', $this->render( 'icon', $config ) );
		$this->assertSame( 'my-icon-star', $this->sanitize( 'icon', 'my-icon-star', $config ) );
		$this->assertSame( '', $this->sanitize( 'icon', 'dashicons-cart', $config ) );
	}

	/**
	 * Every icon in the shipped list is a usable class name.
	 *
	 * @return void
	 */
	public function test_shipped_icons_are_valid_class_names(): void {
		foreach ( array_keys( IconType::ICONS ) as $icon ) {
			$this->assertSame( $icon, sanitize_html_class( $icon ), $icon );
			$this->assertStringStartsWith( 'dashicons-', $icon );
		}
	}

	/**
	 * Both types are registered under the ids they document.
	 *
	 * @return void
	 */
	public function test_both_types_are_registered(): void {
		$registry = new Registry();

		$this->assertContains( 'nav_menu', $registry->ids() );
		$this->assertContains( 'icon', $registry->ids() );
		$this->assertSame( 'icon', $registry->get( 'icon' )->id() );
	}

}
