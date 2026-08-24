<?php
/**
 * Encrypting Context Decorator
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Context;

use ArrayPress\FieldKit\Contracts\Context;
use ArrayPress\FieldKit\Contracts\Flushable;
use ArrayPress\FieldKit\Contracts\Registrable;
use ArrayPress\FieldKit\Field;

/**
 * Encrypts fields marked `encrypted` at rest, whatever they are stored in.
 *
 * A decorator rather than a feature of one context: an API key is no less
 * worth encrypting because it lives in post meta rather than an option, and
 * the settings library having its own copy of this was the reason it was only
 * ever available there.
 *
 * The key is derived from the site's own salts. That has a consequence worth
 * stating plainly: values encrypted on one site cannot be read on another,
 * and rotating salts makes existing values unreadable. That is the correct
 * trade for a credential — it is why a leaked database alone is not enough to
 * use one — but it means an encrypted field is not something to put in a
 * migration and expect to survive.
 */
final class EncryptedContext implements Context, Flushable, Registrable {

	/**
	 * Marker identifying a value this class wrote, before it encoded types.
	 *
	 * The payload is the plain string. Read only — nothing writes it now.
	 */
	private const PREFIX = 'fkenc:';

	/**
	 * Marker identifying a value whose payload is JSON.
	 *
	 * Versioned rather than reused, because the two cannot be told apart by
	 * looking: a v0 payload of `123` and a v1 payload of `123` decode to a
	 * string and an int respectively, and guessing would silently change the
	 * type of every stored number.
	 */
	private const PREFIX_JSON = 'fkenc:j:';

	/**
	 * The cipher. GCM authenticates as well as encrypts, so a tampered
	 * value fails to decrypt rather than decrypting to something else.
	 */
	private const CIPHER = 'aes-256-gcm';

	/**
	 * The context being decorated.
	 *
	 * @var Context
	 */
	private Context $inner;

	/**
	 * Construct.
	 *
	 * @param Context $inner The context to wrap.
	 */
	public function __construct( Context $inner ) {
		$this->inner = $inner;
	}

	/**
	 * Read, decrypting if this field is encrypted.
	 *
	 * @param int|string $object_id The object id.
	 * @param Field      $field     The field.
	 *
	 * @return mixed
	 */
	public function read( int|string $object_id, Field $field ): mixed {
		$value = $this->inner->read( $object_id, $field );

		// Ciphertext is always a string, whatever went in.
		if ( ! $this->applies( $field ) || ! is_string( $value ) ) {
			return $value;
		}

		$json = str_starts_with( $value, self::PREFIX_JSON );
		$plain = $this->decrypt( $value );

		if ( null === $plain ) {
			return '';
		}

		if ( ! $json ) {
			return $plain;
		}

		$decoded = json_decode( $plain, true );

		// A payload that will not decode is not something to hand back as a
		// raw JSON string: that would put `{"a":1}` in a text field.
		return null === $decoded && 'null' !== $plain ? '' : $decoded;
	}

	/**
	 * Write, encrypting if this field is encrypted.
	 *
	 * @param int|string $object_id The object id.
	 * @param Field      $field     The field.
	 * @param mixed      $value     Sanitized value.
	 *
	 * @return void
	 */
	public function write( int|string $object_id, Field $field, mixed $value ): void {
		if ( $this->applies( $field ) && $this->worth_encrypting( $value ) ) {
			// Every type, not only the ones that store a string. A group, a
			// repeater, a set of checkboxes — anything marked encrypted has
			// to be, and silently storing an array in the clear because it
			// was not a string is the worst possible answer: the field says
			// encrypted and the database says otherwise.
			$encoded = wp_json_encode( $value );

			$encrypted = false === $encoded ? null : $this->encrypt( self::PREFIX_JSON, $encoded );

			// A failed encryption must not fall through to storing the
			// plaintext: that is the one outcome worse than not saving.
			if ( null === $encrypted ) {
				return;
			}

			$value = $encrypted;
		}

		$this->inner->write( $object_id, $field, $value );
	}

