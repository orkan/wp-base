<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
namespace Orkan\WP\Base;

use Orkan\Input;

/**
 * Plugin: Settings.
 *
 * @author Orkan <orkans+wpbase@gmail.com>
 */
class Settings
{
	/**
	 * Unserialized DB options (short time cache).
	 * @see Settings::getOption()
	 */
	protected $options;

	/**
	 * Admin notices queue.
	 * @see Settings::adminNotice()
	 */
	protected $notices = [];

	/* @formatter:off */

	/**
	 * Registered Ajax actions.
	 * @var array (
	 *   [key] => "action" used in add_action() as "wp_ajax_{action}" -or- "wp_ajax_nopriv_{action}"
	 *   ...
	 * )
	 */
	protected $ajaxActions = [];

	/**
	 * Index of all cache keys used to store data in DB.
	 * Makes it easier to manage all the data cached in DB from one place!
	 * Internaly prefixed with cfg[stt_cache_prefix]
	 * @see Settings::cacheFlush()
	 * @see Settings::cacheDeleteAll()
	 */
	protected $cacheKeys = [];

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
	protected $tools = [
		'settings'   => [ 'title' => 'Settings'    , 'icon' => 'admin-generic'    , 'menu' => false ],
		'readme'     => [ 'title' => 'README'      , 'icon' => 'book'             , 'menu' => true  ],
		'config'     => [ 'title' => 'Config'      , 'icon' => 'info-outline'     , 'menu' => true  ],
	];

	/**
	 * Transient messages.
	 * @see Settings::adminTransient()
	 */
	protected $transients = [];

	/* @formatter:on */

	/**
	 * Connected?
	 */
	private $ready = false;

	/**
	 * Services:
	 */
	protected $Factory;
	protected $Plugin;

	/**
	 * Setup.
	 */
	public function __construct( Factory $Factory )
	{
		$this->Factory = $Factory;
		$this->Plugin = $Factory->Plugin();

		$this->Factory->merge( self::defaults() );
		$this->cacheRegister( 'settings_inputs' );
	}

	/**
	 * Register all hooks.
	 */
	public function run(): void
	{
		if ( $this->ready ) {
			return;
		}

		$isAjax = wp_doing_ajax();
		$isPage = !$isAjax && is_admin();

		if ( $isAjax && in_array( $_REQUEST['action'], $this->ajaxActions ) ) {
			$this->Plugin->ajaxExceptionHandle();
		}
		elseif ( $isPage ) {
			add_action( 'admin_init', [ $this, 'actionAdminInit' ] );
			add_action( 'admin_menu', [ $this, 'actionAdminMenu' ] );

			// Tools
			$pl = $this->Factory->get( 'plu_basename' );
			$hook = $this->Factory->get( 'stt_options_name' );
			add_action( "activate_$pl", [ $this, 'actionActivate' ] );
			add_action( "deactivate_$pl", [ $this, 'actionDeactivate' ] );
			add_action( 'admin_notices', [ $this, 'actionAdminNotices' ] );
			add_action( 'plugin_row_meta', [ $this, 'actionPluginRowMeta' ], 10, 2 );
			add_filter( "plugin_action_links_$pl", [ $this, 'filterActionLinks' ] );
			add_filter( $hook . '_inputs', [ $this, 'filterInputFields' ] );
			add_action( $hook . '_submit_settings', [ $this, 'actionSubmitSettings' ] );
			add_action( $hook . '_define_settings', [ $this, 'actionDefineSettings' ], 555 );
			add_action( $hook . '_render_settings', [ $this, 'actionRenderSettings' ] );
			add_action( $hook . '_render_readme', [ $this, 'actionRenderReadme' ] );
			add_action( $hook . '_render_config', [ $this, 'actionRenderConfig' ] );
		}

		$this->ready = true;
	}

