<?php
/**
 * Flushable Contract
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Contracts;

/**
 * A context that batches its writes and needs telling when they are done.
 *
 * An option holds every field in one row, so writing per field would be one
 * database write per control on the page. Such a context stages instead, and
 * the field set flushes once it has handled every field.
 *
 * This exists as a contract rather than a class check because a context is
 * routinely wrapped — in encryption, in a constant override — and a wrapped
 * store that never gets flushed stages a whole page of values and silently
 * discards them at the end of the request.
 */
interface Flushable {

	/**
	 * Write everything staged.
	 *
	 * Must be safe to call when nothing is staged.
	 *
	 * @return void
	 */
	public function save(): void;
}
