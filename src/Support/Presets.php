<?php
/**
 * Option Presets
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.2.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Support;

use ArrayPress\Countries\Countries;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Utils\Runtime;
use ArrayPress\Money\Currencies;

/**
 * Named option lists a choice field can ask for by name.
 *
 * Every settings screen has a select whose options are the site's roles, or
 * its timezones, or its page templates. The list is the same on every screen
 * and every plugin writes the closure that builds it again, and each copy
 * gets one thing subtly different: roles left untranslated, `medium_large`
 * missing from the image sizes, no "Default template" at the top of the
 * template list, a timezone dropdown four hundred rows long with nothing to
 * group it.
 *
 * So the lists live here, under a name, and a field says
 * `'options' => 'roles'`. A resolver runs when the options are asked for
 * rather than when the field is declared, because most of them read
 * something -- the theme, the registered post types -- that is not there
 * until WordPress has finished loading, and a field is usually declared
 * before that.
 *
 * Two of the built-ins draw on sibling libraries: `countries` on
 * wp-countries and `currencies` on wp-money. Neither is loaded here. A
 * missing one gives an empty list and says, once, which package to install
 * -- rather than a fatal on the page, or an empty select with no explanation
 * of why.
 *
 * A consumer registers its own the same way, and may replace a built-in by
 * registering under its name.
 */
final class Presets {

	/**
	 * Name => resolver.
	 *
	 * A resolver is `callable( ?Field $field ): array` and returns options
	 * in the shape a select takes: value => label, or label => [ value =>
	 * label ] for a group.
	 *
	 * @var array<string, callable>
	 */
	private static array $resolvers = [];

	/**
	 * Whether the built-ins have been registered.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Packages already complained about, so a missing one is said once.
	 *
	 * @var array<string, true>
	 */
	private static array $warned = [];

	/**
	 * Register a preset, or replace a built-in one.
	 *
	 * @param string   $name     Preset name, as a field's `options` will spell it.
	 * @param callable $resolver Returns the options; handed the field, which may be null.
	 *
	 * @return void
	 */
	public static function register( string $name, callable $resolver ): void {
		self::boot();

		self::$resolvers[ $name ] = $resolver;
	}

	/**
	 * Whether a preset is registered.
	 *
	 * @param string $name Preset name.
	 *
	 * @return bool
	 */
	public static function has( string $name ): bool {
		self::boot();

		return isset( self::$resolvers[ $name ] );
	}

	/**
	 * The options a preset resolves to.
	 *
	 * An unknown name is a mistake in a field's configuration -- a typo, or
	 * a preset the consumer meant to register and did not -- so it is
	 * reported the way an unknown configuration key is, and the field
	 * renders with nothing to choose from rather than not at all.
	 *
	 * @param string     $name  Preset name.
	 * @param Field|null $field The field asking, for a resolver that reads its configuration.
	 *
	 * @return array<string|int, mixed>
	 */
	public static function options( string $name, ?Field $field = null ): array {
		self::boot();

		if ( ! isset( self::$resolvers[ $name ] ) ) {
			self::complain(
				sprintf(
					/* translators: 1: preset name, 2: comma-separated list of preset names */
					__( 'There is no option preset named "%1$s". Known presets: %2$s.', 'arraypress' ),
					$name,
					implode( ', ', self::names() )
				)
			);

			return [];
		}

		$options = ( self::$resolvers[ $name ] )( $field );
		$options = is_array( $options ) ? $options : [];

		if ( ! function_exists( 'apply_filters' ) ) {
			return $options;
		}

		/**
		 * The options a preset resolved to.
		 *
		 * Named per build (see Runtime), so two plugins each bundling a
		 * prefixed copy of the kit filter their own presets and not each
		 * other's.
		 *
		 * @param array      $options The options, value => label or label => [ value => label ].
		 * @param string     $name    The preset name.
		 * @param Field|null $field   The field asking, if one is.
		 */
		$filtered = apply_filters( Runtime::hook( 'preset_options' ), $options, $name, $field );

		return is_array( $filtered ) ? $filtered : $options;
	}

	/**
	 * Every registered preset name, sorted.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		self::boot();

		$names = array_keys( self::$resolvers );

		sort( $names );

		return $names;
	}

	/**
	 * Register the built-ins, once.
	 *
	 * On first use rather than at load, so a class that is merely
	 * autoloaded costs nothing. A name a consumer registered before this ran
	 * is kept: their version is a deliberate replacement, and the order in
	 * which plugins load is not something they chose.
	 *
	 * @return void
	 */
	private static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		$built_in = [
			'roles'          => self::roles( ... ),
			'post_types'     => self::post_types( ... ),
			'taxonomies'     => self::taxonomies( ... ),
			'pages'          => self::pages( ... ),
			'timezones'      => self::timezones( ... ),
			'image_sizes'    => self::image_sizes( ... ),
			'page_templates' => self::page_templates( ... ),
			'cron_schedules' => self::cron_schedules( ... ),
			'countries'      => self::countries( ... ),
			'currencies'     => self::currencies( ... ),
		];

