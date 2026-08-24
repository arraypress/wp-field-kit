<?php
/**
 * Self-reference checker.
 *
 * Shared test infrastructure. Not part of the library's runtime — it lives
 * beside the stubs the consuming libraries already require, because every one
 * of them wants this check and none of them should carry its own copy.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ReflectionClass;

/**
 * Does every `self::` and `$this->` in a library point at something that
 * exists — in the class it will actually be part of?
 *
 * PHP will not tell you. A property nobody declared, a constant deleted with
 * the feature that used it, a method removed while its last caller stayed —
 * all of it parses, all of it lints, and every one is a fatal the moment that
 * line runs. `php -l` says nothing, and neither does a suite that never
 * reaches the branch.
 *
 * Not hypothetical: removing a vendored search library from an asset manager
 * took its handle constant with it and, in the same edit, the properties and
 * the registration method that had nothing to do with it. The file linted
 * clean and white-screened every admin page the library loaded on.
 *
 * ## Traits
 *
 * A trait is not where its own code runs. `self::render_header()` inside a
 * trait resolves against whatever class composes it, so the trait file alone
 * cannot answer the question — and asking it there gives the wrong answer in
 * both directions. A call to a sibling trait's method looks broken and is
 * fine; a call to a method that no composing class has looks fine and is a
 * fatal. That second one is the failure mode of splitting a large class up:
 * the code moves, the `use` line is forgotten, everything lints, and the page
 * dies the moment the hook fires.
 *
 * So a reference inside a trait is resolved against every class that composes
 * it, transitively. A trait nothing composes is reported too — code that
 * cannot run is not code that works.
 *
 * ## `$this->`
 *
 * Checked, but only against names the library itself declares somewhere. A
 * list table calls dozens of methods it inherits from core, and core is not
 * here — a stub carries the handful the tests need and no more, so demanding
 * that every `$this->` resolve would report core's own API as missing. What
 * is left is exactly the question worth asking: this library defines that
 * method, and the object calling it will not have it.
 *
 * Tokens rather than a regular expression, so a mention in a comment or a
 * string is not a reference.
 */
final class SelfReferences {

	/**
	 * Every unresolvable reference under a directory.
	 *
	 * @param string $directory Absolute path to a library's `src`.
	 *
	 * @return string[] One line per problem, empty when there are none.
	 */
	public static function broken( string $directory ): array {
		$declared = self::declared( $directory );
		$names    = self::declared_methods( $declared );
		$broken   = [];

		foreach ( self::sources( $directory ) as $path ) {
			foreach ( self::references( $path ) as [ $symbol, $access, $kind, $name, $line ] ) {
				// A symbol the autoloader cannot produce is not this check's
				// business — it would report every reference in the file for
				// one unrelated reason.
				if ( ! self::loadable( $symbol ) ) {
					continue;
				}

				$hosts = self::hosts( $symbol, $declared );

				if ( [] === $hosts ) {
					continue;
				}

				foreach ( $hosts as $host ) {
					if ( self::has( $host, $kind, $name ) ) {
						continue;
					}

					// Core's own API, reached through `$this->` on a class
					// that extends something this library did not write.
					//
					// Only then: a class whose whole ancestry is here has
					// nowhere else for a method to come from, so a name that
					// exists nowhere at all is still a fatal waiting to
					// happen. And a name the library *does* declare is the
					// case this check exists for — the code moved into a
					// trait and the `use` line was forgotten.
					if ( 'this' === $access
						&& ! isset( $names[ $name ] )
						&& self::inherits_from_elsewhere( $host, $declared ) ) {
						continue;
					}

					$broken[] = sprintf(
						'%s:%d  %s%s%s is not a declared %s%s',
						basename( $path ),
						$line,
						'this' === $access ? '$this->' : 'self::',
						'property' === $kind ? '$' : '',
						$name,
						$kind,
						$host === $symbol ? '' : sprintf( ' on %s, which composes %s', self::short( $host ), self::short( $symbol ) )
					);
				}
			}
		}

		// A trait nothing composes never runs, so nothing above could have
		// checked it. That is worth saying on its own.
		foreach ( $declared as $symbol => $kind ) {
			if ( 'trait' === $kind && self::loadable( $symbol ) && [] === self::hosts( $symbol, $declared ) ) {
				$broken[] = sprintf( '%s is a trait no class uses', self::short( $symbol ) );
			}
		}

		sort( $broken );

		return $broken;
	}

	/**
	 * Whether a symbol can be reflected on.
	 *
	 * @param string $symbol Fully-qualified name.
	 *
	 * @return bool
	 */
	private static function loadable( string $symbol ): bool {
		return class_exists( $symbol ) || interface_exists( $symbol ) || trait_exists( $symbol );
	}

