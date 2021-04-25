<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
namespace Orkan\WP\Base;

use Orkan\Input;
use Parsedown;

/**
 * Plugin: Settings.
 *
 * @author Orkan <orkans+wpbase@gmail.com>
 */
class Settings
{
	/**
	 * Settings page slug.
	 */
	const OPTIONS_PAGE = 'ork-settings';

	/**
	 * Settings DB key and Actions prefix.
	 */
	const OPTIONS_NAME = 'ork_settings';

	/**
	 * WP options DB table.
	 */
	const CACHE_PREFIX = 'ork_cache_';

	/**
	 * Short-live memory cache key.
	 */
	const TRANSIENT_PREFIX = 'ork_transcient_';

	/* @formatter:off */

	/**
	 * Transient messages.
	 * @see Settings::adminTransient()
	 */
	protected const TRANSIENTS = [
		'activate' => 'Please open {link} page to finish plugin activation!',
	];

	/**
	 * Ajax actions.
	 * @var array (
	 *   [id] => "action" used in add_action() as "wp_ajax_{action}" -or- "wp_ajax_nopriv_{action}"
	 *   ...
	 * )
	 */
	const ACTION = [];

	/**
	 * Cache keys.
	 * String used to create DB option name, internaly prefixed with:
	 * @see Settings::CACHE_PREFIX
	 */
	protected const CACHE = [
		'settings' => self::OPTIONS_NAME . '_inputs',
	];

	/**
	 * Action links.
	 * Re-use single plugin Settings page to render more pages.
	 *
	 * Icons:
	 * @link https://developer.wordpress.org/resource/dashicons
	 *
	 * @see Settings::toolsLink()
	 * @see Settings::toolsUrl()
	 *
	 * @var array (
	 * [title] => Action name displayed
	 * [icon]  => Add action icon to rendered link
	 * [menu]  => Add action link in Plugin Row Meta
	 * )
	 */
	protected static $tools = [
		'settings'   => [ 'title' => 'Settings'    , 'icon' => 'admin-generic'    , 'menu' => false ],
		'readme'     => [ 'title' => 'README'      , 'icon' => 'book'             , 'menu' => true  ],
		'config'     => [ 'title' => 'Config'      , 'icon' => 'info-outline'     , 'menu' => true  ],
	];

	/* @formatter:on */

	/**
	 * @see Settings::adminNotice()
	 */
	protected static $adminNotices = [];

	/**
	 * @var Factory
	 */
	protected static $Factory;

	/**
	 * @var Plugin
	 */
	protected static $Plugin;

	/**
	 * Setup.
	 */
	public function __construct( Factory $Factory )
	{
		static::$Factory = $Factory;
		static::$Plugin = $Factory->Plugin();
	}

	/**
	 * Register all hooks.
	 */
	public static function run(): void
	{
		static $ready;

		if ( $ready ) {
			return;
		}

		$isAjax = wp_doing_ajax();
		$isPage = !$isAjax && is_admin();

		if ( $isAjax && in_array( $_REQUEST['action'], static::ACTION ) ) {
			static::$Plugin->ajaxSetExceptionHandler();
		}
		elseif ( $isPage ) {
			add_action( 'admin_init', [ static::class, 'actionAdminInit' ] );
			add_action( 'admin_menu', [ static::class, 'actionAdminMenu' ] );

			// Tools
			$pl = static::$Factory->get( 'basename' );
			$hook = static::OPTIONS_NAME;
			add_action( "activate_$pl", [ static::class, 'actionActivate' ] );
			add_action( "deactivate_$pl", [ static::class, 'actionDeactivate' ] );
			add_action( 'admin_notices', [ static::class, 'actionAdminNotices' ] );
			add_action( 'plugin_row_meta', [ static::class, 'actionPluginRowMeta' ], 10, 2 );
			add_filter( "plugin_action_links_$pl", [ static::class, 'filterActionLinks' ] );
			add_filter( $hook . '_inputs', [ static::class, 'filterInputFields' ] );
			add_action( $hook . '_submit_settings', [ static::class, 'actionSubmitSettings' ] );
			add_action( $hook . '_define_settings', [ static::class, 'actionDefineSettings' ], 555 );
			add_action( $hook . '_render_settings', [ static::class, 'actionRenderSettings' ] );
			add_action( $hook . '_render_upgrade', [ static::class, 'actionRenderUpgrade' ] );
			add_action( $hook . '_render_readme', [ static::class, 'actionRenderReadme' ] );
			add_action( $hook . '_render_develop', [ static::class, 'actionRenderDevelop' ] );
			add_action( $hook . '_render_config', [ static::class, 'actionRenderConfig' ] );
		}

		$ready = true;
	}

