<?php
/**
 * Storage Context Contract
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Contracts;

use ArrayPress\FieldKit\Field;

/**
 * Where a set of fields is read from and written to.
 *
 * This is the only thing that differs between the five predecessor
 * libraries. They each carried a full copy of the rendering and sanitizing
 * machinery to wrap around get_post_meta() or get_option(); everything above
 * this interface is now shared, and a context is a few dozen lines.
 *
 * Contexts also own one trap nobody should have to rediscover: WordPress's
 * storage APIs disagree about slashing. update_metadata() calls wp_unslash()
 * on the value it is given, so the meta APIs expect slashed input, while
 * update_option() does not unslash and expects it clean. Sanitized values
 * arrive here unslashed, and each context re-slashes or does not according
 * to what its own API wants.
 */
interface Context {

	/**
	 * Read a field's stored value.
	 *
	 * @param int|string $object_id The post, term, user or option-set id.
	 * @param Field      $field     The field.
	 *
	 * @return mixed
	 */
	public function read( int|string $object_id, Field $field ): mixed;

	/**
	 * Write a field's sanitized value.
	 *
	 * @param int|string $object_id The post, term, user or option-set id.
	 * @param Field      $field     The field.
	 * @param mixed      $value     Sanitized, unslashed value.
	 *
	 * @return void
	 */
	public function write( int|string $object_id, Field $field, mixed $value ): void;

	/**
	 * Remove a field's stored value.
	 *
	 * @param int|string $object_id The post, term, user or option-set id.
	 * @param Field      $field     The field.
	 *
	 * @return void
	 */
	public function delete( int|string $object_id, Field $field ): void;
}
