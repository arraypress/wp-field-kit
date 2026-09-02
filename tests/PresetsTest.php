<?php
/**
 * Option preset tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\Countries\Countries;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Support\Presets;
use PHPUnit\Framework\TestCase;

/**
 * A field says `'options' => 'roles'` and gets the site's roles.
 *
 * What is worth pinning down is the shape each built-in comes back in --
 * keyed by what is stored, labelled by what is shown, grouped where a flat
 * list would be too long -- because the shape is the contract a select
 * renders from, and a preset that comes back keyed by label stores the label.
 */
final class PresetsTest extends TestCase {

	/**
	 * Every preset reads a global a test sets. Start each from nothing.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		foreach (
			[
				'fk_roles',
				'fk_post_types',
				'fk_taxonomies',
				'fk_pages',
				'fk_image_sizes',
				'fk_page_templates',
				'fk_schedules',
				'fk_filters',
				'fk_doing_it_wrong',
			] as $global
		) {
			$GLOBALS[ $global ] = [];
		}
	}

	/**
	 * Build a field the way FieldSet does, defaults merged in.
	 *
	 * @param string               $type   Field type.
	 * @param array<string, mixed> $config Field configuration.
	 *
	 * @return Field
	 */
	private function field( string $type, array $config = [] ): Field {
		$resolved = ( new Registry() )->get( $type );

		return new Field( 'demo', $resolved, array_merge( $resolved->defaults(), [ 'label' => 'Demo' ], $config ), null );
	}

	/**
	 * Roles come back keyed by role, which is what is stored on a user.
	 */
	public function test_roles(): void {
		$GLOBALS['fk_roles'] = [ 'administrator' => 'Administrator', 'editor' => 'Editor' ];

		$this->assertSame( [ 'administrator' => 'Administrator', 'editor' => 'Editor' ], Presets::options( 'roles' ) );
	}

	/**
	 * Public post types only, keyed by name.
	 *
	 * A revision or a menu item is a post type too, and no field wants it.
	 */
	public function test_post_types(): void {
		$GLOBALS['fk_post_types'] = [
			'post'     => (object) [ 'name' => 'post', 'label' => 'Posts', 'public' => true ],
			'product'  => (object) [ 'name' => 'product', 'label' => 'Products', 'public' => true ],
			'revision' => (object) [ 'name' => 'revision', 'label' => 'Revisions', 'public' => false ],
		];

		$this->assertSame( [ 'post' => 'Posts', 'product' => 'Products' ], Presets::options( 'post_types' ) );
	}

	/**
	 * Public taxonomies only, keyed by name.
	 */
	public function test_taxonomies(): void {
		$GLOBALS['fk_taxonomies'] = [
			'category' => (object) [ 'name' => 'category', 'label' => 'Categories', 'public' => true ],
			'nav_menu' => (object) [ 'name' => 'nav_menu', 'label' => 'Navigation Menus', 'public' => false ],
		];

		$this->assertSame( [ 'category' => 'Categories' ], Presets::options( 'taxonomies' ) );
	}

	/**
	 * Pages are listed as a tree: a child under its parent, dashed by depth.
	 *
	 * The dash rather than core's `&nbsp;` padding, because every label is
	 * escaped on its way to the page and the entity would print as text.
	 * A page whose parent is not in the list is still in the list.
	 */
	public function test_pages_keep_their_hierarchy(): void {
		$GLOBALS['fk_pages'] = [
			(object) [ 'ID' => 1, 'post_title' => 'About', 'post_parent' => 0 ],
			(object) [ 'ID' => 4, 'post_title' => 'Contact', 'post_parent' => 0 ],
			(object) [ 'ID' => 3, 'post_title' => 'History', 'post_parent' => 2 ],
			(object) [ 'ID' => 5, 'post_title' => 'Orphan', 'post_parent' => 99 ],
			(object) [ 'ID' => 2, 'post_title' => 'Team', 'post_parent' => 1 ],
		];

		$this->assertSame(
			[
				1 => 'About',
				2 => '— Team',
				3 => '— — History',
				4 => 'Contact',
				5 => 'Orphan',
			],
			Presets::options( 'pages' )
		);
	}

