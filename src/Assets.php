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

		$handle = Runtime::handle();

		wp_register_style(
			$handle,
			$this->url . '/css/field-kit.css',
			[ 'dashicons' ],
			$this->version( 'css/field-kit.css' )
		);

		// jquery is a base dependency even though the kit is vanilla: several
		// of the core controls it drives — the colour picker above all — are
		// jQuery plugins, and a script that runs before them finds nothing to
		// call and fails without a word.
		wp_register_script(
			$handle,
			$this->url . '/js/field-kit.js',
			[ 'jquery' ],
			$this->version( 'js/field-kit.js' ),
			true
		);

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

		foreach ( $dependencies['styles'] ?? [] as $style ) {
			wp_enqueue_style( $style );
		}

		foreach ( $dependencies['scripts'] ?? [] as $script ) {
			wp_enqueue_script( $script );
		}

		$this->enqueue_code_editors( $dependencies['code_editors'] ?? [] );

		// Whatever this screen's fields need has to load first, so it is
		// added to the kit's own dependencies rather than merely enqueued
		// alongside. Enqueue order is not load order — WordPress resolves
		// that from dependencies — and the kit was running before jQuery and
		// the colour picker for exactly this reason.
		$this->depend_on( $dependencies['scripts'] ?? [] );

		wp_enqueue_style( Runtime::handle() );
		wp_enqueue_script( Runtime::handle() );

		// The media frame needs its templates printed, which enqueueing the
		// script alone does not do.
		if ( in_array( 'media-views', $dependencies['scripts'] ?? [], true ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Make the kit's script load after the handles it drives.
	 *
	 * @param string[] $handles Script handles this screen needs.
	 *
	 * @return void
	 */
	private function depend_on( array $handles ): void {
		$script = wp_scripts()->query( Runtime::handle(), 'registered' );

		if ( ! $script ) {
			return;
		}

		// wp_enqueue_code_editor() registers 'code-editor' itself, so it is
		// added by name rather than being something a field could declare.
		if ( wp_script_is( 'code-editor', 'enqueued' ) ) {
			$handles[] = 'code-editor';
		}

		$script->deps = array_values( array_unique( array_merge( $script->deps, $handles ) ) );
	}

	/**
	 * Enqueue a code editor for each language in use.
	 *
	 * wp_enqueue_code_editor() enqueues CodeMirror with the right mode and
	 * linter and returns the settings its initialiser needs, so the settings
	 * are passed through to the script keyed by language.
	 *
	 * It returns false when the current user has turned syntax highlighting
	 * off in their profile. That is a preference, not a failure: the field
	 * stays a plain textarea, which is why the control was never anything
	 * else underneath.
	 *
	 * @param string[] $types Mime types to prepare.
	 *
	 * @return void
	 */
	private function enqueue_code_editors( array $types ): void {
		$settings = [];

		foreach ( $types as $type ) {
			$resolved = wp_enqueue_code_editor( [ 'type' => $type ] );

			if ( false !== $resolved ) {
				$settings[ $type ] = $resolved;
			}
		}

		if ( [] === $settings ) {
			return;
		}

		wp_add_inline_script(
			Runtime::handle(),
			sprintf(
				'window.ArrayPressFieldKit=window.ArrayPressFieldKit||{};'
				. 'window.ArrayPressFieldKit[%1$s]=window.ArrayPressFieldKit[%1$s]||{};'
				. 'window.ArrayPressFieldKit[%1$s].codeEditors=%2$s;',
				wp_json_encode( Runtime::handle() ),
				wp_json_encode( $settings )
			),
			'before'
		);
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
				'addItem'          => __( 'Add', 'arraypress' ),
				'removeItem'       => __( 'Remove', 'arraypress' ),
				'actionDone'       => __( 'Done.', 'arraypress' ),
				'actionFailed'     => __( 'That did not work.', 'arraypress' ),
			],
		];
	}

	/**
	 * A cache-busting version for one asset.
	 *
	 * The file's own modification time rather than a constant, so an edit
	 * during development is picked up without bumping anything by hand.
	 *
	 * Per file, and that is the whole point. Both assets used to share the
	 * script's mtime, so a stylesheet-only change kept the version it already
	 * had and every browser went on serving the cached copy. A CSS fix then
	 * looked like a CSS fix that did not work — repeatedly, and there is no
	 * way to tell that apart from a rule that loses on specificity.
	 *
	 * @param string $relative Path under the assets directory.
	 *
	 * @return string
	 */
	private function version( string $relative ): string {
		$file = $this->path . '/' . $relative;

		return file_exists( $file ) ? (string) filemtime( $file ) : '1.0.0';
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