	/**
	 * Whether a value needs encrypting on the way in.
	 *
	 * Two things are left alone. Emptiness, which reveals nothing and would
	 * otherwise make "no value" indistinguishable from "a value" by length.
	 * And anything already carrying a marker: everything that writes an
	 * option back wholesale — a reset, an import, a plain update_option() —
	 * hands back what it read, and encrypting that again leaves a value that
	 * decrypts to ciphertext.
	 *
	 * @param mixed $value The value about to be stored.
	 *
	 * @return bool
	 */
	private function worth_encrypting( mixed $value ): bool {
		if ( null === $value || '' === $value || [] === $value ) {
			return false;
		}

		return ! is_string( $value ) || ! str_starts_with( $value, self::PREFIX );
	}

	/**
	 * Remove a value.
	 *
	 * @param int|string $object_id The object id.
	 * @param Field      $field     The field.
	 *
	 * @return void
	 */
	public function delete( int|string $object_id, Field $field ): void {
		$this->inner->delete( $object_id, $field );
	}

	/**
	 * Whether this field asked to be encrypted, and whether we can.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	private function applies( Field $field ): bool {
		return (bool) $field->get( 'encrypted', false ) && self::available();
	}

	/**
	 * Whether encryption is possible in this environment.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return function_exists( 'openssl_encrypt' )
			&& in_array( self::CIPHER, (array) openssl_get_cipher_methods(), true )
			&& '' !== self::key();
	}

	/**
	 * The key, derived from the site's salts.
	 *
	 * @return string
	 */
	private static function key(): string {
		$salt = '';

		foreach ( [ 'LOGGED_IN_KEY', 'LOGGED_IN_SALT', 'AUTH_KEY', 'SECURE_AUTH_KEY' ] as $constant ) {
			if ( defined( $constant ) && '' !== (string) constant( $constant ) ) {
				$salt .= (string) constant( $constant );
			}
		}

		return '' === $salt ? '' : hash( 'sha256', $salt, true );
	}

	/**
	 * Encrypt a value.
	 *
	 * The nonce and the authentication tag travel with the ciphertext,
	 * because both are needed to decrypt and neither is a secret.
	 *
	 * @param string $prefix Marker to write it under.
	 * @param string $value  Plain value.
	 *
	 * @return string|null Null when encryption failed.
	 */
	private function encrypt( string $prefix, string $value ): ?string {
		$key = self::key();

		if ( '' === $key ) {
			return null;
		}

		$nonce  = openssl_random_pseudo_bytes( (int) openssl_cipher_iv_length( self::CIPHER ) );
		$tag    = '';
		$cipher = openssl_encrypt( $value, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag );

		if ( false === $cipher ) {
			return null;
		}

		return $prefix . base64_encode( $nonce . $tag . $cipher );
	}

	/**
	 * Decrypt a value.
	 *
	 * A value without the marker is returned as it is: a field that was
	 * marked encrypted after it already held something must not lose it.
	 *
	 * @param string $value Stored value.
	 *
	 * @return string|null Null when the value is unreadable.
	 */
	private function decrypt( string $value ): ?string {
		// Longest marker first: the JSON one begins with the legacy one, so
		// testing in the other order strips too little and the payload no
		// longer base64-decodes.
		$prefix = str_starts_with( $value, self::PREFIX_JSON )
			? self::PREFIX_JSON
			: ( str_starts_with( $value, self::PREFIX ) ? self::PREFIX : '' );

		if ( '' === $prefix ) {
			return $value;
		}

		$key = self::key();
		$raw = base64_decode( substr( $value, strlen( $prefix ) ), true );

		if ( '' === $key || false === $raw ) {
			return null;
		}

		$nonce_length = (int) openssl_cipher_iv_length( self::CIPHER );
		$nonce        = substr( $raw, 0, $nonce_length );
		$tag          = substr( $raw, $nonce_length, 16 );
		$cipher       = substr( $raw, $nonce_length + 16 );

		$plain = openssl_decrypt( $cipher, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag );

		return false === $plain ? null : $plain;
	}

	/**
	 * Flush the wrapped store.
	 *
	 * A decorator is what the field set holds, so if it did not pass this on
	 * an option-backed set behind one would stage every value and write none.
	 *
	 * @return void
	 */
	public function save(): void {
		if ( $this->inner instanceof Flushable ) {
			$this->inner->save();
		}
	}

	/**
	 * The kind of meta the wrapped store holds.
	 *
	 * A decorated store is still the store it decorates. Without this a
	 * settings page's encrypted field set would look unregistrable, which is
	 * true, and a term screen's would too, which is not.
	 *
	 * @return string
	 */
	public function meta_type(): string {
		return $this->inner instanceof Registrable ? $this->inner->meta_type() : '';
	}
}
