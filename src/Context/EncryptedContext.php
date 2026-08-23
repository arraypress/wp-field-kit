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
final class EncryptedContext implements Context, Flushable {

	/**
	 * Marker identifying a value this class wrote.
	 */
	private const PREFIX = 'fkenc:';

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

		if ( ! $this->applies( $field ) || ! is_string( $value ) ) {
			return $value;
		}

		return $this->decrypt( $value ) ?? '';
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
		// A value already carrying the marker is stored as it is. Anything
		// that writes the option back wholesale — a reset, an import, a plain
		// update_option() — hands back what was read from the store, and
		// encrypting that a second time leaves a value that decrypts to
		// ciphertext and reads as nonsense.
		if ( $this->applies( $field ) && is_string( $value ) && '' !== $value
			&& ! str_starts_with( $value, self::PREFIX ) ) {
			$encrypted = $this->encrypt( $value );

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
	 * @param string $value Plain value.
	 *
	 * @return string|null Null when encryption failed.
	 */
	private function encrypt( string $value ): ?string {
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

		return self::PREFIX . base64_encode( $nonce . $tag . $cipher );
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
		if ( ! str_starts_with( $value, self::PREFIX ) ) {
			return $value;
		}

		$key = self::key();
		$raw = base64_decode( substr( $value, strlen( self::PREFIX ) ), true );

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
}
