<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
namespace Orkan\WP\Base;

use MatthiasMullie\Minify;

/**
 * Manage assets.
 *
 * @author Orkan <orkans+wpbase@gmail.com>
 */
class Asset
{
	/**
	 * @var Factory
	 */
	protected static $Factory;

	/**
	 * @var Settings
	 */
	protected static $Settings;

	/**
	 * Setup.
	 */
	public function __construct( Factory $Factory )
	{
		static::$Factory = $Factory;
	}

	/**
	 * Get default config.
	 */
	private static function defaults()
	{
		/*
		 * [ast_jsVar]
		 * Inline Javascript Object name
		 *
		 * [ast_rebuild]
		 * Rebuild current asset?
		 *
		 * @formatter:off */
		return [
			'ast_jsVar'       => 'ork',
			'ast_rebuild'     => static::$Factory->get( 'debug' ),
			'ast_rebuild_css' => (bool) @constant( 'ORK_ASSET_REBUILD_CSS' ),
			'ast_rebuild_js'  => (bool) @constant( 'ORK_ASSET_REBUILD_JS' ),
			'ast_minify_css'  => (bool) @constant( 'ORK_ASSET_MINIFY_CSS' ),
			'ast_minify_js'   => (bool) @constant( 'ORK_ASSET_MINIFY_JS' ),
			'ast_force_min'   => (bool) @constant( 'ORK_ASSET_FORCE_MIN' ),
		];
		/* @formatter:on */
	}

	/**
	 * Services late binding.
	 */
	protected static function setup()
	{
		static $ready;

		if ( isset( $ready ) ) {
			return;
		}

		static::$Factory->merge( self::defaults() );
		static::$Settings = static::$Factory->Settings();

		$ready = true;
	}

	/**
	 * Enqueue JS/CSS assets.
	 *
	 * @see hook: admin_enqueue_scripts
	 * @see wp_enqueue_script()
	 * @see wp_add_inline_script()
	 * @see wp_localize_script()
	 * @see wp_register_script()
	 * @see wp_add_inline_script()
	 * @see wp_script_is() - Determines whether a script has been enqueued
	 *
	 * @throws \LogicException Prevent double enqueues

	 * @param string $asset   Path to asset relative to cfg[assets] dir, eg. 'js/main.js'
	 * @param array  $deps    Dependencies. Without -js, -css sufixes
	 * @param string $data    [JS only] Inline script or array that will be converted to object: window.ork = {data}
	 * @param array  $options [CSS/JS] Extra options
	 */
	public static function enqueue( string $asset, array $deps = [], $data = '', array $options = [] ): void
	{
		static::setup();

		static $info = [];
		$deps = array_unique( $deps );

		// Prevent double rebiulds
		if ( isset( $info[$asset] ) ) {
			throw new \LogicException( 'Asset already enqueued: ' . $asset );
		}

		$info[$asset] = static::info( $asset );

		/* @formatter:off */
		$args = array_merge( $options, [
			'footer'   => true,
			'position' => 'before',
			'media'    => 'all',
		]);
		/* @formatter:on */

		if ( 'css' === $info[$asset]['dirname'] ) {
			wp_enqueue_style( $info[$asset]['handle'], $info[$asset]['location'], $deps, $info[$asset]['version'], $args['media'] );
		}
		else {
			wp_enqueue_script( $info[$asset]['handle'], $info[$asset]['location'], $deps, $info[$asset]['version'], $args['footer'] );

			if ( $data ) {
				if ( is_array( $data ) ) {
					$data = sprintf( 'var %s = %s', static::$Factory->get( 'ast_jsVar' ), json_encode( $data ) ); // maybe JSON_FORCE_OBJECT
				}
				wp_add_inline_script( $info[$asset]['handle'], $data, $args['position'] );
			}
		}
	}

	/**
	 * Get asset info (also rebuild if in DEV mode)
	 * [handle]
	 *  - asset filename (without extension)
	 * [location]
	 *  - path to minified asset, relative to the root
	 *  - by using paths (instead of urls) we can use file based functions
	 *  - notice the leading slash - is required by wp_enqueue_script()
	 *  - in DEV mode points to src asset instead
	 * [version]
	 *  - plugin version
	 *  - Note, changing version string resets breakpoints in JS debugger
	 *
	 * @see wp_enqueue_script()
	 *
	 * -------------------
	 * In DEV mode:
	 * -------------------
	 *
	 * Rebuild asset file:
	 * define: ORK_ASSETS_REBUILD_CSS | ORK_ASSETS_REBUILD_JS
	 * 1. create source file in: assets/[type]/src/[basename]
	 * 2. insert partial file reference in source with: [ {FILE: [partialfile]} ] where [] is a block comment
	 * 3. the concated file will be created in: assets/[type]/[basename]
	 *
	 * Minify asset file:
	 * define: ORK_ASSET_MINIFY_CSS | ORK_ASSETS_MINIFY_JS
	 * the minified file will be created in: assets/[type]/[filename].min.[type]
	 *
	 * Force minified asset:
	 * define: ORK_ASSET_FORCE_MIN
	 *
	 * @param string $asset Asset file path, realtive to cfg[assets] dir, eg. 'js/main.js'
	 * @return array Asset file info
	 */
	public static function info( string $asset ): array
	{
		static::setup();
		$info = pathinfo( $asset );
		$info['dir'] = static::$Factory->get( 'assets_loc' ) . '/' . $info['dirname'];
		$info['src'] = sprintf( '%s/%s.%s', $info['dir'], $info['filename'], $info['extension'] );
		$info['min'] = sprintf( '%s/%s.min.%s', $info['dir'], $info['filename'], $info['extension'] );
		$info['location'] = '/' . ( static::$Factory->get( 'debug' ) ? $info['src'] : $info['min'] ); // Must start with / - required by WP
		$info['version'] = static::$Factory->get( 'version' );
		$info['handle'] = static::$Factory->get( 'ast_jsVar' ) . '-' . $info['filename'];
		$info['url'] = $info['location'] . '?v=' . $info['version'];

		if ( static::$Factory->get( 'ast_rebuild' ) ) {
			$info = static::build( $info );
		}

		return $info;
	}

