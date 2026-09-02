<?php
/**
 * WordPress stubs for the test suite.
 *
 * This is a library, not a plugin: there is no WordPress to load. The
 * WordPress functions the rendering and sanitizing paths touch are stubbed
 * here, kept as close to core's real behaviour as the tests depend on and no
 * closer.
 *
 * Separate from the bootstrap so a library that builds on the kit can require
 * exactly these and add its own on top. Two copies of a stub drift, and a
 * stub that no longer behaves like core is a test that proves nothing.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/*
 * The salts a real install defines. Without them EncryptedContext reports
 * itself unavailable and its tests skip — which is the one path that must not
 * go unexercised, since it is what keeps a credential out of a database dump.
 */
foreach ( [ 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'LOGGED_IN_SALT' ] as $fk_salt ) {
	if ( ! defined( $fk_salt ) ) {
		define( $fk_salt, 'test-salt-' . strtolower( $fk_salt ) . '-0123456789abcdef' );
	}
}


if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $text ) {
		return strip_tags( (string) $text, '<a><p><strong><em><br><ul><ol><li><code>' );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/*
	 * Core drops a script or style element's *content* as well as its tags,
	 * which strip_tags() alone does not — so the stub used to leave
	 * "Acme alert(1)" where WordPress leaves "Acme", and a test asserting the
	 * stub encoded the wrong expectation in the dangerous direction.
	 */
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text ) ?? '';
		$text = strip_tags( $text );

		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text ) ?? '';
		}

		return trim( $text );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( (string) $str ) ) ?? '' );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( wp_strip_all_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( trim( (string) $email ), FILTER_VALIDATE_EMAIL ) ?: '';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) ?? '' );
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ? $color : '';
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

/*
 * Added when PageHeaderTest first exercised the header and found it calling a
 * function no test had ever reached. It works on a real site because
 * WordPress defines it — which is exactly why the gap was invisible.
 */
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

/*
 * The echoing pair. Missing for the same reason the two above were: nothing
 * in this package calls them, and a consumer that did only found out when a
 * test finally rendered the component doing it.
 */
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr__( $text, $domain );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html__( $text, $domain );
	}
}

if ( ! function_exists( 'wp_editor' ) ) {
	function wp_editor( $content, $editor_id, $settings = [] ) {
		printf(
			'<textarea id="%s" name="%s" rows="%d">%s</textarea>',
			htmlspecialchars( (string) $editor_id, ENT_QUOTES ),
			htmlspecialchars( (string) ( $settings['textarea_name'] ?? $editor_id ), ENT_QUOTES ),
			(int) ( $settings['textarea_rows'] ?? 10 ),
			htmlspecialchars( (string) $content, ENT_QUOTES )
		);
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '', $scheme = 'admin' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $path = '', $plugin = '' ) {
		return 'https://example.test/wp-content/plugins/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'testnonce';
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post = 0 ) {
		$attachment = $GLOBALS['fk_attachments'][ (int) $post ] ?? null;

		return null === $attachment ? 'Item ' . (int) $post : (string) ( $attachment['name'] ?? 'Item ' . (int) $post );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		return $single ? '' : [];
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $user_id ) {
		return null;
	}
}

if ( ! function_exists( 'get_term' ) ) {
	function get_term( $term, $taxonomy = '' ) {
		return null;
	}
}

/*
 * The smallest WP_Error a validation callback can return: a message, and the
 * function that tells it apart from a string. Nothing here reads codes or
 * data, so the stub carries neither.
 */
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public string $message;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->message = (string) $message;
		}

		public function get_error_message( $code = '' ) {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

/*
 * Validation rules. is_email() and sanitize_title() are approximated only as
 * far as the rule tests depend on: an address without a domain is refused,
 * and a title with capitals or spaces comes back changed.
 */
if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email, $deprecated = false ) {
		return filter_var( (string) $email, FILTER_VALIDATE_EMAIL ) ? (string) $email : false;
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title, $fallback_title = '', $context = 'save' ) {
		$title = strtolower( strip_tags( (string) $title ) );
		$title = preg_replace( '/[^a-z0-9 _-]/', '', $title ) ?? '';
		$title = preg_replace( '/[\s-]+/', '-', $title ) ?? '';

		return trim( $title, '-' );
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? $single : $plural;
	}
}

