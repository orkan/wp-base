<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
namespace Orkan\WP\Base;

use Orkan\Utils;

/**
 * Plugin: Base.
 *
 * @author Orkan <orkans+wpbase@gmail.com>
 */
class Plugin
{
// 	const SLUG = 'base';

	/**
	 * Location to current entry point plugin file loaded by WP.
	 * @see wp-settings.php,424
	 */
	protected $plugin;

	/**
	 * Connected?
	 */
	private $ready = false;

	/**
	 * Services:
	 */
	protected $Factory;

	/**
	 * Setup.
	 */
	public function __construct( Factory $Factory )
	{
		$this->Factory = $Factory;
		$this->plugin = $GLOBALS['plugin'] ?? '';

		if ( !$this->plugin ) {
			throw new \InvalidArgumentException( 'Missing $GLOBALS[plugin] var!' );
		}
	}

	/**
	 * Run plugin.
	 */
	public function run(): void
	{
		if ( $this->ready ) {
			return;
		}

		$skip = false;
		$skip |= !defined( 'ABSPATH' );
		$skip |= !defined( 'WP_PLUGIN_DIR' );
		$skip |= !defined( 'WP_SITEURL' ) && !defined( 'WPINC' ); // we need one of: WP_SITEURL || get_site_url()

		if ( $skip ) {
			return;
		}

		$this->Factory->merge( self::defaults() );

		// In STANDALONE mode WP environment isn't available (WPINC == false)
		if ( defined( 'WPINC' ) ) {
			if ( is_admin() ) {
				$pl = $this->Factory->get( 'plu_basename' );
				add_action( "activate_$pl", [ $this, 'actionActivate' ] );
				add_action( "deactivate_$pl", [ $this, 'actionDeactivate' ] );
				add_action( 'admin_enqueue_scripts', [ $this, 'actionAdminEnqueueScripts' ] );
			}
		}

		$this->ready = true;
	}

	/**
	 * Configure plugin.
	 *
	 * NOTE:
	 * ABSPATH - indicates that wp-config.php file was loaded
	 * WPINC - indicates that wp-settings.php file was loaded
	 *
	 * @see plugin_basename()
	 * @see plugins_url()
	 */
	private function defaults(): array
	{
		$info = pathinfo( $this->plugin );
		$host = defined( 'WP_SITEURL' ) ? WP_SITEURL : get_site_url();
		$plug = substr( WP_PLUGIN_DIR, strlen( ABSPATH ) ); // plugins dir path @see plugin_basename()
		$base = basename( $info['dirname'] ); // plugin dir name
		$path = $plug . '/' . $base; // plugin dir path

		/*
		 * [plu_basename]
		 * Used to identify the plugin, eg. plugin_dir/plugin.php
		 *
		 * [plu_plugin_loc]
		 * Plugin dir from root, eg. wp-content/plugins/{plugin}
		 *
		 * [plu_assets_loc]
		 * Assets: wp-content/plugins/{plugin}/assets
		 *
		 * [plu_plugin_dir]
		 * Plugin dir absolute path, eg. /var/www/html/wp-content/plugins/{plugin}
		 *
		 * [plu_config_dir]
		 * Configs: /var/www/html/wp-content/plugins/{plugin}/config
		 *
		 * [plu_plugin_url]
		 * Plugin dir url, eg. http://example.com/wp-content/plugins/{plugin}
		 *
		 * [plu_ajax_error]
		 * Ajax error code. Def. [400] HTTP Bad Request
		 *
		 * [plu_ajax_nonce_name]
		 * Plugin unique nonce name to be used in GET/POST param
		 *
		 * [plu_ajax_nonce_action]
		 * WP can generate different nonces for different actions
		 *
		 * [plu_name]
		 * Ueed in:
		 * - Dashboard > Settings
		 * - Transients label
		 *
		 * [plu_version]
		 * Ueed in: Assets
		 *
		 * [plu_slug]
		 * Used to generate plugin unique key identifiers
		 *
		 * [plu_debug]
		 * Is development version?
		 * Ueed in: ?
		 *
		 * [plu_premium]
		 * Used in: Settings page: replace inputs[premium] with <span>
		 *
		 * @formatter:off */
		return [
			// Paths
			'plu_basename'   => $base . '/' . $info['basename'],
			'plu_plugin_loc' => $path,
			'plu_assets_loc' => $path . '/assets',
			'plu_plugin_dir' => ABSPATH . $path,
			'plu_config_dir' => ABSPATH . $path . '/config',
			'plu_plugin_url' => $host . '/' . $path,
			// Ajax
			'plu_ajax_error'        => 400,
			'plu_ajax_nonce_name'   => 'nonce',
			'plu_ajax_nonce_action' => 'ork_ajax_action',
			// Other
			'plu_name'          => $this->Factory::NAME,
			'plu_version'       => $this->Factory::VERSION,
			'plu_slug'          => $this->Factory::SLUG,
			'plu_debug'         => $this->Factory::VERSION === '@' . 'Version@',
			'plu_premium'       => true,
			'plu_assets_filter' => [ $this, 'filterAssets' ],
		];
		/* @formatter:on */
	}