	/**
	 * Whether a symbol declares, inherits or composes something.
	 *
	 * @param string $symbol Fully-qualified name.
	 * @param string $kind   property, method or constant.
	 * @param string $name   What is being looked for.
	 *
	 * @return bool
	 */
	private static function has( string $symbol, string $kind, string $name ): bool {
		$mirror = new ReflectionClass( $symbol );

		return match ( $kind ) {
			'property' => $mirror->hasProperty( $name ),
			'method'   => $mirror->hasMethod( $name ),
			default    => $mirror->hasConstant( $name ),
		};
	}

	/**
	 * The classes a reference inside a symbol will run as part of.
	 *
	 * Itself, for a class. For a trait, every class in the library that
	 * composes it — directly or through another trait.
	 *
	 * @param string                $symbol   Fully-qualified name.
	 * @param array<string, string> $declared Everything the library declares.
	 *
	 * @return string[]
	 */
	private static function hosts( string $symbol, array $declared ): array {
		if ( 'trait' !== ( $declared[ $symbol ] ?? '' ) ) {
			return self::loadable( $symbol ) ? [ $symbol ] : [];
		}

		$hosts = [];

		foreach ( $declared as $candidate => $kind ) {
			if ( 'class' !== $kind || ! self::loadable( $candidate ) ) {
				continue;
			}

			if ( in_array( $symbol, self::composed( $candidate ), true ) ) {
				$hosts[] = $candidate;
			}
		}

		return $hosts;
	}

	/**
	 * Every trait a class ends up with, following traits that use traits.
	 *
	 * @param string $symbol Fully-qualified name.
	 *
	 * @return string[]
	 */
	private static function composed( string $symbol ): array {
		$found = [];
		$queue = [ $symbol ];

		while ( $queue ) {
			$next = (string) array_shift( $queue );

			foreach ( ( new ReflectionClass( $next ) )->getTraitNames() as $trait ) {
				if ( in_array( $trait, $found, true ) ) {
					continue;
				}

				$found[] = $trait;
				$queue[] = $trait;
			}

			// Traits a parent composes are inherited along with everything
			// else, and getTraitNames() reports only the class's own.
			$parent = ( new ReflectionClass( $next ) )->getParentClass();

			if ( $parent && ! in_array( $parent->getName(), $found, true ) ) {
				$found[] = $parent->getName();
				$queue[] = $parent->getName();
			}
		}

		return $found;
	}

	/**
	 * Whether a class extends something the library did not declare.
	 *
	 * A list table extends core's, so it has methods from somewhere this
	 * check cannot see. A class that does not is answerable for every method
	 * it calls on itself.
	 *
	 * @param string                $symbol   Fully-qualified name.
	 * @param array<string, string> $declared Everything the library declares.
	 *
	 * @return bool
	 */
	private static function inherits_from_elsewhere( string $symbol, array $declared ): bool {
		$parent = ( new ReflectionClass( $symbol ) )->getParentClass();

		while ( $parent ) {
			if ( ! isset( $declared[ $parent->getName() ] ) ) {
				return true;
			}

			$parent = $parent->getParentClass();
		}

		return false;
	}

	/**
	 * Every method name the library declares anywhere.
	 *
	 * The set `$this->` references are checked against, so that core's API is
	 * not reported as missing from a class that inherits it.
	 *
	 * @param array<string, string> $declared Everything the library declares.
	 *
	 * @return array<string, true>
	 */
	private static function declared_methods( array $declared ): array {
		$names = [];

		foreach ( array_keys( $declared ) as $symbol ) {
			if ( ! self::loadable( $symbol ) ) {
				continue;
			}

			foreach ( ( new ReflectionClass( $symbol ) )->getMethods() as $method ) {
				$names[ $method->getName() ] = true;
			}
		}

		return $names;
	}

	/**
	 * Everything a directory declares, as name => class|trait|interface.
	 *
	 * @param string $directory Absolute path.
	 *
	 * @return array<string, string>
	 */
	private static function declared( string $directory ): array {
		$declared = [];

		foreach ( self::sources( $directory ) as $path ) {
			$namespace = '';

			foreach ( self::tokens( $path ) as $index => $token ) {
				if ( ! is_array( $token ) ) {
					continue;
				}

				if ( T_NAMESPACE === $token[0] ) {
					$namespace = self::name_after( $path, $index );
					continue;
				}

				if ( ! in_array( $token[0], [ T_CLASS, T_INTERFACE, T_TRAIT ], true ) ) {
					continue;
				}

				$name = self::name_after( $path, $index );

				if ( '' === $name ) {
					continue;
				}

				$declared[ $namespace . '\\' . $name ] = match ( $token[0] ) {
					T_TRAIT     => 'trait',
					T_INTERFACE => 'interface',
					default     => 'class',
				};
			}
		}

		return $declared;
	}

	/**
	 * The name token following one at an index, joined across separators.
	 *
	 * @param string $path  File the tokens came from.
	 * @param int    $index Index of the keyword.
	 *
	 * @return string
	 */
	private static function name_after( string $path, int $index ): string {
		$tokens = self::tokens( $path );
		$name   = '';

		for ( $step = $index + 1; $step < count( $tokens ); $step ++ ) {
			$token = $tokens[ $step ];

			if ( is_array( $token ) && in_array( $token[0], [ T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR ], true ) ) {
				$name .= $token[1];
				continue;
			}

			break;
		}

		return trim( $name, '\\' );
	}

