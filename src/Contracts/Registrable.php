<?php
/**
 * Registrable Contract
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Contracts;

/**
 * A store whose keys can be declared to WordPress.
 *
 * The meta API has a registry — `register_meta()` — and an option does not,
 * in the sense that matters here: an option is declared with
 * `register_setting()` by whoever owns the settings page, one call for the
 * whole page rather than one per field.
 *
 * So this asks the store what kind of meta it holds rather than making the
 * caller say it a second time. The context already knows: it is the thing
 * calling `update_metadata()` with that same string. Two places naming
 * "term" is one place too many, and the one that drifts is never the one
 * being read.
 */
interface Registrable {

	/**
	 * The object type, as the meta API names it.
	 *
	 * @return string
	 */
	public function meta_type(): string;
}