	/**
	 * Build & minify asset.
	 *
	 * @param array $info Asset info from Asset::info()
	 * @return array Asset file info (updated)
	 */
	protected static function build( array $info ): array
	{
		$isRebuild = 'css' === $info['extension'] && static::$Factory->get( 'ast_rebuild_css' );
		$isRebuild |= 'js' === $info['extension'] && static::$Factory->get( 'ast_rebuild_js' );

		if ( $isRebuild ) {
			$notice = 'Rebuild';
			$absDir = ABSPATH . $info['dir'];
			$absSrc = $absDir . DIRECTORY_SEPARATOR . basename( $info['src'] ); // might be missing!
			$absMin = $absDir . DIRECTORY_SEPARATOR . basename( $info['min'] ); // might be missing!

			$tplPath = $absDir . '/src';
			$tplFile = $tplPath . '/' . $info['basename'];

			if ( !is_file( $tplFile ) ) {
				/* @formatter:off */
				throw new \InvalidArgumentException( strtr( 'Asset file "{file}" not found in "{path}" ', [
					'{file}' => $info['basename'],
					'{path}' => $tplPath,
				]));
				/* @formatter:on */
			}

			// -------
			// Parse
			$patStr = '/* {FILE: ';
			$patEnd = '} */';
			$patLen = strlen( $patStr );
			$srcBody = file( $tplFile );
			foreach ( $srcBody as &$line ) {
				if ( false !== $pos = strpos( $line, $patStr ) ) {
					$pos1 = $pos + $patLen; // /* {FILE: <here>PATH } */
					$pos2 = strpos( $line, $patEnd, $pos1 ); // /* {FILE: PATH<here> } */
					$pos3 = $pos2 + strlen( $patEnd ); // /* {FILE: ... } */<here>

					$indent = substr( $line, 0, $pos );
					$path = substr( $line, $pos1, $pos2 - $pos1 );
					$extra = substr( $line, $pos3 );

					/* @formatter:off */
					if ( !is_file( $incFile = $tplPath . '/' . $path ) ) {
						throw new \InvalidArgumentException( sprintf(
							"Missing partial file!\nAsset:   %s\nLine:    %s\nPartial: %s",
							$tplFile,
							trim( $line ),
							$incFile,
						));
					}
					/* @formatter:on */

					$lines = file( $incFile );
					$line = $indent . implode( $indent, $lines ) . $extra;
				}
			}
			unset( $line );
			$out = sprintf( '/* DO NOT EDIT - AUTO-GENERATED FROM: %s */', substr( $tplFile, strlen( ABSPATH ) ) ) . "\n";
			$out .= implode( '', $srcBody );
			file_put_contents( $absSrc, $out );
			static::$Settings->adminNotice( $notice, sprintf( '<a href="%s">%s</a>', $absSrc, basename( $absSrc ) ) );

			// -------
			// Minify?
			$isMinify = 'css' === $info['extension'] && static::$Factory->get( 'ast_minify_css' );
			$isMinify |= 'js' === $info['extension'] && static::$Factory->get( 'ast_minify_js' );

			if ( $isMinify ) {
				$Minifier = ( 'js' === $info['extension'] ) ? new Minify\JS() : new Minify\CSS();
				$Minifier->addFile( $absSrc )->minify( $absMin );
				static::$Settings->adminNotice( $notice, sprintf( '<a href="%s">%s</a>', $absMin, basename( $absMin ) ) );
			}
			else {
				copy( $absSrc, $absMin ); // Always create min file!
			}

			// -------
			// Add headers
			if ( $header = @file_get_contents( "{$tplPath}/_header.{$info['extension']}" ) ?: '' ) {
				file_put_contents( $absSrc, $header . file_get_contents( $absSrc ) );
				file_put_contents( $absMin, $header . file_get_contents( $absMin ) );
			}
		}

		// Force minified in dev mode?
		if ( static::$Factory->get( 'ast_force_min' ) ) {
			$info['location'] = '/' . $info['min'];
		}

		return $info;
	}
}