	/**
	 * Get defaults.
	 */
	private function defaults(): array
	{
		$slug = $this->Factory->get( 'plu_slug' );

		/**
		 * [stt_options_page]
		 * Settings page URL slug
		 *
		 * [stt_options_name]
		 * Used in:
		 * - Settings options DB key
		 * - Settings and Tool actions
		 * - FORM input name space: options_name[input-name]
		 * Prefix for:
		 * - apply_filters(FORM inputs)
		 *
		 * [stt_cache_prefix]
		 * Prefix for: DB cache
		 *
		 * [stt_transient_prefix]
		 * Prefix for: Transients messages
		 *
		 * [stt_form_nonce_name]
		 * [stt_form_nonce_action]
		 * Settings page FORM nonce
		 * @see Settings::formCheckNonce()
		 *
		 * @formatter:off */
		return [
			'stt_options_page'     => "ork-settings-{$slug}",
			'stt_options_name'     => "ork_settings_{$slug}",
			'stt_cache_prefix'     => "ork_cache_{$slug}_",
			'stt_transient_prefix' => "ork_transcient_{$slug}_",
			// Forms
			'stt_form_nonce_name'   => 'ork_nonce',
			'stt_form_nonce_action' => 'ork_form_submit',
		];
		/* @formatter:on */
	}

	/**
	 * Show label in Dashboard > Settings menu and define render page callback.
	 */
	public function actionAdminMenu(): void
	{
		$label = $this->Factory->get( 'plu_name' );
		$page = $this->Factory->get( 'stt_options_page' );
		add_options_page( $label, $label, 'manage_options', $page, [ $this, 'actionRender' ] );
	}

	/**
	 * Activate plugin.
	 */
	public function actionActivate()
	{
	}

