<?php
/**
 * Action Registry
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Actions;

use ArrayPress\FieldKit\Contracts\Action;

/**
 * The actions a field's button may run.
 */
final class Actions {

	/**
	 * The registry the endpoint resolves against.
	 *
	 * Shared for the same reason the search registry is: a field registers
	 * its action while a page renders, and the endpoint has to find it on a
	 * later request.
	 *
	 * @var self|null
	 */
	private static ?self $shared = null;

	/**
	 * Registered actions by name.
	 *
	 * @var array<string, Action>
	 */
	private array $actions = [];

	/**
	 * The shared registry.
	 *
	 * @return self
	 */
	public static function shared(): self {
		if ( null === self::$shared ) {
			self::$shared = new self();
		}

		return self::$shared;
	}

	/**
	 * Register an action, replacing any of the same name.
	 *
	 * @param Action $action The action.
	 *
	 * @return self
	 */
	public function register( Action $action ): self {
		$this->actions[ $action->name() ] = $action;

		return $this;
	}

	/**
	 * Whether a name resolves.
	 *
	 * @param string $name Action name.
	 *
	 * @return bool
	 */
	public function has( string $name ): bool {
		return isset( $this->actions[ $name ] );
	}

	/**
	 * Resolve an action by name.
	 *
	 * @param string $name Action name.
	 *
	 * @return Action|null
	 */
	public function get( string $name ): ?Action {
		return $this->actions[ $name ] ?? null;
	}

	/**
	 * Every registered action name.
	 *
	 * @return string[]
	 */
	public function names(): array {
		return array_keys( $this->actions );
	}
}