/**
 * An attachment a test has described, if it described one.
 *
 * Registered in $GLOBALS rather than passed, because these are functions the
 * code under test calls directly. Absent, every stub behaves as it always
 * did -- an image with a thumbnail -- so nothing that predates this changes.
 */
if ( ! function_exists( 'fk_test_attachment' ) ) {
	function fk_test_attachment( $attachment_id ) {
		return $GLOBALS['fk_attachments'][ (int) $attachment_id ] ?? null;
	}
}

if ( ! function_exists( 'wp_get_attachment_image' ) ) {
	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = '' ) {
		$attachment = fk_test_attachment( $attachment_id );

		// What core does for anything that is not an image, and the reason
		// the gallery needs something else to draw.
		if ( null !== $attachment && 0 !== strpos( (string) ( $attachment['mime'] ?? 'image/png' ), 'image/' ) ) {
			return '';
		}

		return sprintf( '<img src="test.png" alt="%s" />', htmlspecialchars( (string) ( is_array( $attr ) ? ( $attr['alt'] ?? '' ) : '' ), ENT_QUOTES ) );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url, $protocols = null, $_context = 'display' ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES );
	}
}

if ( ! function_exists( 'get_post_mime_type' ) ) {
	function get_post_mime_type( $post = null ) {
		$attachment = fk_test_attachment( $post );

		return null === $attachment ? 'image/png' : (string) ( $attachment['mime'] ?? 'image/png' );
	}
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( $attachment_id ) {
		$attachment = fk_test_attachment( $attachment_id );

		return null === $attachment
			? 'https://example.test/uploads/file.pdf'
			: (string) ( $attachment['url'] ?? '' );
	}
}

if ( ! function_exists( 'wp_oembed_get' ) ) {
	function wp_oembed_get( $url, $args = '' ) {
		return '<iframe src="' . htmlspecialchars( (string) $url, ENT_QUOTES ) . '"></iframe>';
	}
}

if ( ! function_exists( 'wp_slash' ) ) {
	function wp_slash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}

if ( ! function_exists( 'get_metadata' ) ) {
	function get_metadata( $meta_type, $object_id, $meta_key = '', $single = false ) {
		return $GLOBALS['fk_meta'][ $meta_type ][ $object_id ][ $meta_key ] ?? ( $single ? '' : [] );
	}
}

