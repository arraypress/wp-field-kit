<?php
/**
 * Validation Rules
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.2.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Support;

use ArrayPress\FieldKit\Field;

/**
 * What a field's `validate` key means, and what to say when it is not met.
 *
 * Sanitizing and validating answer different questions, and the kit used to
 * answer only the first. A sanitizer coerces: a number outside its range is
 * clamped, a malformed email becomes an empty string, and either way
 * something is stored and the person who typed it is told nothing. That is
 * right for a value that arrived over HTTP from nowhere in particular and
 * wrong for one somebody just entered — they meant something, and a silent
 * correction is how a setting comes to hold a value nobody chose.
 *
 * So a field may say what it accepts. A rule name covers the common shapes;
 * a callable covers everything else, and answers true or with the message to
 * show. Both are resolved here, once, so a type that checks each item — tags,
 * list — and one that checks the whole value reach the same verdict for the
 * same rule.
 */
final class Rules {

	/**
	 * The rules a field may name.
	 *
	 * @var string[]
	 */
	public const NAMES = [ 'email', 'url', 'numeric', 'integer', 'slug', 'alphanumeric' ];

	/**
	 * How many failing items a per-item message names.
	 *
	 * Enough to see what went wrong; the count says how many more there are.
	 * A message listing forty bad tags is not a message anybody reads.
	 *
	 * @var int
	 */
	private const SHOWN = 3;

	/**
	 * The longest an item is echoed back at, in characters.
	 *
	 * The message is escaped when rendered, so this is about length rather
	 * than safety: a pasted paragraph that failed to be an email address
	 * should not come back as the whole paragraph.
	 *
	 * @var int
	 */
	private const ITEM_LENGTH = 30;

	/**
	 * Check one value against a rule.
	 *
	 * @param string|callable $rule  A rule name, or a callable given the value
	 *                               and the field. It returns true when the
	 *                               value is acceptable, otherwise a message
	 *                               as a string or a WP_Error.
	 * @param mixed           $value The sanitized value.
	 * @param Field           $field The field.
	 *
	 * @return string An empty string when the value passes, otherwise the message.
	 */
	public static function check( string|callable $rule, mixed $value, Field $field ): string {
		if ( ! self::is_known( $rule, $field ) ) {
			return '';
		}

		return self::verdict( $rule, $value, $field );
	}

	/**
	 * Check every item of a list against a rule.
	 *
	 * One message for the field rather than one per item: the control is a
	 * single input with a single place to show an error, and what somebody
	 * needs in order to fix it is how many failed and which ones.
	 *
	 * @param string|callable   $rule  A rule name or a callable, as for check().
	 * @param array<int, mixed> $items The sanitized items.
	 * @param Field             $field The field.
	 *
	 * @return string An empty string when every item passes, otherwise the message.
	 */
	public static function check_each( string|callable $rule, array $items, Field $field ): string {
		if ( ! self::is_known( $rule, $field ) ) {
			return '';
		}

		$failed = [];

		foreach ( $items as $item ) {
			if ( '' !== self::verdict( $rule, $item, $field ) ) {
				$failed[] = $item;
			}
		}

		if ( [] === $failed ) {
			return '';
		}

		return self::item_message( self::is_named( $rule ) ? $rule : '', count( $failed ), self::offenders( $failed ) );
	}

	/**
	 * What to call a field in a message.
	 *
	 * The label, which is what the person reading the message sees above
	 * the control. A field with no label is not called by its key — `api_key`
	 * is a name for code, not for a sentence.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public static function name( Field $field ): string {
		$label = $field->label();

		return '' === $label ? __( 'This field', 'arraypress' ) : $label;
	}

	/**
	 * Whether a rule is one of the named ones.
	 *
	 * Asked before is_callable(), so a rule name that happens to match a
	 * global function keeps meaning the rule.
	 *
	 * @param string|callable $rule The rule.
	 *
	 * @return bool
	 */
	private static function is_named( string|callable $rule ): bool {
		return is_string( $rule ) && in_array( $rule, self::NAMES, true );
	}