	/**
	 * Activate plugin.
	 * @link https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/
	 */
	public function actionActivate(): void
	{
	}

	/**
	 * Deactivate plugin.
	 * @link https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/
	 */
	public function actionDeactivate(): void
	{
	}

	public function actionAdminEnqueueScripts( $page ): void
	{
		wp_add_inline_script( 'jquery-migrate', 'jQuery.migrateMute=true;jQuery.migrateTrace=false;', 'before' );
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
	 * @param string $data
	 * @param array  $options Extra options (
	 * [deps]     => Dependencies. Without -js, -css sufixes
	 * [media]    => CSS: media types like all|print|screen|orientation: portrait|max-width: 640px
	 * [data]     => JS: Inline script or array that will be converted to object: cfg[plu_js_var] = {data}
	 * [footer]   => JS: Enqueue in footer?
	 * [position] => JS: Enqueue data before|after script
	 * )
	 */
	public function enqueue( string $asset, array $args = [] ): array
	{
		$Settings = $this->Factory->Settings();
		$Asset = $this->Factory->Asset();

		$info = $Asset->info( $asset );

		foreach ( $Asset->stats( 'rebuild' ) as $path ) {
			$Settings->adminNotice( 'Rebuild', sprintf( '<a href="%s">%s</a>', $path, basename( $path ) ) );
		}

		/* @formatter:off */
		$args = array_merge([
			'var'      => $this->Factory->get( 'plu_slug' ),
			'deps'     => null,
			'data'     => null,
			'footer'   => true,
			'position' => 'before',
			'media'    => 'all',
		], $args );
		/* @formatter:on */

		if ( 'css' === $info['dirname'] ) {
			wp_enqueue_style( $info['handle'], $info['location'], $args['deps'], $info['version'], $args['media'] );
		}
		else {
			wp_enqueue_script( $info['handle'], $info['location'], $args['deps'], $info['version'], $args['footer'] );

			if ( is_array( $args['data'] ) ) {
				$args['data'] = sprintf( 'var %s = %s', $args['var'], json_encode( $args['data'] ) );
			}
			wp_add_inline_script( $info['handle'], $args['data'], $args['position'] );
		}

		return $info;
	}

	/**
	 * Callback: Asset filter.
	 */
	public function filterAssets( string $out, string $type ): string
	{
		if ( 'js' === $type ) {
			$out = preg_replace( '~\$plu(\W)~', $this->Factory->get( 'plu_slug' ) . '$1', $out );
		}

		return $out;
	}

	/**
	 * Check ajax nonce value.
	 *
	 * WARNING:
	 * Don't check nonces during plugin load, eg. in exception handler.
	 * Wait for WP to load functions from ajax-actions.php first!
	 *
	 * @throws \Exception On invalid nonce
	 */
	public function ajaxNonceCheck(): void
	{
		$name = $this->Factory->get( 'plu_ajax_nonce_name' );
		$action = $this->Factory->get( 'plu_ajax_nonce_action' );

		if ( false === check_ajax_referer( $action, $name, false ) ) {
			throw new \InvalidArgumentException( 'Link expired', $this->Factory->get( 'plu_ajax_error' ) );
		}
	}

	/**
	 * Handle Ajax errors & exceptions.
	 *
	 * @see wp_json_encode() -> return string
	 * @see wp_send_json() -> header + wp_die()
	 * @see wp_send_json_error() -> wp_die()
	 */
	public function ajaxExceptionHandle(): void
	{
		// Turn Errors into Exceptions
		set_error_handler( [ Utils::class, 'errorHandler' ] );

		// Log & Json error
		set_exception_handler( function ( \Throwable $E ) {
			error_log( $E );
			wp_send_json_error( $E->getMessage(), $E->getCode() ); // +die!
		} );

		ini_set( 'html_errors', 'Off' );
	}
}