if ( ! function_exists( 'update_metadata' ) ) {
	function update_metadata( $meta_type, $object_id, $meta_key, $meta_value, $prev = '' ) {
		$GLOBALS['fk_meta'][ $meta_type ][ $object_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'delete_metadata' ) ) {
	function delete_metadata( $meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false ) {
		unset( $GLOBALS['fk_meta'][ $meta_type ][ $object_id ][ $meta_key ] );
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default_value = false ) {
		return $GLOBALS['fk_options'][ $option ] ?? $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['fk_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( '_doing_it_wrong' ) ) {
	function _doing_it_wrong( $function_name, $message, $version ) {
		$GLOBALS['fk_doing_it_wrong'][] = $message;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['fk_actions'][ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'remove_action' ) ) {
	function remove_action( $hook, $callback, $priority = 10 ) {
		if ( ! isset( $GLOBALS['fk_actions'][ $hook ] ) ) {
			return false;
		}

		$GLOBALS['fk_actions'][ $hook ] = array_values(
			array_filter(
				$GLOBALS['fk_actions'][ $hook ],
				static fn( $registered ) => $registered !== $callback
			)
		);

		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		foreach ( $GLOBALS['fk_actions'][ $hook ] ?? [] as $callback ) {
			$callback( ...$args );
		}
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['fk_filters'][ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args = [], $override = false ) {
		$GLOBALS['fk_routes'][] = $namespace . $route;
		return true;
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		return $GLOBALS['fk_user_can'][ $capability ] ?? true;
	}
}

if ( ! function_exists( 'rest_authorization_required_code' ) ) {
	function rest_authorization_required_code() {
		return 401;
	}
}

if ( ! function_exists( 'wpautop' ) ) {
	function wpautop( $text, $br = true ) {
		return '<p>' . (string) $text . '</p>';
	}
}

if ( ! function_exists( 'register_meta' ) ) {
	function register_meta( $object_type, $meta_key, $args = [] ) {
		$GLOBALS['fk_meta_registry'][ $object_type ][ $meta_key ] = $args;

		return true;
	}
}

/*
 * Front-end context, for Value.
 *
 * Driven by globals rather than arguments so a test can put the request into
 * a state — "viewing post 5" — and then ask the code under test what it makes
 * of it, which is how the real functions behave.
 */

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) {
		return $GLOBALS['fk_permalinks'][ (int) $post ] ?? false;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
}

if ( ! function_exists( 'is_category' ) ) {
	function is_category( $category = '' ) {
		return 'category' === ( $GLOBALS['fk_archive'] ?? '' );
	}
}

if ( ! function_exists( 'is_tag' ) ) {
	function is_tag( $tag = '' ) {
		return 'tag' === ( $GLOBALS['fk_archive'] ?? '' );
	}
}

if ( ! function_exists( 'is_tax' ) ) {
	function is_tax( $taxonomy = '', $term = '' ) {
		return 'tax' === ( $GLOBALS['fk_archive'] ?? '' );
	}
}

if ( ! function_exists( 'is_author' ) ) {
	function is_author( $author = '' ) {
		return 'author' === ( $GLOBALS['fk_archive'] ?? '' );
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $post_types = '' ) {
		return (bool) ( $GLOBALS['fk_is_singular'] ?? false );
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() {
		return (int) ( $GLOBALS['fk_queried_object'] ?? 0 );
	}
}

/*
 * wp_kses, for the tooltip.
 *
 * Not an approximation of core's parser — a stub cannot be — but enough to
 * assert the contract that matters here: markup outside the allow-list does
 * not survive.
 */

if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( $content, $allowed = [], $protocols = [] ) {
		$tags = implode( '', array_map( static fn( $tag ) => '<' . $tag . '>', array_keys( (array) $allowed ) ) );

		return strip_tags( (string) $content, $tags );
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals );
	}
}

/*
 * The site's own gradient presets. Core defines a dozen and a theme adds its
 * own; both arrive grouped by origin.
 */
if ( ! function_exists( 'wp_get_global_settings' ) ) {
	function wp_get_global_settings( $path = [], $context = [] ) {
		return $GLOBALS['fk_global_settings'] ?? [];
	}
}

if ( ! function_exists( 'wp_get_nav_menus' ) ) {
	/**
	 * The site's navigation menus.
	 *
	 * No return type, because core has none: it returns
	 * apply_filters( 'wp_get_nav_menus', get_terms( ... ) ), so a plugin can
	 * make it return anything, and get_terms() itself returns a WP_Error for
	 * a taxonomy that is not registered. A stub that promises an array cannot
	 * exercise the guard that exists for exactly that.
	 *
	 * @param array $args Unused.
	 *
	 * @return mixed
	 */
	function wp_get_nav_menus( array $args = [] ) {
		return $GLOBALS['fk_nav_menus'] ?? [];
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	/**
	 * Copied from core: the value is printed into a class attribute.
	 *
	 * @param string $class_name Class name.
	 * @param string $fallback   Fallback.
	 *
	 * @return string
	 */
	function sanitize_html_class( $class_name, $fallback = '' ): string {
		$sanitized = preg_replace( '/%[a-f0-9]{2}/i', '', (string) $class_name );
		$sanitized = preg_replace( '/[^A-Za-z0-9_-]/', '', $sanitized );

		if ( '' === $sanitized && $fallback ) {
			return sanitize_html_class( $fallback );
		}

		return $sanitized;
	}
}

/*
 * What the option presets read. Each one is driven by a global a test sets,
 * and answers empty otherwise, so a preset asked for on a bare site is a
 * preset with nothing in it rather than a fatal.
 */

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['fk_filters'][ $hook ] ?? [] as $callback ) {
			$value = $callback( $value, ...$args );
		}

		return $value;
	}
}

if ( ! function_exists( 'wp_roles' ) ) {
	/**
	 * The roles object, reduced to the one method the presets call.
	 *
	 * @return object
	 */
	function wp_roles() {
		return new class() {
			public function get_names(): array {
				return $GLOBALS['fk_roles'] ?? [];
			}
		};
	}
}

if ( ! function_exists( 'translate_user_role' ) ) {
	function translate_user_role( $name, $domain = 'default' ) {
		return $name;
	}
}

/**
 * Registered objects -- post types or taxonomies -- matching a query.
 *
 * Core's get_post_types() and get_taxonomies() both take an array of
 * property => value and return the objects whose properties match every one,
 * as names or as the objects themselves. That filtering is what the presets
 * lean on to say "public only", so the stub does it rather than returning
 * the lot.
 *
 * @param array  $objects Registered objects, keyed by name.
 * @param array  $args    Property => value to match.
 * @param string $output  'names' or 'objects'.
 *
 * @return array
 */
if ( ! function_exists( 'fk_test_matching_objects' ) ) {
	function fk_test_matching_objects( array $objects, array $args, string $output ): array {
		$matching = array_filter(
			$objects,
			static function ( $object ) use ( $args ): bool {
				foreach ( $args as $property => $value ) {
					if ( ( $object->$property ?? null ) !== $value ) {
						return false;
					}
				}

				return true;
			}
		);

		return 'objects' === $output ? $matching : array_keys( $matching );
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	function get_post_types( $args = [], $output = 'names', $operator = 'and' ) {
		return fk_test_matching_objects( $GLOBALS['fk_post_types'] ?? [], (array) $args, (string) $output );
	}
}

if ( ! function_exists( 'get_taxonomies' ) ) {
	function get_taxonomies( $args = [], $output = 'names', $operator = 'and' ) {
		return fk_test_matching_objects( $GLOBALS['fk_taxonomies'] ?? [], (array) $args, (string) $output );
	}
}

if ( ! function_exists( 'get_pages' ) ) {
	/**
	 * No return type, as with wp_get_nav_menus(): core's returns false as
	 * well as an array, and the guard for that has to be reachable.
	 *
	 * @param array $args Unused.
	 *
	 * @return mixed
	 */
	function get_pages( $args = [] ) {
		return $GLOBALS['fk_pages'] ?? [];
	}
}

if ( ! function_exists( 'wp_get_registered_image_subsizes' ) ) {
	function wp_get_registered_image_subsizes() {
		return $GLOBALS['fk_image_sizes'] ?? [];
	}
}

if ( ! function_exists( 'wp_get_theme' ) ) {
	/**
	 * The theme object, reduced to the one method the presets call.
	 *
	 * @return object
	 */
	function wp_get_theme() {
		return new class() {
			public function get_page_templates( $post = null, $post_type = 'page' ): array {
				return $GLOBALS['fk_page_templates'] ?? [];
			}
		};
	}
}

if ( ! function_exists( 'wp_get_schedules' ) ) {
	function wp_get_schedules() {
		return $GLOBALS['fk_schedules'] ?? [];
	}
}

/*
 * wp-money is suggested rather than required, so the currency type is tested
 * against a stand-in with a handful of currencies in it. A namespaced class
 * cannot live in this file of global functions, so it sits beside it.
 */
if ( ! class_exists( 'ArrayPress\Money\Currencies' ) ) {
	require_once __DIR__ . '/stubs/Currencies.php';
}
