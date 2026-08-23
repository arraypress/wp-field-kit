<?php
/**
 * PHPUnit bootstrap.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

/*
 * Before the autoloader, because ABSPATH has to exist by the time Composer
 * runs any files-autoloaded entrypoint that guards on it.
 */
require_once __DIR__ . '/stubs.php';

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
