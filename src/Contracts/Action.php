<?php
/**
 * Field Action Contract
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Contracts;

/**
 * Something a field's button can run.
 *
 * Every interactive button in the kit — activate a licence, send a test
 * email, preview one, or whatever a consumer wires up — goes through one
 * endpoint and one contract. The alternative is an endpoint per feature,
 * which is what the predecessor libraries grew and why the same idea existed
 * three times with three different permission checks.
 *
 * Handlers are resolved by name against a server-side registry, exactly as
 * search sources are: the name is the only thing that travels in the
 * request, so a request can only reach a handler someone registered.
 */
interface Action {

	/**
	 * The name a field refers to this action by.
	 *
	 * @return string
	 */
	public function name(): string;

	/**
	 * The capability required to run it.
	 *
	 * Declared per action rather than shared: activating a licence and
	 * previewing an email are not the same risk, and one blanket check for
	 * everything is how the wrong person ends up able to do the dangerous
	 * one.
	 *
	 * @return string
	 */
	public function capability(): string;

	/**
	 * Run it.
	 *
	 * @param array<string, mixed> $payload Data the field sent, already
	 *                                      sanitized.
	 *
	 * @return array{success: bool, message?: string, data?: array<string, mixed>}
	 */
	public function handle( array $payload ): array;
}
