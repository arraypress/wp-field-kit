<?php
/**
 * Are the documented field configurations real?
 *
 * A configuration key nothing reads is not an error in PHP. The array entry
 * sits there, the control renders with its defaults, and the only symptom is
 * that a documented option quietly does nothing — which is how a set of
 * examples came to use `button_label` on a repeater twenty-three times, when
 * the add button has always been `add_label`.
 *
 * This reads the fenced PHP in a library's markdown, pulls out every field
 * configuration it can find, and checks each one against the registry and
 * against what the type says it reads.
 *
 * Usage, from a library that depends on the kit:
 *
 *   php vendor/arraypress/wp-field-kit/bin/verify-docs.php README.md EXAMPLES.md
 *
 * A library with a vocabulary of its own declares it:
 *
 *   ... --types=header,info_grid --keys=tab docs/*.md
 *
 * And which calls in its documentation register fields, so a README
 * demonstrating another library is not read as this one's configuration:
 *
 *   ... --calls=register_user_fields README.md
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;

// The library being checked, or the kit itself.
foreach ( [ __DIR__ . '/../../../autoload.php', __DIR__ . '/../vendor/autoload.php' ] as $autoload ) {
	if ( file_exists( $autoload ) ) {
		require $autoload;
		break;
	}
}

if ( ! class_exists( Registry::class ) ) {
	fwrite( STDERR, "The kit is not autoloadable from here.\n" );
	exit( 1 );
}

// Stubs, so a config array containing __() can be evaluated at all.
require_once __DIR__ . '/../tests/stubs.php';

/*
 * A consuming library has a vocabulary of its own. Flyouts documents `header`
 * and `info_grid`, which are components it draws rather than field types the
 * kit registers, and a `tab` key naming which tab a field belongs to, which
 * the flyout reads and the kit never sees. Reporting those as errors would
 * mean the tool cannot be used in the library that most needs it — and a
 * check that always reports something is a check nobody runs.
 *
 * So both are declarable:
 *
 *   --types=header,info_grid  types this library draws itself
 *   --keys=tab,wrapper_class  keys this library reads itself
 */
$extra_types = [];
$extra_keys  = [];
$only_calls  = [];
$files       = [];

foreach ( array_slice( $argv, 1 ) as $argument ) {
	if ( str_starts_with( $argument, '--types=' ) ) {
		$extra_types = array_filter( array_map( 'trim', explode( ',', substr( $argument, 8 ) ) ) );
		continue;
	}

	if ( str_starts_with( $argument, '--keys=' ) ) {
		$extra_keys = array_filter( array_map( 'trim', explode( ',', substr( $argument, 7 ) ) ) );
		continue;
	}

	if ( str_starts_with( $argument, '--calls=' ) ) {
		$only_calls = array_filter( array_map( 'trim', explode( ',', substr( $argument, 8 ) ) ) );
		continue;
	}

	$files[] = $argument;
}

if ( [] === $files ) {
	$files = array_values( array_filter( [ 'README.md', 'EXAMPLES.md' ], 'file_exists' ) );
}

/*
 * A library that adds keys of its own already says so, in code:
 *
 *   Field::allow_config_keys( [ 'own_capability', 'show_on_add' ] );
 *
 * It runs when the library boots, which is not something this tool can make
 * happen — it reads markdown, not a WordPress request. So the declarations are
 * read out of the source instead, and a library that has already declared a
 * key does not have to declare it again on a command line. Two places to keep
 * in step is how a key ends up allowed in one and reported in the other.
 */
$extra_keys = array_merge( $extra_keys, declared_config_keys( getcwd() . '/src' ) );

/**
 * The extra configuration keys a library declares in its own source.
 *
 * @param string $directory The library's `src`.
 *
 * @return string[]
 */
