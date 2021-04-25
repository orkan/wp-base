<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
namespace Orkan\WP\Base;

use Composer\Script\Event;
use Orkan\Utils;

/**
 * Composer scripts.
 *
 * Prepare current WP environment after composer install/update/dump events.
 * This class could be also a part of any WP plugin !BUT! the code would be then exposed in [wp-content] dir.
 * Solution is to use this separate package installed in inaccessible [vendor] dir!
 *
 * The [post-autoload-dump] event is fired after any of: [post-install-cmd] or [post-update-cmd] events.
 *
 * @link https://getcomposer.org/doc/articles/scripts.md
 *
 * @author Orkan <orkans+wpbase@gmail.com>
 */
class Composer
{

	/**
	 * Computed vendor dir.
	 */
	protected static $vendorDir;

	/**
	 * Arbitrary extra data for consumption by scripts.
	 *
	 * composer.json: extra => {...}
	 * @link https://getcomposer.org/doc/04-schema.md#extra
	 */
	protected static $cfg;

	/**
	 * @var Factory
	 */
	protected static $Factory;

	/**
	 * Initialize Autoloader
	 */
	protected static function setup( Event $Event )
	{
		if ( isset( static::$vendorDir ) ) {
			return;
		}

		// CAUTION: $vendorDir = "phar://D:/Apps/Composer/composer-latest.phar/vendor"
		//$Reflector = new \ReflectionClass( \Composer\Autoload\ClassLoader::class );
		//static::$vendorDir = dirname( $Reflector->getFileName(), 2 );
		static::$vendorDir = $Event->getComposer()->getConfig()->get( 'vendor-dir' );

		$extra = $Event->getComposer()->getPackage()->getExtra();
		static::$cfg = $extra['ork-wp-base'] ?? [];

		/* @formatter:off */
		static::$cfg = array_merge([
			'factory-ns' => Factory::class,
			'wp-config'  => '/../html/wp-config.php',
		], static::$cfg );
		/* @formatter:on */

		// Services:
		static::$Factory = new static::$cfg['factory-class']();

		// Load WordPress...
		define( 'ORK_STANDALONE', true );
		require static::$vendorDir . static::$cfg['wp-config'];
	}

	/**
	 * Delete file with no warnings.
	 */
	public static function unlink( string $file ): void
	{
		$result = @unlink( $file ) ? 'OK' : 'Not found';
		printf( "Remove: %s - %s\n", $file, $result );
	}

	/**
	 * Render curent Composer::Event.
	 *
	 * @link https://stackoverflow.com/questions/44910354/how-do-you-pass-an-argument-to-a-composer-script-from-the-command-line
	 */
	public static function eventInfo( Event $Event )
	{
		static::setup( $Event );
		printf( "@%s: Arguments %s\n", $Event->getName(), Utils::print_r( $Event->getArguments() ) );
	}

	/**
	 * Build and minify all JS/CSS assets.
	 */
	public static function rebuildAssets( Event $Event )
	{
		static::setup( $Event );

		/* @formatter:off */
		$Factory = static::$Factory->merge([
			'ast_rebuild'     => true,
			'ast_rebuild_css' => true,
			'ast_rebuild_js'  => true,
			'ast_minify_css'  => true,
			'ast_minify_js'   => true,
		]);
		/* @formatter:on */

		$Factory->Plugin()->run();
		$Settings = $Factory->Settings();
		$Asset = $Factory->Asset();

		$assets = ABSPATH . $Factory->get( 'assets_loc' );
		$Settings->adminNotice( 'Rebuild assets in', $assets );

		foreach ( [ 'css', 'js' ] as $type ) {
			foreach ( glob( "{$assets}/{$type}/src/*.{$type}" ) as $file ) {

				$basename = basename( $file );

				if ( '_' === $basename[0] ) {
					continue;
				}

				$Asset->info( $type . '/' . $basename );
			}
		}

		echo strip_tags( str_replace( '</div>', "</div>\n", $Settings->adminNotices() ) );
	}

	/**
	 * Backup files defined in extra section.
	 */
	public static function backupFiles( Event $Event )
	{
		static::setup( $Event );

		$files = static::$cfg['backup-files'] ?? [];

		foreach ( $files as $src => $dst ) {
			$status = 'Not found';

			if ( file_exists( $src ) ) {
				$mTime = filemtime( $src );
				$dst = strtr( $dst, [ '{Ymd}' => date( 'Ymd', $mTime ) ] );

				// Do not overwrite!
				if ( file_exists( $dst ) ) {
					$status = 'Exists';
				}
				elseif ( @copy( $src, $dst ) ) {
					touch( $dst, $mTime );
					$status = 'OK';
				}
				else {
					$status = 'Failed!';
				}
			}

			printf( "Backup: %s -> %s - %s\n", $src, $dst, $status );
		}
	}

	/**
	 * Prepare DEV env.
	 */
	public static function prepareDev( Event $Event )
	{
		static::setup( $Event );
		static::unlink( WP_DEBUG_LOG );
	}

	/**
	 * Prepare PROD env.
	 */
	public static function prepareProd( Event $Event )
	{
		static::setup( $Event );
		static::unlink( WP_DEBUG_LOG );
	}
}
