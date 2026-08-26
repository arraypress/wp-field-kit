<?php
/**
 * Nav Menu Field Type
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.1.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * Class NavMenuType
 *
 * Choose one of the site's navigation menus.
 *
 * A select rather than a searching control: a site has a handful of menus, and
 * a search box for six things is worse than a list of six things.
 *
 * The stored value is the menu's term id, so it survives a rename. Set
 * `return` to 'slug' to store the slug instead, which is what you want when
 * the value is going into an exported template.
 */
final class NavMenuType extends SelectType {

	/**
	 * Options come from the site's registered menus.
	 *
	 * @return array<string, mixed>
	 * @since 1.1.0
	 */
	public function defaults(): array {
		return array_merge(
			parent::defaults(),
			[ 'options' => [ self::class, 'menus' ] ]
		);
	}

	/**
	 * The site's navigation menus, as value => label.
	 *
	 * Static so it can be named as a callable in defaults() without holding a
	 * reference to the instance.
	 *
	 * @param Field $field The field, for its `return` setting.
	 *
	 * @return array<string, string>
	 * @since 1.1.0
	 */
	public static function menus( Field $field ): array {
		$menus = wp_get_nav_menus();

		if ( ! is_array( $menus ) ) {
			return [];
		}

		$by_slug = 'slug' === (string) $field->get( 'return', 'id' );
		$options = [];

		foreach ( $menus as $menu ) {
			$key = $by_slug ? (string) $menu->slug : (string) $menu->term_id;

			$options[ $key ] = $menu->name;
		}

		return $options;
	}

	/**
	 * Configuration this type reads.
	 *
	 * @return array
	 * @since 1.1.0
	 */
	public function config_keys(): array {
		return array_merge( parent::config_keys(), [ 'return' ] );
	}
}
