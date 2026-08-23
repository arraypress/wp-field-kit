<?php
/**
 * Callback Action
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Actions;

use ArrayPress\FieldKit\Contracts\Action;
use Throwable;

/**
 * An action backed by a consumer's own callable.
 *
 * The callable is invoked with the sanitized payload and may return either
 * the full result shape or a bare string, which is taken as a success
 * message — returning a string is the obvious thing to do and is not worth a
 * support question.
 *
 * A throw is caught and reported as a failure rather than becoming a 500.
 * These run from a button on an admin screen, and a consumer's licence
 * server timing out should show a message, not an empty response the field
 * cannot explain.
 */
final class CallbackAction implements Action {

	/**
	 * The name a field refers to this action by.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * The consumer's callable.
	 *
	 * @var callable
	 */
	private $callback;

	/**
	 * The capability required to run it.
	 *
	 * @var string
	 */
	private string $capability;

	/**
	 * Construct.
	 *
	 * @param string   $name       Action name.
	 * @param callable $callback   The consumer's callable.
	 * @param string   $capability Capability required to run it.
	 */
	public function __construct( string $name, callable $callback, string $capability = 'manage_options' ) {
		$this->name       = $name;
		$this->callback   = $callback;
		$this->capability = $capability;
	}

	/**
	 * The name a field refers to this action by.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * The capability required to run it.
	 *
	 * @return string
	 */
	public function capability(): string {
		return $this->capability;
	}

	/**
	 * Run the callable.
	 *
	 * @param array<string, mixed> $payload Sanitized payload.
	 *
	 * @return array{success: bool, message?: string, data?: array<string, mixed>}
	 */
	public function handle( array $payload ): array {
		try {
			$result = ( $this->callback )( $payload );
		} catch ( Throwable $e ) {
			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

		if ( is_string( $result ) ) {
			return [
				'success' => true,
				'message' => $result,
			];
		}

		if ( ! is_array( $result ) ) {
			return [ 'success' => (bool) $result ];
		}

		return [
			'success' => (bool) ( $result['success'] ?? true ),
			'message' => (string) ( $result['message'] ?? '' ),
			'data'    => (array) ( $result['data'] ?? [] ),
		];
	}
}