		foreach ( $built_in as $name => $resolver ) {
			self::$resolvers[ $name ] ??= $resolver;
		}
	}

	/**
	 * The site's roles, role => translated name.
	 *
	 * Through translate_user_role() rather than as stored: role names are
	 * saved in English and translated on display, which is what the users
	 * screen does and what a role dropdown next to it should do too.
	 *
	 * @return array<string, string>
	 */
	private static function roles(): array {
		$options = [];

		foreach ( wp_roles()->get_names() as $role => $name ) {
			$options[ (string) $role ] = translate_user_role( (string) $name );
		}

		return $options;
	}

	/**
	 * Public post types, name => label.
	 *
	 * @return array<string, string>
	 */
	private static function post_types(): array {
		$options = [];

		foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $name => $type ) {
			$options[ (string) $name ] = (string) ( $type->label ?? $name );
		}

		return $options;
	}

	/**
	 * Public taxonomies, name => label.
	 *
	 * @return array<string, string>
	 */
	private static function taxonomies(): array {
		$options = [];

		foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $name => $taxonomy ) {
			$options[ (string) $name ] = (string) ( $taxonomy->label ?? $name );
		}

		return $options;
	}

	/**
	 * The site's pages, id => title, in tree order.
	 *
	 * get_pages() returns a flat list. Walked from the top so a child sits
	 * under its parent, with a dash per level of depth in front of the
	 * title, which is how core's page dropdowns show the hierarchy. Core's
	 * own walker pads with `&nbsp;`, which is markup, and every label here
	 * is escaped on the way out -- it would print as the six characters
	 * rather than as space.
	 *
	 * A page whose parent is not in the list -- the parent is a draft, say
	 * -- is listed at the top level along with anything under it. Core's
	 * walker does the same. Dropping it would lose a page from the list
	 * for a reason nobody looking at the list could see.
	 *
	 * @return array<int, string>
	 */
	private static function pages(): array {
		$pages = get_pages();

		if ( ! is_array( $pages ) ) {
			return [];
		}

		$by_parent = [];

		foreach ( $pages as $page ) {
			$by_parent[ (int) $page->post_parent ][] = $page;
		}

		$options = [];

		$add = static function ( int $parent_id, int $depth ) use ( &$add, &$by_parent, &$options ): void {
			$children = $by_parent[ $parent_id ] ?? [];

			unset( $by_parent[ $parent_id ] );

			foreach ( $children as $page ) {
				$title = (string) $page->post_title;

				$options[ (int) $page->ID ] = str_repeat( '— ', $depth )
					. ( '' === $title ? __( '(no title)', 'arraypress' ) : $title );

				$add( (int) $page->ID, $depth + 1 );
			}
		};

		$add( 0, 0 );

		// Whatever is left had a parent that was not in the list.
		foreach ( array_keys( $by_parent ) as $parent_id ) {
			$add( $parent_id, 0 );
		}

		return $options;
	}

	/**
	 * Every timezone, grouped by the continent in its identifier, plus UTC.
	 *
	 * Four hundred identifiers in one flat list is not a control anyone can
	 * use. Grouped by the part before the slash they come out as core's own
	 * timezone setting does: ten groups, the city as the label, and UTC on
	 * its own at the end because it belongs to no continent.
	 *
	 * @return array<string, string|array<string, string>>
	 */
	private static function timezones(): array {
		$continents = [
			'Africa'     => __( 'Africa', 'arraypress' ),
			'America'    => __( 'America', 'arraypress' ),
			'Antarctica' => __( 'Antarctica', 'arraypress' ),
			'Arctic'     => __( 'Arctic', 'arraypress' ),
			'Asia'       => __( 'Asia', 'arraypress' ),
			'Atlantic'   => __( 'Atlantic', 'arraypress' ),
			'Australia'  => __( 'Australia', 'arraypress' ),
			'Europe'     => __( 'Europe', 'arraypress' ),
			'Indian'     => __( 'Indian', 'arraypress' ),
			'Pacific'    => __( 'Pacific', 'arraypress' ),
		];

		$groups = [];

		foreach ( timezone_identifiers_list() as $zone ) {
			$slash = strpos( $zone, '/' );

			// UTC is the one identifier with no continent. Added last, below.
			if ( false === $slash ) {
				continue;
			}

			$continent = substr( $zone, 0, $slash );
			$label     = $continents[ $continent ] ?? $continent;

			// "America/Argentina/Buenos_Aires" reads as "Argentina - Buenos Aires".
			$groups[ $label ][ $zone ] = str_replace( [ '_', '/' ], [ ' ', ' - ' ], substr( $zone, $slash + 1 ) );
		}

		$groups['UTC'] = 'UTC';

		return $groups;
	}

	/**
	 * Registered image sizes, name => "name (width×height)", plus full.
	 *
	 * The dimensions are in the label because the names are not enough:
	 * `medium_large` means nothing until you know it is 768 wide, and a
	 * theme's `hero` could be anything. `full` is not a registered size --
	 * it is the original -- and is the one people most often want.
	 *
	 * @return array<string, string>
	 */
	private static function image_sizes(): array {
		$options = [];

		foreach ( wp_get_registered_image_subsizes() as $name => $size ) {
			$options[ (string) $name ] = sprintf(
				'%1$s (%2$d×%3$d)',
				$name,
				(int) ( $size['width'] ?? 0 ),
				(int) ( $size['height'] ?? 0 )
			);
		}

		$options['full'] = __( 'full (original size)', 'arraypress' );

		return $options;
	}

	/**
	 * The theme's page templates, file => name, with the default first.
	 *
	 * The theme reports only the templates it adds. "Default template" is
	 * what a page gets without one, and it is stored as `default`, so a
	 * list without it has no way to choose the ordinary case.
	 *
	 * @return array<string, string>
	 */
	private static function page_templates(): array {
		$options = [ 'default' => __( 'Default template', 'arraypress' ) ];

		foreach ( wp_get_theme()->get_page_templates() as $file => $name ) {
			$options[ (string) $file ] = (string) $name;
		}

		return $options;
	}

	/**
	 * Cron schedules, name => display name.
	 *
	 * @return array<string, string>
	 */
	private static function cron_schedules(): array {
		$options = [];

		foreach ( wp_get_schedules() as $name => $schedule ) {
			$options[ (string) $name ] = (string) ( $schedule['display'] ?? $name );
		}

		return $options;
	}

	/**
	 * Every country, code => name, from wp-countries.
	 *
	 * Grouped by continent when the field says `continents => true`: seven
	 * groups of thirty-odd is easier to find a country in than one list of
	 * two hundred and fifty, and a shipping-zone field wants the groups
	 * visible.
	 *
	 * @param Field|null $field The field asking, for its `continents` setting.
	 *
	 * @return array<string, string|array<string, string>>
	 */
	private static function countries( ?Field $field ): array {
		if ( ! self::available( Countries::class, 'arraypress/wp-countries' ) ) {
			return [];
		}

		if ( null === $field || ! (bool) $field->get( 'continents', false ) ) {
			return Countries::all();
		}

		$groups = [];

		foreach ( Countries::get_continent_codes() as $code => $name ) {
			$countries = Countries::get_by_continent( (string) $code );

			if ( [] === $countries ) {
				continue;
			}

			$groups[ (string) ( Countries::get_continent_name( (string) $code ) ?? $name ) ] = $countries;
		}

		return $groups;
	}

	/**
	 * Every currency, CODE => "CODE - Name (symbol)", from wp-money.
	 *
	 * @return array<string, string>
	 */
	private static function currencies(): array {
		if ( ! self::available( Currencies::class, 'arraypress/wp-money' ) ) {
			return [];
		}

		return Currencies::options();
	}

	/**
	 * Whether a sibling library's class is here, saying so once if not.
	 *
	 * Once, because the preset is asked for on every render and every save,
	 * and the same line a hundred times in the debug log is a line nobody
	 * reads.
	 *
	 * @param string $class_name The class the preset needs.
	 * @param string $package    The Composer package that provides it.
	 *
	 * @return bool
	 */
	private static function available( string $class_name, string $package ): bool {
		if ( class_exists( $class_name ) ) {
			return true;
		}

		if ( ! isset( self::$warned[ $package ] ) ) {
			self::$warned[ $package ] = true;

			self::complain(
				sprintf(
					/* translators: 1: class name, 2: composer package name */
					__( 'The preset needs %1$s, which is not installed. Run: composer require %2$s', 'arraypress' ),
					$class_name,
					$package
				)
			);
		}

		return false;
	}

	/**
	 * Say so, while someone is looking.
	 *
	 * Debug only, as with a configuration key nothing reads: it is a mistake
	 * in a consumer's setup, not a reason to interrupt a live site, and the
	 * field still renders.
	 *
	 * @param string $message What went wrong.
	 *
	 * @return void
	 */
	private static function complain( string $message ): void {
		if ( ! function_exists( '_doing_it_wrong' ) ) {
			return;
		}

		_doing_it_wrong( self::class . '::options', esc_html( $message ), '1.2.0' );
	}
}