function declared_config_keys( string $directory ): array {
	if ( ! is_dir( $directory ) ) {
		return [];
	}

	$keys  = [];
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) );

	foreach ( $files as $file ) {
		if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
			continue;
		}

		if ( ! preg_match_all(
			'/allow_config_keys\(\s*\[(.*?)\]/s',
			(string) file_get_contents( $file->getPathname() ),
			$calls
		) ) {
			continue;
		}

		foreach ( $calls[1] as $literal ) {
			// Quoted strings only. A variable or a constant is not something
			// that can be read out of the source, and a key this misses is
			// reported rather than silently allowed.
			preg_match_all( '/[\'"]([a-z0-9_]+)[\'"]/i', $literal, $quoted );

			$keys = array_merge( $keys, $quoted[1] );
		}
	}

	return array_values( array_unique( $keys ) );
}

/**
 * Every fenced PHP block in a markdown file, with the line it starts on.
 *
 * @param string $markdown File contents.
 *
 * @return array<int, array{0: int, 1: string}>
 */
function fenced_php( string $markdown ): array {
	$lines  = explode( "\n", $markdown );
	$blocks = [];
	$open   = null;
	$buffer = [];

	foreach ( $lines as $number => $line ) {
		if ( null === $open && preg_match( '/^```\s*php\s*$/', $line ) ) {
			$open   = $number + 2;
			$buffer = [];
			continue;
		}

		if ( null !== $open && preg_match( '/^```\s*$/', $line ) ) {
			$blocks[] = [ $open, implode( "\n", $buffer ) ];
			$open     = null;
			continue;
		}

		if ( null !== $open ) {
			$buffer[] = $line;
		}
	}

	return $blocks;
}

/**
 * Pull the field configurations out of a block and evaluate them.
 *
 * The call shapes differ across the libraries — a taxonomy name and a config,
 * a list of post types and a config, or a single config on its own — so every
 * top-level array literal in a register_*() call is evaluated and the ones
 * carrying `fields` or `panels` are kept. Nothing is guessed at from position.
 *
 * @param string $code A fenced PHP block.
 *
 * @return array<int, array<string, mixed>>
 */