	/**
	 * Get defaults.
	 */
	private static function defaults(): array
	{
		/* @formatter:off */
		return [
		];
		/* @formatter:on */
	}

	/**
	 * Show label in Dashboard > Settings menu and define render page callback.
	 */
	public static function actionAdminMenu(): void
	{
		$label = static::$Factory->get( 'name' );
		add_options_page( $label, $label, 'manage_options', static::OPTIONS_PAGE, [ static::class, 'actionRender' ] );
	}

	/**
	 * Activate plugin.
	 */
	public static function actionActivate()
	{
		static::adminTransient( 'activate', [ 'tokens' => [ '{link}' => static::toolLink( 'settings' ) ] ] );
	}

	/**
	 * Deactivate plugin.
	 */
	public static function actionDeactivate()
	{
		static::cacheFlush();
	}

	/**
	 * Settings page - init.
	 * Enqueue scripts or proceed with FORM submit.
	 *
	 * NOTE:
	 * Settings page uses separate page (options.php) to submit, while Action pages uses "render" page to submit FORM.
	 *
	 * Hook names:
	 * @see Settings::getHookName()
	 *
	 * Hooks - render:
	 * @see Settings::actionRender()
	 */
	public static function actionAdminInit(): void
	{
		if ( static::isSettingsSubmit() ) {
			do_action( static::getHookName( 'submit' ) ); // Current Action: 'settings' !
		}
		elseif ( static::isSettingsRender() ) {
			do_action( static::getHookName( 'define' ) );
		}
		elseif ( static::isToolRender() ) {

			if ( static::isToolSubmit() ) {
				do_action( static::getHookName( 'submit', false ) ); // All Actions
				do_action( static::getHookName( 'submit' ) ); // Current Actions
			}

			// Always call 'define' hook on Action pages, since FORM url === Page url
			do_action( static::getHookName( 'define', false ) );
			do_action( static::getHookName( 'define' ) );
		}
	}

	/**
	 * Settings/Action page - render.
	 *
	 * NOTE:
	 * Settings page is used to render sub-pages (aka. actions), without actually displaying the settings FORM,
	 * like: upgrading, demos, etc...
	 *
	 * Hook name format:
	 * ork_settings_render_settings
	 * ork_settings_render_config
	 * etc...
	 *
	 * Hooks - submit, define:
	 * @see Settings::actionAdminInit()
	 */
	public static function actionRender()
	{
		echo '<div id="ork-settings-actionRender">';
		echo static::toolHeader();
		do_action( static::getHookName( 'render' ) );
		echo '</div>';
	}

	/**
	 * Is Settings page - render?
	 */
	public static function isSettingsRender()
	{
		$pagenow = basename( $_SERVER['PHP_SELF'] );
		$page = $_GET['page'] ?? '';
		$action = $_GET['action'] ?? 'settings';

		$result = 'options-general.php' === $pagenow;
		$result &= static::OPTIONS_PAGE === $page;
		$result &= 'settings' === $action;

		return $result;
	}

	/**
	 * Is Settings page - submit?
	 *
	 * CAUTION:
	 * Submit <FORM action="options.php"> !== Page URL: "options-general.php"
	 */
	public static function isSettingsSubmit()
	{
		$pagenow = basename( $_SERVER['PHP_SELF'] );
		$page = $_POST['option_page'] ?? '';
		$action = $_POST['action'] ?? '';

		$result = 'options.php' === $pagenow;
		$result &= static::OPTIONS_PAGE === $page;
		$result &= 'update' === $action;

		return $result;
	}

	/**
	 * Is Action page - render?
	 *
	 * CAUTION:
	 * That includes 'submit' action too, since the FORM action url is the same
	 */
	public static function isToolRender()
	{
		$pagenow = basename( $_SERVER['PHP_SELF'] );
		$page = $_GET['page'] ?? '';
		$action = $_GET['action'] ?? '';

		$result = 'options-general.php' === $pagenow;
		$result &= static::OPTIONS_PAGE === $page;
		$result &= isset( static::$tools[$action] );

		return $result;
	}

