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
	const NAME = 'Ork Base';
	const VERSION = '1.0.2';

	/**
	 * Error codes.
	 */
	const AJAX_ERROR = 400; // HTTP Bad Request

	/* @formatter:off */

	/**
	 * Custom nonces.
	 */
	const NONCE = [
		'form'  => [ 'name' => 'ork_nonce', 'action' => 'ork_form_submit' ],
		'ajax'  => [ 'name' => 'nonce'    , 'action' => 'ork_ajax_action' ],
	];

	/* @formatter:on */

	/**
	 * @var Factory
	 */
	protected static $Factory;

	/**
	 * Setup.
	 */
	public function __construct( Factory $Factory )
	{
		static::$Factory = $Factory;
	}

	/**
	 * Run plugin.
	 */
	public static function run(): void
	{
		static $ready;

		if ( isset( $ready ) ) {
			return;
		}

		$skip = false;
		$skip |= !defined( 'ABSPATH' );
		$skip |= !defined( 'WP_PLUGIN_DIR' );
		$skip |= !defined( 'WP_SITEURL' ) && !defined( 'WPINC' ); // we need one of: WP_SITEURL || get_site_url()

		if ( $skip ) {
			return;
		}

		static::$Factory->merge( self::defaults() );

		// In STANDALONE mode WP environment isn't available (WPINC == false)
		if ( defined( 'WPINC' ) ) {

			if ( is_admin() ) {
				$pl = static::$Factory->get( 'basename' );
				add_action( "activate_$pl", [ static::class, 'actionActivate' ] );
				add_action( "deactivate_$pl", [ static::class, 'actionDeactivate' ] );
				add_action( 'admin_enqueue_scripts', [ static::class, 'actionAdminEnqueueScripts' ] );
			}
		}

		$ready = true;
	}

	/*
	 * Configure plugin.
	 *
	 * NOTE:
	 * ABSPATH - indicates that wp-config.php file was loaded
	 * WPINC - indicates that wp-settings.php file was loaded
	 *
	 * @see plugin_basename()
	 * @see plugins_url()
	 */
	private static function defaults(): array
	{
		$Reflector = new \ReflectionClass( static::class );
		$classFile = $Reflector->getFileName();

		$host = defined( 'WP_SITEURL' ) ? WP_SITEURL : get_site_url();
		$plug = substr( WP_PLUGIN_DIR, strlen( ABSPATH ) ); // plugins dir path @see plugin_basename()
		$base = basename( dirname( $classFile, 2 ) ); // plugin dir name
		$path = $plug . '/' . $base; // plugin dir path
		$cfg = [];

		// Plugin name. Register plugin pages, etc...
		$cfg['name'] = static::NAME;

		// Plugin version.
		$cfg['version'] = static::VERSION;

		// Check wether the plugin is in DEV (SRC) / PROD (REL) environment
		$cfg['debug'] = static::VERSION === '@' . 'Version@';

		// Premium version?
		$cfg['premium'] = true;

		// Used to identify the plugin, eg. plugin_dir/plugin.php
		$cfg['basename'] = $base . '/plugin.php';

		// Plugin dir from root, eg. wp-content/plugins/{plugin}
		$cfg['plugin_loc'] = $path;

		// Assets: wp-content/plugins/{plugin}/assets
		$cfg['assets_loc'] = $path . '/assets';

		// Plugin dir absolute path, eg. /var/www/html/wp-content/plugins/{plugin}
		$cfg['plugin_dir'] = ABSPATH . $path;

		// Configs: /var/www/html/wp-content/plugins/{plugin}/config
		$cfg['config_dir'] = ABSPATH . $path . '/config';

		// Plugin dir url, eg. http://example.com/wp-content/plugins/{plugin}
		$cfg['plugin_url'] = $host . '/' . $path;

		return $cfg;
	}

	/**
	 * Activate plugin.
	 * @link https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/
	 */
	public static function actionActivate(): void
	{
	}

	/**
	 * Deactivate plugin.
	 * @link https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/
	 */
	public static function actionDeactivate(): void
	{
	}

	public static function actionAdminEnqueueScripts( $page ): void
	{
		wp_add_inline_script( 'jquery-migrate', 'jQuery.migrateMute=true;jQuery.migrateTrace=false;', 'before' );
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
	public static function ajaxCheckNonce(): void
	{
		if ( false === check_ajax_referer( static::NONCE['ajax']['action'], static::NONCE['ajax']['name'], false ) ) {
			throw new \Exception( 'Link expired', static::AJAX_ERROR );
		}
	}

	/**
	 * @see wp_json_encode() -> return string
	 * @see wp_send_json() -> header + wp_die()
	 * @see wp_send_json_error() -> wp_die()
	 */
	public static function ajaxSetExceptionHandler(): void
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