	/**
	 * get_pages() returns false as well as an array.
	 */
	public function test_no_pages_is_an_empty_list(): void {
		$GLOBALS['fk_pages'] = false;

		$this->assertSame( [], Presets::options( 'pages' ) );
	}

	/**
	 * Timezones are grouped by continent, with UTC on its own.
	 *
	 * Four hundred identifiers in one flat list is not a control anyone can
	 * use, and UTC belongs to no continent.
	 */
	public function test_timezones_are_grouped_by_continent(): void {
		$options = Presets::options( 'timezones' );

		$this->assertSame( 'London', $options['Europe']['Europe/London'] );
		$this->assertSame( 'New York', $options['America']['America/New_York'] );
		$this->assertSame( 'Argentina - Buenos Aires', $options['America']['America/Argentina/Buenos_Aires'] );
		$this->assertSame( 'UTC', $options['UTC'] );

		// Every entry but UTC is a group, keyed by a continent.
		foreach ( $options as $label => $group ) {
			if ( 'UTC' === $label ) {
				continue;
			}

			$this->assertIsArray( $group, $label );
			$this->assertStringNotContainsString( '/', (string) $label );
		}
	}

	/**
	 * Image sizes carry their dimensions, and `full` is offered though it is
	 * not a registered size.
	 */
	public function test_image_sizes(): void {
		$GLOBALS['fk_image_sizes'] = [
			'thumbnail'    => [ 'width' => 150, 'height' => 150, 'crop' => true ],
			'medium_large' => [ 'width' => 768, 'height' => 0, 'crop' => false ],
		];

		$this->assertSame(
			[
				'thumbnail'    => 'thumbnail (150×150)',
				'medium_large' => 'medium_large (768×0)',
				'full'         => 'full (original size)',
			],
			Presets::options( 'image_sizes' )
		);
	}

	/**
	 * The default template is first, stored as `default` like core does.
	 */
	public function test_page_templates(): void {
		$GLOBALS['fk_page_templates'] = [ 'templates/landing.php' => 'Landing' ];

		$this->assertSame(
			[ 'default' => 'Default template', 'templates/landing.php' => 'Landing' ],
			Presets::options( 'page_templates' )
		);
	}

	/**
	 * Cron schedules by name, labelled by display name.
	 */
	public function test_cron_schedules(): void {
		$GLOBALS['fk_schedules'] = [
			'hourly' => [ 'interval' => 3600, 'display' => 'Once Hourly' ],
			'daily'  => [ 'interval' => 86400, 'display' => 'Once Daily' ],
		];

		$this->assertSame( [ 'hourly' => 'Once Hourly', 'daily' => 'Once Daily' ], Presets::options( 'cron_schedules' ) );
	}

	/**
	 * Countries are wp-countries' list, keyed by ISO code.
	 */
	public function test_countries(): void {
		$options = Presets::options( 'countries' );

		$this->assertSame( Countries::all(), $options );
		$this->assertSame( 'United Kingdom', $options['GB'] );
	}

	/**
	 * With `continents` on the field, countries are grouped by continent
	 * -- and no country is lost in the grouping.
	 */
	public function test_countries_group_by_continent_when_the_field_asks(): void {
		$options = Presets::options( 'countries', $this->field( 'country', [ 'continents' => true ] ) );

		$this->assertSame( 'United Kingdom', $options['Europe']['GB'] );
		$this->assertSame( 'Japan', $options['Asia']['JP'] );

		$count = 0;

		foreach ( $options as $group ) {
			$this->assertIsArray( $group );

			$count += count( $group );
		}

		$this->assertSame( count( Countries::all() ), $count );
	}

	/**
	 * Currencies are wp-money's select options, keyed by code.
	 */
	public function test_currencies(): void {
		$options = Presets::options( 'currencies' );

		$this->assertSame( 'GBP - British Pound (£)', $options['GBP'] );
		$this->assertArrayHasKey( 'USD', $options );
	}