	/**
	 * Is Action page - submit?
	 *
	 * CAUTION:
	 * Submit <FORM action="options-general.php"> === Page URL: "options-general.php"
	 */
	public static function isToolSubmit()
	{
		$result = self::isToolRender();
		$result &= 'POST' === $_SERVER['REQUEST_METHOD'];

		return $result;
	}

	/**
	 * Build current action hook name.
	 *
	 * Hook name format:
	 * ork_settings_submit (submit, action: all)
	 * ork_settings_submit_config (submit, action: config)
	 * ork_settings_define_config (init, action: config)
	 * ork_settings_submit_settings (submit, action: settings)
	 * etc...
	 */
	public static function getHookName( string $type, bool $withAction = true ): string
	{
		$action = $withAction ? '_' . static::getAction() : '';
		return static::OPTIONS_NAME . '_' . $type . $action;
	}

	/**
	 * Get current Action name.
	 */
	public static function getAction(): string
	{
		$action = $_GET['action'] ?? '';
		return isset( static::$tools[$action] ) ? $action : 'settings';
	}

	/**
	 * Add additional links below each plugin on the Plugins page.
	 * @link https://developer.wordpress.org/reference/hooks/plugin_row_meta/
	 *
	 * @param string $links  An array of the plugin's metadata, including the version, author, author URI, and plugin URI
	 * @param string $file   Path to the plugin file relative to the plugins directory
	 * @param array  $data   An array of plugin data
	 * @param string $status Status filter currently applied to the plugin list. Possible values are: 'all', 'active',
	 *                       'inactive', 'recently_activated', 'upgrade', 'mustuse', 'dropins', 'search', 'paused',
	 *                       'auto-update-enabled', 'auto-update-disabled'
	 */
	public static function actionPluginRowMeta( array $links, string $file ): array
	{
		if ( $file === static::$Factory->get( 'basename' ) ) {
			foreach ( static::$tools as $k => $v ) {
				if ( $v['menu'] ) {
					$links[] = static::toolLink( $k, true );
				}
			}
		}

		return $links;
	}

	/**
	 * Register new tool.
	 */
	public static function toolRegister( $id, $title, $icon, $menu ): void
	{
		static::$tools[$id] = [ 'title' => $title, 'icon' => $icon, 'menu' => $menu ];
	}

	/**
	 * Settings page - init.
	 */
	public static function actionDefineSettings(): void
	{
		//$Asset->enqueue( 'css/.css' ); // Settings::form() -> $isRender
	}

	/**
	 * Register FORM fields.
	 */
	protected static function formRegister(): void
	{
		// Refresh DB cache
		static::cacheFlush();

		try {
			// Register FORM fields so they can be auto-rendered
			static::form();
		}
		catch ( \Throwable $E ) {
			error_log( $E );
			static::adminNotice( 'FORM register', $E->getMessage(), [ 'type' => 'error' ] );
		}
	}

	/**
	 * Register FORM inputs.
	 */
	protected static function formRender(): void
	{
		// Echo all notices gathered during page construct. Note, this action is after the 'admin_notices' hook!
		echo static::adminNotices();

		echo '<form method="post" action="options.php">';
		submit_button();
		settings_fields( static::OPTIONS_PAGE );
		do_settings_sections( static::OPTIONS_PAGE );
		submit_button();
		echo '</form>';
	}

	/**
	 * Settings page - render.
	 *
	 * @see add_options_page()
	 * @see Settings::actionAdminMenu()
	 */
	public static function actionRenderSettings(): void
	{
		static::formRegister();
		static::formRender();
	}

	/**
	 * Settings page - submit.
	 *
	 * NOTE:
	 * The $_POST[] values aint saved in DB yet!
	 */
	public static function actionSubmitSettings(): void
	{
		// Register FORM inputs so they can be auto-saved
		static::form();

	/**
	 * -------------------------------------------------------------------------------------------------------------
	 * Refresh DB cache.
	 * @see Settings::actionRenderSettings()
	 * static::flushCache();
	 */
	}

