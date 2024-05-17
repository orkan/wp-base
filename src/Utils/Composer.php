<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
namespace Orkan\WP\Base\Utils;

use Composer\Script\Event;
use Orkan\Utils;

/**
 * Composer scripts.
 *
 * Prepare current WP environment after composer install/update/dump events.
 * @link https://getcomposer.org/doc/articles/scripts.md
 *
 * NOTE:
 * This class could be also a part of any WP plugin !BUT! the code would be then exposed in [wp-content] dir.
 * Solution is to use this separate package installed in inaccessible [vendor] dir!
 *
 * TIPS:
 * The [post-autoload-dump] event is fired after any of: [post-install-cmd] or [post-update-cmd] events.
 *
 * @author Orkan <orkans+wpbase@gmail.com>
 */
class Composer
{

	/**
	 * Render curent Composer::Event.
	 * @link https://stackoverflow.com/questions/44910354/how-do-you-pass-an-argument-to-a-composer-script-from-the-command-line
	 */
	public static function eventInfo( Event $Event )
	{
		printf( "@%s: Arguments %s\n", $Event->getName(), Utils::print_r( $Event->getArguments() ) );
	}

	/**
	 * Build and minify all JS/CSS assets.
	 */
	public static function rebuildAssets( Event $Event )
	{
		if ( !getenv( 'PLUGIN' ) ) {
			throw new \InvalidArgumentException( 'Missing plugin basedir name. Use @putenv PLUGIN=name' );
		}

		$extra = $Event->getComposer()->getPackage()->getExtra();
		define( 'ORK_STANDALONE', true );
		require $extra['ork-wp-base']['wp-config'];

		$base = getenv( 'PLUGIN' );
		$plug = substr( WP_PLUGIN_DIR, strlen( ABSPATH ) );
		$path = $plug . '/' . $base; // plugin dir path

		$loc = $path . '/assets';
		$abs = ABSPATH . $loc;

		/* @formatter:off */
		$Asset = new Asset([
			'assets_loc'  => $loc,
			'build'       => true,
			'rebuild_css' => true,
			'rebuild_js'  => true,
			'minify_css'  => true,
			'minify_js'   => true,
		]);
		/* @formatter:on */

		echo "Source: {$abs}\n";

		foreach ( [ 'css', 'js' ] as $type ) {
			foreach ( glob( "{$abs}/{$type}/src/*.{$type}" ) as $file ) {
				$basename = basename( $file );
				if ( '_' !== $basename[0] ) {
					$Asset->info( $type . '/' . $basename );
				}
			}
		}

		echo 'Assets: ' . implode( ', ', array_map( 'basename', $Asset->stats( 'rebuild' ) ) );
		echo "\n";
	}

	/**
	 * Backup files defined in extra section.
	 */
	public static function backupFiles( Event $Event )
	{
		$extra = $Event->getComposer()->getPackage()->getExtra();
		$files = $extra['ork-wp-base']['backup-files'] ?? [];
		$rotated = [];

		echo "Backup:\n";
		foreach ( $files as $src ) {
			$dst = '';
			$status = 'Not found';

			if ( file_exists( $src ) ) {
				$parts = pathinfo( $src );
				$mTime = filemtime( $src );
				$mask = $parts['dirname'] . '/' . $parts['filename'] . '-*.' . $parts['extension'];
				$dst = str_replace( '*', date( 'Y-m-d', $mTime ), $mask );

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

			printf( "%s -> %s - %s\n", $src, $dst, $status );
			$rotated = array_merge( $rotated, Utils::filesRotate( $mask, getenv( 'KEEP' ) ?: 5 ) );
		}

		echo "Rotated:\n";
		echo $rotated && implode( "\n", array_map( 'basename', $rotated ) );
		echo "\n";
	}

	/**
	 * Delete file with no warnings.
	 */
	protected static function unlink( string $file ): void
	{
		$result = @unlink( $file ) ? 'OK' : 'Not found';
		printf( "Remove: %s - %s\n", $file, $result );
	}

	/**
	 * Prepare DEV env.
	 */
	public static function prepareDev( Event $Event )
	{
		$extra = $Event->getComposer()->getPackage()->getExtra();
		define( 'ORK_STANDALONE', true );
		require $extra['ork-wp-base']['wp-config'];

		static::unlink( WP_DEBUG_LOG );
	}

	/**
	 * Prepare PROD env.
	 */
	public static function prepareProd( Event $Event )
	{
		$extra = $Event->getComposer()->getPackage()->getExtra();
		define( 'ORK_STANDALONE', true );
		require $extra['ork-wp-base']['wp-config'];

		static::unlink( WP_DEBUG_LOG );
	}
}