	/**
	 * Whether a rule can be applied at all, saying so when it cannot.
	 *
	 * An unknown name is a configuration mistake and not a reason to refuse
	 * a save, so the value is treated as acceptable — but under WP_DEBUG the
	 * mistake is reported, or a misspelt rule would be a rule that silently
	 * accepts everything.
	 *
	 * @param string|callable $rule  The rule.
	 * @param Field           $field The field, for the message.
	 *
	 * @return bool
	 */
	private static function is_known( string|callable $rule, Field $field ): bool {
		if ( self::is_named( $rule ) || is_callable( $rule ) ) {
			return true;
		}

		_doing_it_wrong(
			__METHOD__,
			sprintf(
				/* translators: 1: field key, 2: the rule as configured, 3: comma-separated list of rule names */
				esc_html__( 'The field "%1$s" names a validation rule "%2$s" that does not exist, so nothing was checked. The rules are: %3$s.', 'arraypress' ),
				esc_html( $field->key() ),
				esc_html( (string) $rule ),
				esc_html( implode( ', ', self::NAMES ) )
			),
			'1.2.0'
		);

		return false;
	}

	/**
	 * The verdict on one value from a rule already known to be applicable.
	 *
	 * A callable's answer is read generously. True, or nothing at all, is a
	 * pass: a callback written as a bare `if` that forgets to return on the
	 * happy path should not reject every value. False is a rejection without
	 * a reason, and gets the generic message rather than being read as a
	 * pass — a rule that returns false meant no.
	 *
	 * @param string|callable $rule  The rule.
	 * @param mixed           $value The sanitized value.
	 * @param Field           $field The field.
	 *
	 * @return string
	 */
	private static function verdict( string|callable $rule, mixed $value, Field $field ): string {
		if ( self::is_named( $rule ) ) {
			return self::passes( $rule, $value ) ? '' : self::message( $rule, $field );
		}

		$result = $rule( $value, $field );

		if ( true === $result || null === $result ) {
			return '';
		}

		if ( is_string( $result ) ) {
			return $result;
		}

		if ( is_wp_error( $result ) ) {
			return (string) $result->get_error_message();
		}

		return self::message( '', $field );
	}

	/**
	 * Whether a value satisfies a named rule.
	 *
	 * Everything is judged as text, because everything arrives as text: a
	 * number field's sanitized integer is checked the same way as the string
	 * "42" typed into a text field, and both are numeric.
	 *
	 * @param string $rule  A rule name.
	 * @param mixed  $value The sanitized value.
	 *
	 * @return bool
	 */
	private static function passes( string $rule, mixed $value ): bool {
		$text = is_scalar( $value ) ? (string) $value : '';

		return match ( $rule ) {
			'email'        => false !== is_email( $text ),
			'url'          => self::is_url( $text ),
			'numeric'      => is_numeric( $text ),
			'integer'      => 1 === preg_match( '/^-?[0-9]+$/', $text ),
			'slug'         => '' !== $text && sanitize_title( $text ) === $text,
			'alphanumeric' => 1 === preg_match( '/^[A-Za-z0-9]+$/', $text ),
			default        => true,
		};
	}