	/**
	 * Cache: get, set, delete.
	 *
	 * A simple implementation that forces only array data to be stored (to preserve data types)
	 * Doesn't provide any expiration setting meaning is set forever until deleted
	 *
	 * @link https://wordpress.stackexchange.com/questions/102555/is-get-option-function-cached
	 * @link https://www.php-fig.org/psr/psr-6/
	 * @link https://scotty-t.com/2012/01/20/wordpress-memcached/
	 * @link https://developer.wordpress.org/reference/classes/wp_object_cache
	 *
	 * @see wp_cache_add()
	 */
	protected static function cacheId( string $key ): string
	{
		return static::CACHE_PREFIX . $key;
	}

	public static function cacheGet( ?string $key = null, ?array $default = null ): ?array
	{
		return $key ? get_option( static::cacheId( $key ), $default ) : null; // unserialize
	}

	public static function cacheSet( ?string $key = null, array $data, bool $autoload = false ): bool
	{
		return $key ? update_option( static::cacheId( $key ), $data, $autoload ) : false; // serialize
	}

	public static function cacheDelete( ?string $key = null ): bool
	{
		return $key ? delete_option( static::cacheId( $key ) ) : false;
	}

	/**
	 * Clear WP cache.
	 *
	 * DEBUG:
	 * Add admin notice if cache was deleted
	 *
	 * NOTE:
	 * [flush_rewrite_rules]
	 * We cannot use directly activation/deactivation hooks since these hooks makes the header() redirect folowed by exit()
	 * To make the flush_rewrite_rules() work, we need FULL page reload.
	 * @link https://wordpress.stackexchange.com/questions/291011/save-permalinks-does-more-than-flush-rewrite-rules
	 * @see get_option('rewrite_rules')
	 * @see flush_rewrite_rules()
	 *
	 * @param string|array $ids Previously used to cacheSet()
	 */
	public static function cacheDeleteAll( $ids ): void
	{
		foreach ( (array) $ids as $id ) {
			$result = static::cacheDelete( $id );
			$result && static::adminNotice( 'Flush', static::cacheId( $id ) );
		}
	}

	/**
	 * Clear Settings cache.
	 */
	public static function cacheFlush( string $name = '' ): void
	{
		static::cacheDeleteAll( static::CACHE[$name] ?? static::CACHE ?? null);
	}

	/**
	 * Print some debug info.
	 */
	public static function actionRenderConfig(): void
	{
		echo '<ol>';
		echo '<li><a href="#config">Plugin::cfg()</a></li>';
		echo '<li><a href="#constants">CONSTANTS</a></li>';
		echo '<li><a href="#server">$_SERVER</a></li>';
		echo '<li><a href="#cookie">$_COOKIE</a></li>';
		echo '</ol>';
		echo '<pre>';

		// -------------------------------------------------------------------------------------------------------------
		// Plugin
		printf( '<a id="config">%s::cfg() </a>', get_class( static::$Plugin ) );
		$cfg = static::$Factory->cfg();
		ksort( $cfg );
		print_r( $cfg );

		// -------------------------------------------------------------------------------------------------------------
		echo '<a id="constants">CONSTANTS:</a><table>';
		foreach (
		/* @formatter:off */
		[
			'WP_SITEURL',
			'WP_HOME',
			'ABSPATH',
			'WP_CONTENT_DIR',
			'WP_PLUGIN_DIR',
			'WPINC',
			'DB_NAME',
			'DB_USER',
			'DB_HOST',
			'WP_DEBUG',
			'WP_DEBUG_DISPLAY',
			'SCRIPT_DEBUG',
			'WP_DEBUG_LOG',
			'ORK_STANDALONE',
		]
		/* @formatter:on */
		as $v ) {
			printf( "<tr><td>    </td><td>%s</td><td>=></td><td>%s</td></tr>\n", $v, @constant( $v ) ?: 'undefined' );
		}
		echo "</table>\n";

		// -------------------------------------------------------------------------------------------------------------
		echo '<a id="server">$_SERVER</a> ';
		print_r( $_SERVER );

		// -------------------------------------------------------------------------------------------------------------
		echo '<a id="cookie">$_COOKIE</a> ';
		print_r( $_COOKIE );

		echo '</pre>';
	}