	/**
	 * A consumer's preset resolves like a built-in, and not before it is
	 * asked for.
	 *
	 * Lazily, because most sources read something -- the theme, a product
	 * table -- that is not there when the field is declared.
	 */
	public function test_a_registered_preset_resolves_lazily(): void {
		$calls = 0;

		Presets::register(
			'fk_test_flavours',
			static function ( ?Field $field ) use ( &$calls ): array {
				++$calls;

				return [ 'vanilla' => 'Vanilla', 'pistachio' => 'Pistachio' ];
			}
		);

		$this->assertTrue( Presets::has( 'fk_test_flavours' ) );
		$this->assertContains( 'fk_test_flavours', Presets::names() );
		$this->assertSame( 0, $calls );

		$this->assertSame( [ 'vanilla' => 'Vanilla', 'pistachio' => 'Pistachio' ], Presets::options( 'fk_test_flavours' ) );
		$this->assertSame( 1, $calls );
	}

	/**
	 * A field resolves a preset by name, and hands itself to the resolver.
	 */
	public function test_a_field_resolves_a_preset_by_name(): void {
		$GLOBALS['fk_roles'] = [ 'editor' => 'Editor' ];

		$this->assertSame( [ 'editor' => 'Editor' ], $this->field( 'select', [ 'options' => 'roles' ] )->options() );

		Presets::register(
			'fk_test_echo',
			static fn( ?Field $field ): array => [ 'key' => $field?->key() ?? 'nothing' ]
		);

		$this->assertSame( [ 'key' => 'demo' ], $this->field( 'select', [ 'options' => 'fk_test_echo' ] )->options() );
	}

	/**
	 * A string that names a function is still a callable, as it always was.
	 *
	 * A preset name is checked first, because a preset called `pages` must
	 * not turn into a call to some plugin's pages() -- but a function name
	 * that is not a preset keeps working.
	 */
	public function test_a_callable_string_still_resolves(): void {
		$field = $this->field( 'select', [ 'options' => self::class . '::flavours' ] );

		$this->assertSame( [ 'vanilla' => 'Vanilla' ], $field->options() );
	}

	/**
	 * The options a preset resolves to pass through a filter, named per build.
	 */
	public function test_the_options_are_filtered(): void {
		$GLOBALS['fk_roles'] = [ 'editor' => 'Editor', 'subscriber' => 'Subscriber' ];

		$seen = [];

		add_filter(
			'field_kit_preset_options',
			static function ( array $options, string $name, ?Field $field ) use ( &$seen ): array {
				$seen = [ $name, $field?->key() ];

				unset( $options['subscriber'] );

				return $options;
			}
		);

		$this->assertSame( [ 'editor' => 'Editor' ], $this->field( 'select', [ 'options' => 'roles' ] )->options() );
		$this->assertSame( [ 'roles', 'demo' ], $seen );
	}

	/**
	 * An unknown name is reported, and the field has nothing to choose from.
	 *
	 * Reported rather than thrown: it is a typo in configuration, which is
	 * not a reason to take the page down.
	 */
	public function test_an_unknown_name_warns(): void {
		$this->assertSame( [], Presets::options( 'fk_test_nonsense' ) );

		$this->assertCount( 1, $GLOBALS['fk_doing_it_wrong'] );
		$this->assertStringContainsString( 'fk_test_nonsense', $GLOBALS['fk_doing_it_wrong'][0] );
		$this->assertStringContainsString( 'roles', $GLOBALS['fk_doing_it_wrong'][0] );
	}

	/**
	 * The built-ins are all there under the names the README documents.
	 */
	public function test_the_built_ins_are_registered(): void {
		foreach (
			[
				'roles',
				'post_types',
				'taxonomies',
				'pages',
				'timezones',
				'image_sizes',
				'page_templates',
				'cron_schedules',
				'countries',
				'currencies',
			] as $name
		) {
			$this->assertTrue( Presets::has( $name ), $name );
		}
	}

	/**
	 * A function-name callable for the test above.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, string>
	 */
	public static function flavours( Field $field ): array {
		return [ 'vanilla' => 'Vanilla' ];
	}
}