	/**
	 * Deactivate plugin.
	 */
	public function actionDeactivate()
	{
		$this->cacheFlush();
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
	public function actionAdminInit(): void
	{
		if ( $this->isSettingsSubmit() ) {
			do_action( $this->getHookName( 'submit' ) ); // Current Action: 'settings' !
		}
		elseif ( $this->isSettingsRender() ) {
			do_action( $this->getHookName( 'define' ) );
		}
		elseif ( $this->isToolRender() ) {

			if ( $this->isToolSubmit() ) {
				do_action( $this->getHookName( 'submit', false ) ); // All Actions
				do_action( $this->getHookName( 'submit' ) ); // Current Actions
			}

			// Always call 'define' hook on Action pages, since FORM url === Page url
			do_action( $this->getHookName( 'define', false ) );
			do_action( $this->getHookName( 'define' ) );
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
	public function actionRender()
	{
		echo '<div id="ork-settings-actionRender">';
		echo $this->toolHeader();
		do_action( $this->getHookName( 'render' ) );
		echo '</div>';
	}

	/**
	 * Is Settings page - render?
	 */
	public function isSettingsRender()
	{
		$pagenow = basename( $_SERVER['PHP_SELF'] );
		$page = $_GET['page'] ?? '';
		$action = $_GET['action'] ?? 'settings';

		$result = 'options-general.php' === $pagenow;
		$result &= $this->Factory->get( 'stt_options_page' ) === $page;
		$result &= 'settings' === $action;

		return $result;
	}

	/**
	 * Is Settings page - submit?
	 *
	 * CAUTION:
	 * Submit <FORM action="options.php"> !== Page URL: "options-general.php"
	 */
	public function isSettingsSubmit()
	{
		$pagenow = basename( $_SERVER['PHP_SELF'] );
		$page = $_POST['option_page'] ?? '';
		$action = $_POST['action'] ?? '';

		$result = 'options.php' === $pagenow;
		$result &= $this->Factory->get( 'stt_options_page' ) === $page;
		$result &= 'update' === $action;

		return $result;
	}

	/**
	 * Is Action page - render?
	 *
	 * CAUTION:
	 * That includes 'submit' action too, since the FORM action url is the same
	 */
	public function isToolRender( string $currentAction = '' ): bool
	{
		$pagenow = basename( $_SERVER['PHP_SELF'] );
		$page = $_GET['page'] ?? null;
		$action = $_GET['action'] ?? null;

		$result = 'options-general.php' === $pagenow;
		$result &= $this->Factory->get( 'stt_options_page' ) === $page;
		$result &= isset( $action );

		if ( $currentAction ) {
			$result &= $action === $currentAction;
		}

		return $result;
	}

	/**
	 * Is Action page - submit?
	 *
	 * CAUTION:
	 * Submit <FORM action="options-general.php"> === Page URL: "options-general.php"
	 */
	public function isToolSubmit(): bool
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
	public function getHookName( string $type, bool $withAction = true ): string
	{
		$action = $withAction ? '_' . $this->getAction() : '';
		return $this->Factory->get( 'stt_options_name' ) . '_' . $type . $action;
	}

	/**
	 * Get current Action name.
	 */
	public function getAction(): string
	{
		$action = $_GET['action'] ?? '';
		return isset( $this->tools[$action] ) ? $action : 'settings';
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
	public function actionPluginRowMeta( array $links, string $file ): array
	{
		if ( $file === $this->Factory->get( 'plu_basename' ) ) {
			foreach ( $this->tools as $k => $v ) {
				if ( $v['menu'] ) {
					$links[] = $this->toolLink( $k, true );
				}
			}
		}

		return $links;
	}

	/**
	 * Register new tool.
	 */
	public function toolRegister( $id, $title, $icon, $menu ): void
	{
		$this->tools[$id] = [ 'title' => $title, 'icon' => $icon, 'menu' => $menu ];
	}

	/**
	 * Settings page - init.
	 */
	public function actionDefineSettings(): void
	{
		//enqueue() moved to Settings::form() -> isRender()
	}

	/**
	 * Register FORM fields.
	 */
	protected function formRegister(): void
	{
		// Refresh DB cache
		$this->cacheFlush();

		try {
			// Register FORM fields so they can be auto-rendered
			$this->form();
		}
		catch ( \Throwable $E ) {
			error_log( $E );
			$this->adminNotice( 'FORM register', $E->getMessage(), [ 'type' => 'error' ] );
		}
	}

	/**
	 * Register FORM inputs.
	 */
	protected function formRender(): void
	{
		// Echo all notices gathered during page construct. Note, this action is after the 'admin_notices' hook!
		echo $this->adminNotices();

		echo '<form method="post" action="options.php">';
		submit_button();
		settings_fields( $this->Factory->get( 'stt_options_page' ) );
		do_settings_sections( $this->Factory->get( 'stt_options_page' ) );
		submit_button();
		echo '</form>';
	}

	/**
	 * Settings page - render.
	 *
	 * @see add_options_page()
	 * @see Settings::actionAdminMenu()
	 */
	public function actionRenderSettings(): void
	{
		$this->formRegister();
		$this->formRender();
	}

	/**
	 * Settings page - submit.
	 *
	 * NOTE:
	 * The $_POST[] values aint saved in DB yet!
	 */
	public function actionSubmitSettings(): void
	{
		// Register FORM inputs so they can be auto-saved
		$this->form();

	/**
	 * -------------------------------------------------------------------------------------------------------------
	 * Refresh DB cache.
	 * @see Settings::actionRenderSettings()
	 * $this->flushCache();
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
	protected function cacheId( string $key ): string
	{
		$id = $this->cacheKeys[$key] ?? null;

		if ( !$id ) {
			throw new \RuntimeException( <<<EOT
				Cache key "$key" not defined! 
				Use Settings::cacheRegister(key => id) before calling cache methods.
				EOT );
		}

		return $this->Factory->get( 'stt_cache_prefix' ) . $id;
	}

	public function cacheRegister( string $key, string $id = '' )
	{
		if ( isset( $this->cacheKeys[$key] ) ) {
			throw new \RuntimeException( <<<EOT
				Cache key "$key" already defined! 
				EOT );
		}

		$this->cacheKeys[$key] = $id ?: $key;
		return true;
	}

	public function cacheGet( string $key, $default = null )
	{
		return get_option( $this->cacheId( $key ), $default );
	}

	public function cacheSet( string $key, $data, bool $autoload = false ): bool
	{
		return update_option( $this->cacheId( $key ), $data, $autoload );
	}

	public function cacheDelete( string $key ): bool
	{
		return delete_option( $this->cacheId( $key ) );
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
	 * @param string|array $keys Previously used to cacheSet()
	 */
	public function cacheDeleteAll( $keys ): int
	{
		$items = 0;
		foreach ( (array) $keys as $key ) {
			if ( $this->cacheDelete( $key ) ) {
				$this->adminNotice( 'Flush', $this->cacheId( $key ) );
				$items++;
			}
		}
		return $items;
	}

	/**
	 * Clear Settings cache.
	 */
	public function cacheFlush( string $key = '' ): int
	{
		return $this->cacheDeleteAll( $key ?: array_keys( $this->cacheKeys ) );
	}

	/**
	 * Print some debug info.
	 */
	public function actionRenderConfig(): void
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
		printf( '<a id="config">%s::cfg() </a>', get_class( $this->Plugin ) );
		$cfg = $this->Factory->cfg();
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
	public function actionRenderReadme(): void
	{
		$pluginUrl = $this->Factory->get( 'plu_plugin_url' );
		$pluginDir = $this->Factory->get( 'plu_plugin_dir' );

		$text = file_get_contents( "$pluginDir/README.md" );
		$text = ( new \Parsedown() )->text( $text );
		$text = preg_replace( '~(?:src|href)=[\'"](?!http://|https://|/)\K([^\'"]*)~i', "$pluginUrl/$1", $text );
		echo $text;
	}

	/**
	 * Get default javascript object.
	 */
	public function jsObject( array $data = [] ): array
	{
		/* @formatter:off */
		return array_replace_recursive([
			'debug' => WP_DEBUG,
			'url'   => admin_url( 'admin-ajax.php' ),
			'l10n'  => [
				'error' => 'An error occured',
				'wait'  => 'Wait...',
			],
			'nonce' => [
				'name'   => $this->Factory->get( 'plu_ajax_nonce_name' ),
				'action' => wp_create_nonce( $this->Factory->get( 'plu_ajax_nonce_action' ) ),
			],
		], $data );
		/* @formatter:on */
	}

	/**
	 * Add action links.
	 * @link https://developer.wordpress.org/reference/hooks/plugin_action_links_plugin_file/
	 */
	public function filterActionLinks( array $actions ): array
	{
		$actions['settings'] = $this->toolLink( 'settings' );
		return $actions;
	}

	/**
	 * Get Settings tools page header title.
	 */
	public function toolHeader(): string
	{
		$action = $this->getAction();

		/* @formatter:off */
		return strtr( <<<HTML
			<h1>
				<span class="dashicons dashicons-{icon}" style="font-size:1.4rem;width:1rem;"></span>
				<a href="{url}">{name} - {title}</a>
			</h1>
			HTML, [
			'{name}'  => $this->Factory->get( 'plu_name' ),
			'{url}'   => $this->toolUrl( $action ),
			'{icon}'  => $this->tools[$action]['icon'],
			'{title}' => $this->tools[$action]['title'],
		]);
		/* @formatter:on */
	}

	/**
	 * Get Settings tools page link.
	 */
	public function toolLink( string $action = '', bool $addIcon = false ): string
	{
		$action = $action ?: 'settings';
		$icon = $this->tools[$action]['icon'];

		/* @formatter:off */
		return strtr( '<a href="{action}" style="white-space:nowrap;">{icon}{title}</a>', [
			'{action}' => $this->toolUrl( $action ),
			'{title}'  => $this->tools[$action]['title'],
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
	public function toolUrl( string $action, bool $long = true ): string
	{
		/* @formatter:off */
		$query = http_build_query([
			'page'   => $this->Factory->get( 'stt_options_page' ),
			'action' => $action
		], '', $long ? '&amp;' : '&' );
		/* @formatter:on */

		return admin_url( 'options-general.php?' . $query );
	}

	/**
	 * Load FORM Sections from config file.
	 */
	public function getSections(): array
	{
		$fields = require $this->Factory->get( 'plu_config_dir' ) . '/settings_sections.php';
		$action = $this->Factory->get( 'stt_options_name' ) . '_sections';
		return apply_filters( $action, $fields );
	}

	/**
	 * Load FORM Fields from config file.
	 * Cache results, since it might be used by $this->getOption() multiple times for different
	 *
	 * @see Settings::getOption()
	 */
	public function getInputFields( $key = null ): array
	{
		$fields = $this->cacheGet( 'settings_inputs' );

		if ( null === $fields ) {
			$fields = require $this->Factory->get( 'plu_config_dir' ) . '/settings_inputs.php';
			$action = $this->Factory->get( 'stt_options_name' ) . '_inputs';
			$fields = apply_filters( $action, $fields );

			// Add required field attrs (name, type)
			Input::fieldsPrepare( $fields );
			$this->cacheSet( 'settings_inputs', $fields );
		}

		return $key ? $fields[$key] : $fields;
	}

	/**
	 * Update inputs before displaying.
	 * @see Settings::getInputFields()
	 */
	public function filterInputFields( array $fields ): array
	{
		return $fields;
	}

	/**
	 * Build FORM input name.
	 * Get namespaced name, so the FORM values can be auto saved by WP in DB options table under one record (array)
	 *
	 * @param  string $name Field name
	 * @return string FORM <input> name
	 */
	public function buildInputName( string $name ): string
	{
		return $this->Factory->get( 'stt_options_name' ) . "[$name]";
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
	protected function form()
	{
		// -------------------------------------------------------------------------------------------------------------
		// Sections:
		$sections = $this->getSections();
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
				$this->Factory->get( 'stt_options_page' )
			);
			/* @formatter:on */
		}

		// -------------------------------------------------------------------------------------------------------------
		// Inputs:
		$namespace = $this->Factory->get( 'stt_options_name' );
		$fields = $this->getInputFields();
		$jsData = [];
		$premium = $this->Factory->get( 'plu_premium' );

		foreach ( Input::fieldsEach( $fields, true ) as $name => &$input ) {
			// Create namespaced input name: <input name="ork_settings[name]" ... >
			$input['name'] = $this->buildInputName( $name );

			// Disable all premium options
			if ( !$premium && isset( $input['premium'] ) ) {
				$input['type'] = 'html';
				$input['html'] = '<span class="ork-locked-info">Locked content!</span>';
			}

			// Save original input name mapping to updated id
			$jsData['name2id'][$name] = Input::buildId( $input['name'] );
		}
		unset( $input );

		// Create POST[ork_settings] => Array(DB options) with filtered values to feed each Input created.
		$values = [ $namespace => $this->getOption() ];

		foreach ( $fields as $name => $input ) {
			$Input = new Input( $input, $values );

			/* @formatter:off */
			add_settings_field(
				$Input->name(),
				sprintf( '<label for="%s">%s</label>', $Input->get( 'for' ), $Input->get( 'title' ) ),
				function() use ($Input) {
					echo $Input->getContents();
				},
				$this->Factory->get( 'stt_options_page' ),
				$Input->get( 'section' ),
				[
					'class' => $Input->get( 'tr_class' ),
				],
			);
			/* @formatter:on */
		}

		if ( $this->isSettingsRender() ) {
			$this->Plugin->enqueue( 'css/settings.css' );
			/* @formatter:off */
			$this->Plugin->enqueue( 'js/settings.js', [
				'deps' => Input::fieldPluck( 'enqueue', $fields ),
				'data' => $this->jsObject( $jsData ),
			]);
			/* @formatter:on */
		}

		// Register POST namespace to auto-save inputs to DB (raw data - no sanitizing performed!)
		register_setting( $this->Factory->get( 'stt_options_page' ), $this->Factory->get( 'stt_options_name' ) );
	}

	/**
	 * Get plugin's option from settings page.
	 *
	 * WARNING:
	 * Options are saved in DB as serialized array (raw data)
	 * Filtering is done during Input::val()
	 *
	 * @param string $name Field name
	 * @param bool   $raw  Unfiltered value?
	 */
	public function getOption( string $name = '', bool $raw = false, $def = '' )
	{
		// Remember requested options array, so we dont have to unserialize it every time
		if ( !isset( $this->options ) ) {
			$this->options = get_option( $this->Factory->get( 'stt_options_name' ), [] ); // unserialize
		}

		if ( '' === $name ) {
			return $this->options ?: [];
		}

		if ( $raw ) {
			return $this->options[$name] ?? $def;
		}

		if ( !$field = Input::fieldFind( $name, $this->getInputFields() ) ) {
			throw new \InvalidArgumentException( "Field '{$name}' not found!" );
		}

		// Get filtered value.
		// Fallback to field[defval] or empty string if no options was saved yet.
		$Input = new Input( $field, $this->options );
		$value = $Input->val();

		return $value;
	}

	/**
	 * Get FORM nonce input.
	 */
	public function formNonceInput(): string
	{
		$name = $this->Factory->get( 'stt_form_nonce_name' );
		$value = wp_create_nonce( $this->Factory->get( 'stt_form_nonce_action' ) );

		return <<<HTML
		<input type="hidden" name="$name" value="$value">
		HTML;
	}

	/**
	 * Check FORM nonce value.
	 *
	 * NOTE:
	 * adminNotices() are parsed on [admin:init] hook, after [submit] but before [render] hook!
	 * Best use this function in [submit] hook.
	 */
	public function formNonceCheck(): bool
	{
		$name = $this->Factory->get( 'stt_form_nonce_name' );
		$action = $this->Factory->get( 'stt_form_nonce_action' );
		$result = check_ajax_referer( $action, $name, false );

		if ( false === $result ) {
			$this->adminNotice( 'Error', 'FORM data expired!', [ 'type' => 'error', 'close' => false ] );
		}

		return $result;
	}

	/**
	 * Add admin notice.
	 *
	 * This is extended version of:
	 * @see add_settings_error()
	 *
	 * @param string $label  Main label for all notice items: "Label: notice1, notice2"
	 * @param string $notice Notice to add under current [label] collection
	 * @param array  $args (
	 * [type]  - Notice type: error|warning|success|info. Default: empty.
	 * [split] - String used to separate multiple notices under same $label. Default: comma.
	 * [close] - Append dismiss button? Default: yes.
	 * )
	 */
	public function adminNotice( string $label, string $notice, array $args = [] ): void
	{
		$notices = $this->notices[$label] ?? [];
		$notices['items'][] = $notice;

		/* @formatter:off */
		$this->notices[$label] = array_merge([
			'type'  => '',
			'split' => ', ',
			'close' => true,
		], $notices, $args );
		/* @formatter:on */
	}

	/**
	 * Flush admin notices.
	 * @link https://developer.wordpress.org/reference/hooks/admin_notices/
	 */
	public function adminNotices(): string
	{
		$out = '';

		foreach ( $this->notices as $label => $notice ) {

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
		$this->notices = []; // reset

		return $out;
	}

	/**
	 * Build transient key.
	 * @see Plugin::$transients
	 */
	protected function transientId( string $key ): string
	{
		return $this->Factory->get( 'stt_transient_prefix' ) . $key;
	}

	/**
	 * Add admin transcient (short-life message turned into notice).
	 *
	 * @link https://developer.wordpress.org/apis/transients/
	 * @see Plugin::$transients
	 * @see Plugin::adminNotices()
	 */
	public function adminTransient( string $key, array $args = [] ): bool
	{
		if ( !isset( $this->transients[$key] ) ) {
			return false;
		}

		/* @formatter:off */
		$args = array_merge_recursive([
			'type'   => 'warning',
			'close'  => false,
			'label'  => $this->Factory->get( 'plu_name' ),
			'expire' => 0,
			'tokens' => [
				'{name}' => $this->Factory->get( 'plu_name' ),
			],
		], $args);
		/* @formatter:on */

		return set_transient( $this->transientId( $key ), $args, $args['expire'] );
	}

	/**
	 * Turn transients into admin notices.
	 */
	public function adminTransients(): void
	{
		foreach ( $this->transients as $name => $format ) {
			if ( $args = get_transient( $id = $this->transientId( $name ) ) ) {
				$this->adminNotice( $args['label'], strtr( $format, $args['tokens'] ), $args );
				delete_transient( $id );
			}
		}
	}

	/**
	 * Display admin notices in dashboard.
	 */
	public function actionAdminNotices(): void
	{
		$this->adminTransients();
		echo $this->adminNotices();
	}
}