	/**
	 * Parse and print README.md
	 */
	public static function actionRenderReadme(): void
	{
		$pluginUrl = static::$Factory->get( 'plugin_url' );
		$pluginDir = static::$Factory->get( 'plugin_dir' );

		$text = file_get_contents( "$pluginDir/README.md" );
		$text = ( new Parsedown() )->text( $text );
		$text = preg_replace( '~(?:src|href)=[\'"](?!http://|https://|/)\K([^\'"]*)~i', "$pluginUrl/$1", $text );
		echo $text;
	}

	/**
	 * Get default javascript object.
	 *
	 * CAUTION:
	 * This method must stay public to allow access from other modules,
	 * however it is PRIVATE since it creates private JS object!
	 * You must overwrite this method in derived class otherwise you will get the AirDB JS object.
	 */
	public static function jsObject( array $data = [] ): array
	{
		/* @formatter:off */
		return array_replace_recursive([
			'l10n' => [
				'error' => 'An error occured',
				'wait'  => 'Wait...',
			],
			'url' => admin_url( 'admin-ajax.php' ),
			'nonce' => [
				'name'   => static::$Plugin::NONCE['ajax']['name'],
				'action' => wp_create_nonce( static::$Plugin::NONCE['ajax']['action'] ),
			],
			'debug' => (bool) static::$Factory->get( 'debug' ),
		], $data );
		/* @formatter:on */
	}

	/**
	 * Add action links.
	 * @link https://developer.wordpress.org/reference/hooks/plugin_action_links_plugin_file/
	 */
	public static function filterActionLinks( array $actions ): array
	{
		$actions['settings'] = static::toolLink( 'settings' );
		return $actions;
	}

	/**
	 * Get Settings tools page header title.
	 */
	public static function toolHeader(): string
	{
		$action = static::getAction();

		/* @formatter:off */
		return strtr( <<<HTML
			<h1>
				<span class="dashicons dashicons-{icon}" style="font-size:1.4rem;width:1rem;"></span>
				<a href="{url}">{name} - {title}</a>
			</h1>
			HTML, [
			'{name}'  => static::$Factory->get( 'name' ),
			'{url}'   => static::toolUrl( $action ),
			'{icon}'  => static::$tools[$action]['icon'],
			'{title}' => static::$tools[$action]['title'],
		]);
		/* @formatter:on */
	}

	/**
	 * Get Settings tools page link.
	 */
	public static function toolLink( string $action = '', bool $addIcon = false ): string
	{
		$action = $action ?: 'settings';
		$icon = static::$tools[$action]['icon'];

		/* @formatter:off */
		return strtr( '<a href="{action}" style="white-space:nowrap;">{icon}{title}</a>', [
			'{action}' => static::toolUrl( $action ),
			'{title}'  => static::$tools[$action]['title'],
			'{icon}'   => $addIcon ? "<span class=\"dashicons dashicons-{$icon}\"></span>" : '',
		]);
		/* @formatter:on */
	}

	/**
	 * Get Settings page url.
	 * @see Settings::TOOLS
	 *
	 * @param  string $action Current action
	 * @return string         Full admin url to settings action page
	 */
	public static function toolUrl( string $action, bool $long = true ): string
	{
		/* @formatter:off */
		$query = http_build_query([
			'page'   => static::OPTIONS_PAGE,
			'action' => $action
		], '', $long ? '&amp;' : '&' );
		/* @formatter:on */

		return admin_url( 'options-general.php?' . $query );
	}

	/**
	 * Load FORM Sections from config file.
	 */
	public static function getSections(): array
	{
		return apply_filters( 'ork_settings_sections', require static::$Factory->get( 'config_dir' ) . '/settings_sections.php' );
	}

	/**
	 * Load FORM Fields from config file.
	 * Cache results, since it might be used by static::getOption() multiple times for different
	 *
	 * @see Settings::getOption()
	 */
	public static function getInputFields( $key = null ): array
	{
		$cKey = static::CACHE['settings'] ?? null;
		$fields = static::cacheGet( $cKey );

		if ( !isset( $fields ) ) {
			$fields = require static::$Factory->get( 'config_dir' ) . '/settings_inputs.php';

			// Add more...
			$fields = apply_filters( static::OPTIONS_NAME . '_inputs', $fields );

			// Add required field attrs (name, type)
			Input::fieldsPrepare( $fields );
			static::cacheSet( $cKey, $fields );
		}

		return $key ? $fields[$key] : $fields;
	}

