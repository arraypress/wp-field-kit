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

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $str ) ) ?? '' );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( (string) $str ) );
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
		return 'Item ' . (int) $post;
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

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false;
	}
}

if ( ! function_exists( 'wp_get_attachment_image' ) ) {
	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = '' ) {
		return sprintf( '<img src="test.png" alt="%s" />', htmlspecialchars( (string) ( is_array( $attr ) ? ( $attr['alt'] ?? '' ) : '' ), ENT_QUOTES ) );
	}
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( $attachment_id ) {
		return 'https://example.test/uploads/file.pdf';
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

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		return true;
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
