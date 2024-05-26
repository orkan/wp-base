<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
namespace Orkan\WP\Base\Utils;

use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;
use Orkan\Config;

/**
 * Build assets.
 *
 * @author Orkan <orkans+wpbase@gmail.com>
 */
class Asset
{
	use Config;

	/* @formatter:off */
	protected $stats = [
		'rebuild' => [], // Absolute paths to built assets
	];
	/* @formatter:on */

	/**
	 * Setup.
	 */
	public function __construct( array $cfg = [] )
	{
		$this->cfg = $cfg;
		$this->merge( self::defaults() );
	}

	/**
	 * Get default config.
	 */
	private function defaults()
	{
		/*
		 * [assets]
		 * Relative path from WP root to [assets] dir
		 *
		 * [version]
		 * Version string to be appended to asset location
		 * Leave empty for WP version
		 *
		 * [abspath]
		 * Absolute path to WordPress installation dir
		 *
		 * [debug]
		 * Enqueue unminified file?
		 *
		 * [build]
		 * Build files from sources? By default only asset info is computed
		 * Note that additional cfg[rebuild_css|rebuild_js] is required to actually ran the build proccess
		 *
		 * [rebuild_css|rebuild_js]
		 * Select which build to perform. Requires cfg[build]
		 *
		 * [minify_css|minify_js]
		 * Allow miniffy?
		 *
		 * [force_min]
		 * Use minified version in [debug] mode. By default the [src] version is used in [location].
		 *
		 * [filter]
		 * CSS/JS filter callback.
		 *
		 * @formatter:off */
		return [
			'assets'      => 'wp-content/plugins/{plugin}/assets',
			'version'     => '',
			'abspath'     => @constant( 'ABSPATH' ),
			'debug'       => @constant( 'WP_DEBUG' ),
			'build'       => @constant( 'WP_DEBUG' ),
			'rebuild_css' => @constant( 'ORK_ASSET_REBUILD_CSS' ),
			'rebuild_js'  => @constant( 'ORK_ASSET_REBUILD_JS' ),
			'minify_css'  => @constant( 'ORK_ASSET_MINIFY_CSS' ),
			'minify_js'   => @constant( 'ORK_ASSET_MINIFY_JS' ),
			'force_min'   => @constant( 'ORK_ASSET_FORCE_MIN' ),
			'filter'      => null,
		];
		/* @formatter:on */
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
	public function info( string $asset ): array
	{
		$info = pathinfo( $asset );
		$info['dir'] = $this->get( 'assets' ) . '/' . $info['dirname'];
		$info['src'] = sprintf( '%s/%s.%s', $info['dir'], $info['filename'], $info['extension'] );
		$info['min'] = sprintf( '%s/%s.min.%s', $info['dir'], $info['filename'], $info['extension'] );
		$info['handle'] = 'ork-' . $info['filename'];
		$info['location'] = '/' . ( $this->get( 'debug' ) ? $info['src'] : $info['min'] ); // Must start with / - required by WP
		$info['version'] = $this->get( 'version' );

		if ( $this->get( 'build' ) ) {
			$info = $this->build( $info );
		}

		return $info;
	}

	/**
	 * Build & minify asset.
	 *
	 * @param array $info Asset info from Asset::info()
	 * @return array Asset file info (updated)
	 */
	protected function build( array $info ): array
	{
		$isRebuild = 'css' === $info['extension'] && $this->get( 'rebuild_css' );
		$isRebuild |= 'js' === $info['extension'] && $this->get( 'rebuild_js' );

		if ( $isRebuild ) {
			$absDir = $this->get( 'abspath' ) . $info['dir'];
			$absSrc = $absDir . '/' . basename( $info['src'] ); // might be missing!
			$absMin = $absDir . '/' . basename( $info['min'] ); // might be missing!

			$tplPath = $absDir . '/src';
			$tplFile = $tplPath . '/' . $info['basename'];

			/* @formatter:off */
			if ( !is_dir( $absDir ) ) {
				throw new \InvalidArgumentException( strtr( <<<EOT
					Assets [src] dir not found: "{dir}". Please check: 
					cfg[abspath]: "{abs}"
					cfg[assets]: "{loc}"
					EOT, [
					'{dir}' => $absDir,
					'{abs}' => $this->get( 'abspath' ),
					'{loc}' => $this->get( 'assets' ),
				]));
			}
			if ( !is_file( $tplFile ) ) {
				throw new \InvalidArgumentException( strtr( <<<EOT
					Asset file "{file}" not found in "{path}"
					EOT, [
					'{file}' => $info['basename'],
					'{path}' => $tplPath,
				]));
			}
			/* @formatter:on */

			// ---------------------------------------------------------------------------------------------------------
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
						throw new \RuntimeException( strtr( <<<EOT
							Missing partial file!
							Asset:   {out},{pos}
							Partial: {src}
							EOT, [
							'{out}' => $tplFile,
							'{pos}' => trim( $line ),
							'{src}' => $incFile,
						]));
					}
					/* @formatter:on */

					$lines = file( $incFile );
					$line = $indent . implode( $indent, $lines ) . $extra;
				}
			}
			unset( $line );
			$out = substr( $tplFile, strlen( $this->get( 'abspath' ) ) ); // cut abspath
			$out = "/* DO NOT EDIT - AUTO-GENERATED FROM: $out */\n";
			$out .= implode( '', $srcBody );

			// ---------------------------------------------------------------------------------------------------------
			// Filter?
			if ( is_callable( $callback = $this->get('filter') ) ) {
				$out = call_user_func( $callback, $out, $info['extension'] );
			}

			file_put_contents( $absSrc, $out );
			$this->stats['rebuild'][] = $absSrc;

			// ---------------------------------------------------------------------------------------------------------
			// Minify?
			$isMinify = 'css' === $info['extension'] && $this->get( 'minify_css' );
			$isMinify |= 'js' === $info['extension'] && $this->get( 'minify_js' );

			if ( $isMinify ) {
				$Minifier = ( 'js' === $info['extension'] ) ? new JS() : new CSS();
				$Minifier->addFile( $absSrc )->minify( $absMin );
				$this->stats['rebuild'][] = $absMin;
			}
			else {
				copy( $absSrc, $absMin ); // Always create min file!
			}

			// ---------------------------------------------------------------------------------------------------------
			// Add headers
			if ( $header = @file_get_contents( "{$tplPath}/_header.{$info['extension']}" ) ?: '' ) {
				file_put_contents( $absSrc, $header . file_get_contents( $absSrc ) );
				file_put_contents( $absMin, $header . file_get_contents( $absMin ) );
			}
		}

		// Force minified in dev mode?
		if ( $this->get( 'force_min' ) ) {
			$info['location'] = '/' . $info['min'];
		}

		return $info;
	}

	/**
	 * Get stats.
	 */
	public function stats( string $key = '' )
	{
		return $key ? $this->stats[$key] : $this->stats;
	}
}