	/**
	 * Update inputs before displaying.
	 * @see Settings::getInputFields()
	 */
	public static function filterInputFields( array $fields ): array
	{
		/* @formatter:off */
		$fields['group_b1']['items']['group_b1_nested']['desc'] = 'Items generated in ' . __METHOD__;
		$fields['group_b1']['items']['group_b1_nested']['inputs'] = [
			'group_b1_nested_text' => [
				'type' => 'text',
				'hint' => 'Im [text] inside [Groupped/Nested]',
			],
			'group_b1_nested_cbox' => [
				'type' => 'checkbox',
				'tag'  => 'Im [checkbox] inside [Groupped/Nested]',
			],
		];
		/* @formatter:on */

		return $fields;
	}

	/**
	 * Build FORM input name.
	 * Get namespaced name, so the FORM values can be auto saved by WP in DB options table under one record (array)
	 *
	 * @param  string $name Field name
	 * @return string FORM <input> name
	 */
	public static function buildInputName( string $name ): string
	{
		return static::OPTIONS_NAME . "[$name]";
	}

	/**
	 * Register Settings FORM sections & fields.
	 *
	 * This method is called when:
	 * 1. The form is submited
	 *    $pagenow == 'options.php'
	 *    FORM values are in $POST array
	 * 2. The FORM is rendered
	 *    $pagenow == 'options-general.php'
	 *    FORM values are in DB now!
	 */
	protected static function form()
	{
		// -------------------------------------------------------------------------------------------------------------
		// Sections:
		$sections = static::getSections();
		Input::sort( $sections );

		// Register sections
		foreach ( $sections as $key => $val ) {
			/* @formatter:off */
			add_settings_section(
				$key,
				$val['title'],
				function() use( $val ) {
					printf( '<div class="form-text">%s</div>', $val['desc'] ?? '' );
				},
				static::OPTIONS_PAGE
			);
			/* @formatter:on */
		}

		// -------------------------------------------------------------------------------------------------------------
		// Inputs:
		$fields = static::getInputFields();
		$jsData = [];
		$premium = static::$Factory->get( 'premium' );

		foreach ( Input::fieldsEach( $fields, true ) as $name => &$input ) {
			// Create namespaced input name: <input name="ork_settings[name]" ... >
			$input['name'] = static::buildInputName( $name );

			// Disable all premium options
			if ( !$premium && isset( $input['premium'] ) ) {
				$input['type'] = 'html';
				$input['html'] = '<span class="ork-locked-info">Locked content!</span>';
			}

			// Save original input name mapping to updated id
			$jsData['name2id'][$name] = Input::buildId( $input['name'] );
		}
		unset( $input );

		// Create POST[ork_settings][data] with filtered values to feed each Input created.
		$values = [ static::OPTIONS_NAME => static::getOption() ];

		foreach ( $fields as $name => $input ) {
			$Input = new Input( $input, $values );

			/* @formatter:off */
			add_settings_field(
				$Input->name(),
				sprintf( '<label for="%s">%s</label>', $Input->get( 'for' ), $Input->get( 'title' ) ),
				function() use ($Input) {
					echo $Input->getContents();
				},
				static::OPTIONS_PAGE,
				$Input->get( 'section' ),
				[
					'class' => $Input->get( 'tr_class' ),
				],
			);
			/* @formatter:on */
		}

		if ( static::isSettingsRender() ) {
			static::$Factory->Asset()->enqueue( 'css/settings.css' );
			static::$Factory->Asset()->enqueue( 'js/settings.js', Input::fieldPluck( 'enqueue', $fields ), static::jsObject( $jsData ) );
		}

		/*
		 * Register POST  namespace to allow all nested  automatically saved to DB by WP.
		 * NOTE: POST data will be serialized to adb_settings[] array with no sanitizing performed!
		 */
		register_setting( static::OPTIONS_PAGE, static::OPTIONS_NAME );
	}