	/**
	 * Whether text is a web address: an http or https scheme and a host.
	 *
	 * Not `wp_http_validate_url()`. That one guards the HTTP API against
	 * being pointed at the server's own network, so it refuses localhost and
	 * every private address — and a URL a person types into a settings
	 * field may perfectly well be one of those. What is being asked here is
	 * only whether the text is a URL at all.
	 *
	 * @param string $text The value as text.
	 *
	 * @return bool
	 */
	private static function is_url( string $text ): bool {
		if ( false === filter_var( $text, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$scheme = strtolower( (string) parse_url( $text, PHP_URL_SCHEME ) );
		$host   = (string) parse_url( $text, PHP_URL_HOST );

		return in_array( $scheme, [ 'http', 'https' ], true ) && '' !== $host;
	}

	/**
	 * The message for a whole value that failed a named rule.
	 *
	 * Spelled out per rule rather than assembled from parts, so each is a
	 * sentence a translator can translate as one.
	 *
	 * @param string $rule  A rule name, or an empty string for the generic message.
	 * @param Field  $field The field.
	 *
	 * @return string
	 */
	private static function message( string $rule, Field $field ): string {
		$name = self::name( $field );

		return match ( $rule ) {
			'email'        => sprintf(
				/* translators: %s: field label */
				__( '%s must be an email address.', 'arraypress' ),
				$name
			),
			'url'          => sprintf(
				/* translators: %s: field label */
				__( '%s must be a web address starting with http:// or https://.', 'arraypress' ),
				$name
			),
			'numeric'      => sprintf(
				/* translators: %s: field label */
				__( '%s must be a number.', 'arraypress' ),
				$name
			),
			'integer'      => sprintf(
				/* translators: %s: field label */
				__( '%s must be a whole number.', 'arraypress' ),
				$name
			),
			'slug'         => sprintf(
				/* translators: %s: field label */
				__( '%s may contain only lowercase letters, numbers and hyphens.', 'arraypress' ),
				$name
			),
			'alphanumeric' => sprintf(
				/* translators: %s: field label */
				__( '%s may contain only letters and numbers.', 'arraypress' ),
				$name
			),
			default        => sprintf(
				/* translators: %s: field label */
				__( '%s is not valid.', 'arraypress' ),
				$name
			),
		};
	}

	/**
	 * The message for a list in which some items failed.
	 *
	 * @param string $rule      A rule name, or an empty string for a callable.
	 * @param int    $count     How many failed.
	 * @param string $offenders The first few, already shortened and joined.
	 *
	 * @return string
	 */
	private static function item_message( string $rule, int $count, string $offenders ): string {
		$text = match ( $rule ) {
			'email'        =>
				/* translators: 1: how many items failed, 2: comma-separated list of the first few */
				_n( '%1$d of these is not an email address: %2$s', '%1$d of these are not email addresses: %2$s', $count, 'arraypress' ),
			'url'          =>
				/* translators: 1: how many items failed, 2: comma-separated list of the first few */
				_n( '%1$d of these is not a web address: %2$s', '%1$d of these are not web addresses: %2$s', $count, 'arraypress' ),
			'numeric'      =>
				/* translators: 1: how many items failed, 2: comma-separated list of the first few */
				_n( '%1$d of these is not a number: %2$s', '%1$d of these are not numbers: %2$s', $count, 'arraypress' ),
			'integer'      =>
				/* translators: 1: how many items failed, 2: comma-separated list of the first few */
				_n( '%1$d of these is not a whole number: %2$s', '%1$d of these are not whole numbers: %2$s', $count, 'arraypress' ),
			'slug'         =>
				/* translators: 1: how many items failed, 2: comma-separated list of the first few */
				_n( '%1$d of these is not a slug: %2$s', '%1$d of these are not slugs: %2$s', $count, 'arraypress' ),
			'alphanumeric' =>
				/* translators: 1: how many items failed, 2: comma-separated list of the first few */
				_n( '%1$d of these is not letters and numbers only: %2$s', '%1$d of these are not letters and numbers only: %2$s', $count, 'arraypress' ),
			default        =>
				/* translators: 1: how many items failed, 2: comma-separated list of the first few */
				_n( '%1$d of these is not valid: %2$s', '%1$d of these are not valid: %2$s', $count, 'arraypress' ),
		};

		return sprintf( $text, $count, $offenders );
	}

	/**
	 * The first few failing items, shortened and joined.
	 *
	 * @param array<int, mixed> $failed Every item that failed.
	 *
	 * @return string
	 */
	private static function offenders( array $failed ): string {
		$shown = [];

		foreach ( array_slice( $failed, 0, self::SHOWN ) as $item ) {
			$text = is_scalar( $item ) ? (string) $item : '';

			$shown[] = mb_strlen( $text ) > self::ITEM_LENGTH
				? mb_substr( $text, 0, self::ITEM_LENGTH - 1 ) . '…'
				: $text;
		}

		$list = implode( ', ', $shown );

		return count( $failed ) > self::SHOWN ? $list . ', …' : $list;
	}
}
