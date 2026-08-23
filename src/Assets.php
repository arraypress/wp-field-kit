<?php
/**
 * Asset Registration
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit;

use ArrayPress\FieldKit\Utils\Runtime;

/**
 * Registers the one script and the one stylesheet.
 *
 * Handles are derived, and the configuration is published into a registry
 * keyed by handle rather than to a bare global: two plugins each bundling a
 * Strauss-prefixed copy load two copies of this script, and a shared global
 * would leave whichever localised last owning the REST URL and nonce for
 * both.
 */
final class Assets {

	/**
	 * Whether registration has run.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Directory the assets live in.
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * URL the assets are served from.
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * Construct.
	 *
	 * @param string $path Filesystem path to the assets directory.
	 * @param string $url  URL of the assets directory.
	 */
	public function __construct( string $path = '', string $url = '' ) {
		$this->path = '' !== $path ? $path : dirname( __DIR__ ) . '/assets';
		$this->url  = '' !== $url ? $url : $this->guess_url();
	}

	/**
	 * Register the script and stylesheet.
	 *
	 * Registers rather than enqueues: a field set asks for what it needs, so
	 * a screen with no fields on it loads nothing.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;

		$handle  = Runtime::handle();
		$version = $this->version();

		wp_register_style( $handle, $this->url . '/css/field-kit.css', [ 'dashicons' ], $version );

		wp_register_script( $handle, $this->url . '/js/field-kit.js', [], $version, true );

		wp_add_inline_script(
			$handle,
			sprintf(
				'window.ArrayPressFieldKit=window.ArrayPressFieldKit||{};window.ArrayPressFieldKit[%s]=%s;',
				wp_json_encode( $handle ),
				wp_json_encode( $this->config() )
			),
			'before'
		);
	}

	/**
	 * Enqueue the kit and whatever a field set additionally needs.
	 *
	 * @param array{scripts: string[], styles: string[]} $dependencies Extra handles.
	 *
	 * @return void
	 */
	public function enqueue( array $dependencies = [] ): void {
		$this->register();

		wp_enqueue_style( Runtime::handle() );
		wp_enqueue_script( Runtime::handle() );

		foreach ( $dependencies['styles'] ?? [] as $style ) {
			wp_enqueue_style( $style );
		}

		foreach ( $dependencies['scripts'] ?? [] as $script ) {
			wp_enqueue_script( $script );
		}

		// The media frame needs its templates printed, which enqueueing the
		// script alone does not do.
		if ( in_array( 'media-views', $dependencies['scripts'] ?? [], true ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * The configuration handed to the script.
	 *
	 * @return array<string, mixed>
	 */
	private function config(): array {
		return [
			'restUrl'   => rest_url( Runtime::rest_namespace() . '/' ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'i18n'      => [
				'noResults'        => __( 'No results found.', 'arraypress' ),
				'resultsAvailable' => __( 'results available.', 'arraypress' ),
				'position'         => __( 'Item', 'arraypress' ),
				'rowAdded'         => __( 'Row added.', 'arraypress' ),
				'rowRemoved'       => __( 'Row removed.', 'arraypress' ),
				'copied'           => __( 'Copied to the clipboard.', 'arraypress' ),
				'copyFailed'       => __( 'Could not copy.', 'arraypress' ),
			],
		];
	}

	/**
	 * A cache-busting version.
	 *
	 * The file's modification time rather than a constant, so an edit during
	 * development is picked up without bumping anything by hand.
	 *
	 * @return string
	 */
	private function version(): string {
		$script = $this->path . '/js/field-kit.js';

		return file_exists( $script ) ? (string) filemtime( $script ) : '1.0.0';
	}

	/**
	 * Work out the URL this library is served from.
	 *
	 * Handles both a plugin's vendor directory and a theme's, since a library
	 * inside vendor-prefixed cannot assume either.
	 *
	 * @return string
	 */
	private function guess_url(): string {
		$path = wp_normalize_path( $this->path );
		$root = wp_normalize_path( WP_CONTENT_DIR );

		if ( str_starts_with( $path, $root ) ) {
			return content_url( substr( $path, strlen( $root ) ) );
		}

		return plugins_url( 'assets', dirname( __DIR__ ) . '/placeholder.php' );
	}
}