	/**
	 * Get plugin's option from settings page.
	 *
	 * WARNING:
	 * Options are saved in DB as serialized array (raw data)
	 * Filtering is done during Input::val()
	 *
	 * NOTE:
	 * Unchecked checkboxes do not appear in POST data
	 *
	 * @param string $name Field name
	 * @param bool   $raw  Unfiltered value?
	 */
	public static function getOption( string $name = '', bool $raw = false, $def = '' )
	{

		// Remember requested options array, so we dont have to unserialize it every time
		static $options = null;

		if ( null === $options ) {
			$options = get_option( static::OPTIONS_NAME, [] ); // unserialize
		}

		if ( '' === $name ) {
			return $options ?: [];
		}

		if ( $raw ) {
			return $options[$name] ?? $def;
		}

		if ( !$field = Input::fieldFind( $name, static::getInputFields() ) ) {
			throw new \InvalidArgumentException( "Field '{$name}' not found!" );
		}

		// Get filtered value.
		// Fallback to field[defval] or empty string if no options was saved yet.
		$Input = new Input( $field, $options );
		$value = $Input->val();

		return $value;
	}

	/**
	 * Add admin notice.
	 *
	 * This is extended version of:
	 * @see add_settings_error()
	 *
	 * @param string $label  Main label for all notice items: "Label: notice1, notice2"
	 * @param string $notice Notice to add under current [label] collection
	 * @param array $args   (
	 *   @type string  [type]  - Notice type: error|warning|success|info. Default empty.
	 *   @type string  [split] - String used to separate multiple notices under same $label
	 *   @type boolean [close] - Append dismiss button?
	 * )
	 */
	public static function adminNotice( string $label, string $notice, array $args = [] ): void
	{
		/* @formatter:off */
		$args = array_merge([
			'type'  => '',
			'split' => ', ',
			'close' => true,
			'items' => [],
		], $args);
		/* @formatter:on */

		static::$adminNotices[$label] = array_merge( $args, static::$adminNotices[$label] ?? []);
		static::$adminNotices[$label]['items'][] = $notice;
	}

	/**
	 * Flush admin notices.
	 * @link https://developer.wordpress.org/reference/hooks/admin_notices/
	 */
	public static function adminNotices(): string
	{
		$out = '';

		foreach ( static::$adminNotices as $label => $notice ) {

			// Remove empty items
			$notice['items'] = array_filter( $notice['items'] );

			/* @formatter:off */
			$out .= strtr( '<div class="notice{type}{close}"><p><strong>{label}:</strong> {items}</p></div>', [
				'{type}'  => $notice['type'] ? ' notice-' . $notice['type'] : '',
				'{close}' => $notice['close'] ? ' is-dismissible' : '',
				'{label}' => $label,
				'{items}' => implode( $notice['split'], $notice['items'] ),
			]);
			/* @formatter:on */
		}
		static::$adminNotices = []; // reset

		return $out;
	}

	/**
	 * Build transient key.
	 * @see Plugin::TRANSIENTS
	 */
	protected static function transientId( string $name ): string
	{
		return static::TRANSIENT_PREFIX . $name;
	}

	/**
	 * Add admin transcient (short-life message turned into notice).
	 *
	 * @link https://developer.wordpress.org/apis/transients/
	 * @see Plugin::TRANSIENTS
	 * @see Plugin::adminNotices()
	 */
	public static function adminTransient( string $name, array $args = [] ): bool
	{
		if ( !isset( static::TRANSIENTS[$name] ) ) {
			return false;
		}

		/* @formatter:off */
		$args = array_merge_recursive([
			'type'   => 'warning',
			'close'  => false,
			'label'  => static::$Factory->get( 'name' ),
			'expire' => 0,
			'tokens' => [
				'{name}' => static::$Factory->get( 'name' ),
			],
		], $args);
		/* @formatter:on */

		return set_transient( static::transientId( $name ), $args, $args['expire'] );
	}

	/**
	 * Turn transients into admin notices.
	 */
	public static function adminTransients(): void
	{
		foreach ( static::TRANSIENTS as $name => $format ) {
			if ( $args = get_transient( $id = static::transientId( $name ) ) ) {
				static::adminNotice( $args['label'], strtr( $format, $args['tokens'] ), $args );
				delete_transient( $id );
			}
		}
	}

	/**
	 * Display admin notices in dashboard.
	 */
	public static function actionAdminNotices(): void
	{
		static::adminTransients();
		echo static::adminNotices();
	}
}
