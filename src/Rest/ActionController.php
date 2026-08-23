<?php
/**
 * Action REST Controller
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Rest;

use ArrayPress\FieldKit\Actions\Actions;
use ArrayPress\FieldKit\Utils\Runtime;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The one endpoint every interactive button talks to.
 *
 * Activating a licence, sending a test email, previewing one and whatever a
 * consumer wires up are all the same shape: send a payload, run a named
 * handler, get back success and a message. The predecessor libraries grew a
 * route per feature — setting-fields alone had /license, /email/preview,
 * /email/send-test and /action — each with its own permission check, which
 * is four places for one of them to be wrong.
 *
 * The namespace is derived rather than written for the same reason the
 * search endpoint's is: WP_REST_Server::register_route() array_merges
 * same-path registrations, so two plugins bundling a prefixed copy under one
 * namespace would leave the first to register answering the other's
 * requests, under its own capability check.
 */
final class ActionController {

	/**
	 * The available actions.
	 *
	 * @var Actions
	 */
	private Actions $actions;

	/**
	 * Namespaces already claimed, so a second instance cannot double-register.
	 *
	 * @var array<string, bool>
	 */
	private static array $claimed = [];

	/**
	 * Construct.
	 *
	 * @param Actions|null $actions The available actions.
	 */
	public function __construct( ?Actions $actions = null ) {
		$this->actions = $actions ?? Actions::shared();
	}

	/**
	 * Hook the route registration.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$namespace = Runtime::rest_namespace();

		if ( isset( self::$claimed[ $namespace ] ) ) {
			return;
		}

		self::$claimed[ $namespace ] = true;

		register_rest_route(
			$namespace,
			'/action',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'run' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'action'  => [
						'description'       => __( 'Name of the action to run.', 'arraypress' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
					'payload' => [
						'description' => __( 'Data the field is sending.', 'arraypress' ),
						'type'        => 'object',
						'default'     => [],
					],
				],
			]
		);
	}

	/**
	 * Whether the request may run the named action.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return true|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ): bool|WP_Error {
		$action = $this->actions->get( (string) $request->get_param( 'action' ) );

		if ( null === $action ) {
			return new WP_Error(
				'field_kit_unknown_action',
				__( 'Unknown action.', 'arraypress' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! current_user_can( $action->capability() ) ) {
			return new WP_Error(
				'field_kit_forbidden',
				__( 'You are not allowed to do that.', 'arraypress' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Run the action.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function run( WP_REST_Request $request ): WP_REST_Response {
		$action = $this->actions->get( (string) $request->get_param( 'action' ) );
		$result = $action->handle( $this->clean( (array) $request->get_param( 'payload' ) ) );

		return new WP_REST_Response(
			[
				'success' => (bool) ( $result['success'] ?? false ),
				'message' => (string) ( $result['message'] ?? '' ),
				'data'    => (array) ( $result['data'] ?? [] ),
			],
			// A failed action is still a handled request. Returning 4xx would
			// make the script treat a licence server saying "no" the same as
			// the endpoint being unreachable, which are different problems
			// and want different messages.
			200
		);
	}

	/**
	 * Reduce the payload to scalars and flat arrays of scalars.
	 *
	 * A handler decides what it trusts, but nothing nested or object-shaped
	 * reaches one from here.
	 *
	 * @param array<string, mixed> $payload Raw payload.
	 *
	 * @return array<string, scalar|array<int, scalar>>
	 */
	private function clean( array $payload ): array {
		$clean = [];

		foreach ( $payload as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = array_values(
					array_map(
						static fn( $item ) => sanitize_text_field( (string) $item ),
						array_filter( $value, 'is_scalar' )
					)
				);
				continue;
			}

			if ( is_scalar( $value ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $clean;
	}
}
