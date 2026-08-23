<?php
/**
 * Search Source Registry
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Search;

/**
 * The sources a relational field may search.
 *
 * Built-ins are registered lazily so a consumer can replace one before it is
 * ever constructed.
 */
final class Sources {

	/**
	 * The registry the REST endpoint resolves against.
	 *
	 * Shared deliberately: a field registers its source while a page is
	 * rendering, and the endpoint has to find it on a later request. Two
	 * instances would mean a source that exists when the field is drawn and
	 * does not exist when it is searched.
	 *
	 * @var self|null
	 */
	private static ?self $shared = null;

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
	 * Registered sources by name.
	 *
	 * @var array<string, Source>
	 */
	private array $sources = [];

	/**
	 * Whether the built-ins have been added.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Register a source, replacing any of the same name.
	 *
	 * @param Source $source The source.
	 *
	 * @return self
	 */
	public function register( Source $source ): self {
		$this->sources[ $source->name() ] = $source;

		return $this;
	}

	/**
	 * Whether a name resolves.
	 *
	 * @param string $name Source name.
	 *
	 * @return bool
	 */
	public function has( string $name ): bool {
		$this->boot();

		return isset( $this->sources[ $name ] );
	}

	/**
	 * Resolve a source by name.
	 *
	 * @param string $name Source name.
	 *
	 * @return Source|null
	 */
	public function get( string $name ): ?Source {
		$this->boot();

		return $this->sources[ $name ] ?? null;
	}

	/**
	 * Every registered source name.
	 *
	 * @return string[]
	 */
	public function names(): array {
		$this->boot();

		return array_keys( $this->sources );
	}

	/**
	 * Add the built-in sources, unless already replaced.
	 *
	 * @return void
	 */
	private function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		foreach ( [ new PostSource(), new UserSource(), new TermSource() ] as $source ) {
			if ( ! isset( $this->sources[ $source->name() ] ) ) {
				$this->sources[ $source->name() ] = $source;
			}
		}
	}
}