	/**
	 * The last segment of a fully-qualified name.
	 *
	 * @param string $symbol Fully-qualified name.
	 *
	 * @return string
	 */
	private static function short( string $symbol ): string {
		$parts = explode( '\\', $symbol );

		return (string) end( $parts );
	}

	/**
	 * The PHP files under a directory.
	 *
	 * @param string $directory Absolute path.
	 *
	 * @return string[]
	 */
	private static function sources( string $directory ): array {
		if ( ! is_dir( $directory ) ) {
			return [];
		}

		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $directory ) );
		$paths = [];

		foreach ( $files as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$paths[] = $file->getPathname();
			}
		}

		sort( $paths );

		return $paths;
	}

	/**
	 * One file's tokens, with the noise dropped.
	 *
	 * So "the next token" means the next one that matters rather than the
	 * whitespace before it. Cached, because every file is walked twice — once
	 * for what it declares and once for what it references.
	 *
	 * @param string $path Absolute path.
	 *
	 * @return array<int, array|string>
	 */
	private static function tokens( string $path ): array {
		static $cache = [];

		if ( isset( $cache[ $path ] ) ) {
			return $cache[ $path ];
		}

		$cache[ $path ] = array_values(
			array_filter(
				token_get_all( (string) file_get_contents( $path ) ),
				static fn( $token ) => ! is_array( $token )
					|| ! in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true )
			)
		);

		return $cache[ $path ];
	}

	/**
	 * Every self-reference in one file.
	 *
	 * @param string $path Absolute path.
	 *
	 * @return array<int, array{0: string, 1: string, 2: string, 3: string, 4: int}>
	 */
	private static function references( string $path ): array {
		$tokens    = self::tokens( $path );
		$namespace = '';
		$symbol    = '';
		$found     = [];

		foreach ( $tokens as $index => $token ) {
			if ( ! is_array( $token ) ) {
				continue;
			}

			if ( T_NAMESPACE === $token[0] ) {
				$namespace = self::name_after( $path, $index );
				continue;
			}

			// A named declaration. Not an anonymous class, whose next token is
			// `(` or `{`, and not `::class`, whose previous one is `::`.
			if ( in_array( $token[0], [ T_CLASS, T_INTERFACE, T_TRAIT ], true )
				&& is_array( $tokens[ $index + 1 ] ?? null )
				&& T_STRING === $tokens[ $index + 1 ][0] ) {

				$symbol = $namespace . '\\' . $tokens[ $index + 1 ][1];
				continue;
			}

			if ( '' === $symbol ) {
				continue;
			}

			$reference = self::reference_at( $tokens, $index, $symbol );

			if ( null !== $reference ) {
				$found[] = $reference;
			}
		}

		return $found;
	}

	/**
	 * One reference, if the tokens at an index are one.
	 *
	 * @param array<int, array|string> $tokens The file's tokens.
	 * @param int                      $index  Where to look.
	 * @param string                   $symbol The declaration it is inside.
	 *
	 * @return array{0: string, 1: string, 2: string, 3: string, 4: int}|null
	 */
	private static function reference_at( array $tokens, int $index, string $symbol ): ?array {
		$token  = $tokens[ $index ];
		$next   = $tokens[ $index + 1 ] ?? null;
		$target = $tokens[ $index + 2 ] ?? null;

		if ( ! is_array( $next ) || ! is_array( $target ) ) {
			return null;
		}

		$access = '';

		if ( T_STRING === $token[0]
			&& in_array( strtolower( (string) $token[1] ), [ 'self', 'static' ], true )
			&& T_DOUBLE_COLON === $next[0] ) {
			$access = 'self';
		}

		if ( T_VARIABLE === $token[0] && '$this' === $token[1] && T_OBJECT_OPERATOR === $next[0] ) {
			$access = 'this';
		}

		if ( '' === $access ) {
			return null;
		}

		// `$this->$name()` and `$this->{$name}()` name nothing to check.
		if ( 'self' === $access && T_VARIABLE === $target[0] ) {
			return [ $symbol, $access, 'property', ltrim( (string) $target[1], '$' ), (int) $target[2] ];
		}

		if ( T_STRING !== $target[0] ) {
			return null;
		}

		// Always available.
		if ( 'self' === $access && 'class' === strtolower( (string) $target[1] ) ) {
			return null;
		}

		$call = '(' === ( $tokens[ $index + 3 ] ?? '' );

		// A property read through `$this->` is not checked: core's list table
		// sets several of its own dynamically, and a stub declares only what
		// the tests touch.
		if ( 'this' === $access && ! $call ) {
			return null;
		}

		return [ $symbol, $access, $call ? 'method' : 'constant', (string) $target[1], (int) $target[2] ];
	}
}
