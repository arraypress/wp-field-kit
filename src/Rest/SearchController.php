<?php
/**
 * Search REST Controller
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Rest;

use ArrayPress\FieldKit\Search\Sources;
use ArrayPress\FieldKit\Utils\Runtime;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The one endpoint every searchable field talks to.
 *
 * It replaces three implementations: setting-fields had a REST route,
 * post-fields had a second one, and flyouts went through admin-ajax with a
 * callback named in the field config.
 *
 * The namespace is derived rather than written, because
 * `WP_REST_Server::register_route()` merges same-path registrations with
 * array_merge over a numerically-indexed handler list. Two plugins bundling
 * a prefixed copy of this library under one namespace would mean the first
 * to register answered the other's requests — under its own capability check
 * and against its own sources.
 */
final class SearchController {

	/**
	 * Largest page a request may ask for.
	 */
	private const MAX_LIMIT = 50;

	/**
	 * The available sources.
	 *
	 * @var Sources
	 */
	private Sources $sources;

	/**
	 * Paths already claimed, so a second instance cannot double-register.
	 *
	 * @var array<string, bool>
	 */
	private static array $claimed = [];

	/**
	 * Construct.
	 *
	 * @param Sources|null $sources The available sources.
	 */
	public function __construct( ?Sources $sources = null ) {
		$this->sources = $sources ?? Sources::shared();
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
	 * Register the routes.
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
			'/search',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'search' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => $this->args(),
			]
		);
	}

	/**
	 * Endpoint arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function args(): array {
		return [
			'source' => [
				'description'       => __( 'Name of the source to search.', 'arraypress' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_key',
			],
			'q'      => [
				'description'       => __( 'Search term.', 'arraypress' ),
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'page'   => [
				'description' => __( 'Page of results, one-based.', 'arraypress' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			],
			'limit'  => [
				'description' => __( 'Results per page.', 'arraypress' ),
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => self::MAX_LIMIT,
			],
			'args'   => [
				'description' => __( 'Arguments the field supplied, such as post type.', 'arraypress' ),
				'type'        => 'object',
				'default'     => [],
			],
		];
	}

	/**
	 * Whether the request may search the named source.
	 *
	 * The capability comes from the source rather than being one blanket
	 * check: searching users is not the same risk as searching categories.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return true|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ): bool|WP_Error {
		$source = $this->sources->get( (string) $request->get_param( 'source' ) );

		if ( null === $source ) {
			return new WP_Error(
				'field_kit_unknown_source',
				__( 'Unknown search source.', 'arraypress' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! current_user_can( $source->capability() ) ) {
			return new WP_Error(
				'field_kit_forbidden',
				__( 'You are not allowed to search this.', 'arraypress' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Search a source.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function search( WP_REST_Request $request ): WP_REST_Response {
		$source = $this->sources->get( (string) $request->get_param( 'source' ) );

		$found = $source->search(
			(string) $request->get_param( 'q' ),
			$this->clean_args( (array) $request->get_param( 'args' ) ),
			(int) $request->get_param( 'page' ),
			min( self::MAX_LIMIT, (int) $request->get_param( 'limit' ) )
		);

		return new WP_REST_Response(
			[
				'results' => array_values( $found['results'] ?? [] ),
				'more'    => (bool) ( $found['more'] ?? false ),
			]
		);
	}

	/**
	 * Reduce the supplied arguments to scalars.
	 *
	 * A source decides for itself which of these it trusts — the post source
	 * checks its post type against the registered ones — but nothing nested
	 * or object-shaped reaches a query from here.
	 *
	 * @param array<string, mixed> $args Raw arguments.
	 *
	 * @return array<string, scalar|array<int, scalar>>
	 */
	private function clean_args( array $args ): array {
		$clean = [];

		foreach ( $args as $key => $value ) {
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