function configs_in( string $code ): array {
	global $only_calls;

	$found = [];

	if ( ! preg_match_all( '/register_[a-z_]+\(/', $code, $calls, PREG_OFFSET_CAPTURE ) ) {
		return $found;
	}

	// A README shows more than its own library. The term-fields and
	// user-fields ones both demonstrate pairing with wp-register-columns, in
	// the same fenced block, and a columns configuration is a map of names to
	// arrays carrying a `label` — indistinguishable from a field map by shape
	// alone. Read as fields, its `display_callback` and `sortable` came back
	// as keys nothing reads, when what nothing reads them is *this* library.
	//
	// So a library can say which calls register its fields.
	if ( [] !== $only_calls ) {
		$calls[0] = array_values(
			array_filter(
				$calls[0],
				static fn( $call ) => in_array( rtrim( (string) $call[0], '(' ), $only_calls, true )
			)
		);
	}

	$length = strlen( $code );

	foreach ( $calls[0] as $call ) {
		// Walk the argument list, evaluating each array literal at depth one.
		$i     = (int) $call[1] + strlen( (string) $call[0] );
		$depth = 0;

		for ( ; $i < $length; $i++ ) {
			$character = $code[ $i ];

			if ( ')' === $character && 0 === $depth ) {
				break;
			}

			if ( '[' !== $character ) {
				continue;
			}

			// The whole literal, brackets balanced.
			$open = $i;

			for ( $depth = 0; $i < $length; $i++ ) {
				if ( '[' === $code[ $i ] ) {
					++$depth;
				} elseif ( ']' === $code[ $i ] ) {
					--$depth;

					if ( 0 === $depth ) {
						break;
					}
				}
			}

			$literal = substr( $code, $open, $i - $open + 1 );

			try {
				// A parse error is a ParseError in PHP 8, which @ does not
				// suppress. Documentation is full of placeholders — `callable`,
				// `/* ... */` — that are prose rather than code, and a block
				// that will not parse is skipped rather than reported: this
				// checks what a reader would copy, not what they would read.
				$value = eval( 'return ' . $literal . ';' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
			} catch ( \Throwable $error ) {
				continue;
			}

			if ( is_array( $value ) && ( isset( $value['fields'] ) || isset( $value['panels'] ) ) ) {
				$found[] = $value;
			} elseif ( is_field_map( $value ) ) {
				// Four of the five libraries take the field map as an argument
				// of its own rather than under a `fields` key, so a bare map
				// is a configuration too.
				$found[] = [ 'fields' => $value ];
			}

			$depth = 0;
		}
	}

	return $found;
}

/**
 * Whether an array is a map of field keys to field configurations.
 *
 * Told apart from a metabox configuration by its values: every one is an
 * array that names a type or carries a label. An options list fails on the
 * first count, and a metabox configuration on the second.
 *
 * @param mixed $value A candidate.
 *
 * @return bool
 */
function is_field_map( mixed $value ): bool {
	if ( ! is_array( $value ) || [] === $value ) {
		return false;
	}

	foreach ( $value as $key => $config ) {
		if ( ! is_string( $key ) || ! is_array( $config ) ) {
			return false;
		}

		if ( ! isset( $config['type'] ) && ! isset( $config['label'] ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Walk a configuration and check every field in it.
 *
 * @param array<string, mixed> $fields   Field configuration, keyed by field key.
 * @param Registry             $registry The type registry.
 * @param string               $where    A label for messages.
 *
 * @return string[] Problems found.
 */
function check_fields( array $fields, Registry $registry, string $where ): array {
	global $extra_types, $extra_keys;

	$problems = [];

	foreach ( $fields as $key => $config ) {
		if ( ! is_array( $config ) ) {
			continue;
		}

		$type = (string) ( $config['type'] ?? 'text' );

		// A type the consuming library draws itself. Its keys are its own, so
		// there is nothing here to check them against.
		if ( in_array( $type, $extra_types, true ) ) {
			continue;
		}

		if ( ! $registry->has( $type ) ) {
			$problems[] = sprintf( '%s: "%s" has type "%s", which is not registered.', $where, $key, $type );
			continue;
		}

		$field   = new Field( (string) $key, $registry->get( $type ), $config, null );
		$unknown = array_values( array_diff( $field->unknown_keys(), $extra_keys ) );

		if ( [] !== $unknown ) {
			$problems[] = sprintf(
				'%s: "%s" (%s) sets %s, which nothing reads.',
				$where,
				$key,
				$type,
				implode( ', ', $unknown )
			);
		}

		// Nested fields — a group, a repeater, an email editor.
		if ( isset( $config['fields'] ) && is_array( $config['fields'] ) ) {
			$problems = array_merge( $problems, check_fields( $config['fields'], $registry, $where . ' › ' . $key ) );
		}
	}

	return $problems;
}

$registry = new Registry();
$problems = [];
$checked  = 0;

foreach ( $files as $file ) {
	if ( ! file_exists( $file ) ) {
		fwrite( STDERR, sprintf( "%s does not exist.\n", $file ) );
		exit( 1 );
	}

	foreach ( fenced_php( (string) file_get_contents( $file ) ) as [ $line, $code ] ) {
		foreach ( configs_in( $code ) as $config ) {
			$fields = $config['fields'] ?? null;

			// A tabbed metabox puts its fields inside panels.
			if ( null === $fields && isset( $config['panels'] ) && is_array( $config['panels'] ) ) {
				$fields = [];

				foreach ( $config['panels'] as $panel ) {
					$fields = array_merge( $fields, (array) ( $panel['fields'] ?? [] ) );
				}
			}

			if ( ! is_array( $fields ) ) {
				continue;
			}

			$checked += count( $fields );
			$problems = array_merge( $problems, check_fields( $fields, $registry, sprintf( '%s:%d', $file, $line ) ) );
		}
	}
}

printf( "%d documented fields checked in %s\n", $checked, implode( ', ', $files ) );

if ( [] === $problems ) {
	echo "every one of them is real\n";
	exit( 0 );
}

printf( "\n%d problem(s):\n", count( $problems ) );

foreach ( $problems as $problem ) {
	printf( "  %s\n", $problem );
}

exit( 1 );
