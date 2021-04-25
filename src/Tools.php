<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
namespace Orkan\WP\Base;

use Orkan\Input;
use Orkan\Inputs;

/**
 * Settings tool: Base.
 *
 * @author Orkan <orkans+wpbase@gmail.com>
 */
class Tools
{
	const TITLE = 'Tools';
	const NAME = 'tools';
	const ICON = 'unknown';
	const MENU = false;

	/**
	 * Previous FORM values to remember.
	 */
	const MAX_HISTORY = 3;

	/**
	 * Tool action prefix.
	 */
	protected static $hook;

	/**
	 * Whether the Tool page is currently displayed / proccessed.
	 */
	protected static $isPage;

	/**
	 * Whether the Tool ajax request is currently proccessed.
	 */
	protected static $isAjax;

	/**
	 * Ajax actions.
	 */
	const ACTION = [];

	/**
	 * @var Factory
	 */
	protected static $Factory;

	/**
	 * @var Plugin
	 */
	protected static $Plugin;

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
		static::$Plugin = $Factory->Plugin();
		static::$Settings = $Factory->Settings();
		static::$hook = static::$Settings::OPTIONS_NAME;
	}

	/**
	 * Register all hooks.
	 */
	protected static function run(): void
	{
		static $ready;

		static::$Settings->toolRegister( static::NAME, static::TITLE, static::ICON, static::MENU );

		if ( $ready ) {
			return;
		}

		add_action( static::$hook . '_define', [ __CLASS__, 'actionDefine' ] );
		add_action( static::$hook . '_submit', [ __CLASS__, 'actionSubmit' ] );

		$ready = true;
	}

	/**
	 * Tool: Define.
	 */
	public static function actionDefine(): void
	{
		static::$Factory->Asset()->enqueue( 'css/inputs.css' );
	}

	/**
	 * Tool: Submit.
	 *
	 * NOTE:
	 * Submit action does NOT redirect to render page url
	 */
	public static function actionSubmit(): void
	{
		$data = static::postData();
		ksort( $data );
		$html = trim( Input::escHtml( print_r( $data, true ) ) );

		static::$Settings->adminNotice( 'Submit', 'OK', [ 'type' => 'success' ] );
		static::$Settings->adminNotice( 'Submit', <<<HTML
			<a href="javascript:;" onClick="jQuery('#submit_toogle').toggle();return false;">POST</a>
			<pre id="submit_toogle" style="display:none">$html</pre>
			HTML );
	}

	/**
	 * Get inputs.
	 * No need for cache since PHP caches all require() per request :)
	 */
	protected static function getInputFields(): array
	{
		$fields = require static::$Factory->get( 'config_dir' ) . sprintf( '/tools_%s_inputs.php', static::NAME );
		Input::fieldsPrepare( $fields );
		return $fields;
	}

	/**
	 * Get Tool submitted FORM data.
	 *
	 * NOTE:
	 * @see Tools::cookieGet()
	 */
	protected static function postData(): array
	{
		static $data;
		return $data ?? $data = array_map( 'stripslashes_deep', $_POST );
	}

	/**
	 * Get Tool $_COOKIE data.
	 *
	 * NOTE:
	 * Always use this method to retrive cookie data in derived Tool.
	 * This will prevent double calling stripslashes() on data stored in onSubmit event
	 * @see Tools::cookieSaveHistory()
	 *
	 * CAUTION:
	 * WordPress adds slashes to $_POST/$_GET/$_REQUEST/$_COOKIE regardless of what get_magic_quotes_gpc() returns
	 * @link https://developer.wordpress.org/reference/functions/stripslashes_deep/
	 */
	protected static function cookieData(): array
	{
		static $data;
		return $data ?? $data = array_map( 'stripslashes_deep', $_COOKIE[static::NAME] ?? []);
	}

	/**
	 * Get default cookie args.
	 */
	protected static function cookieCfg( bool $delete = false ): array
	{
		/* @formatter:off */
		return [
			'expires' => $delete ? 1 : strtotime( '+1 year' ), // delete browser cookies?
			'path'    => $_SERVER['PHP_SELF'],
		];
		/* @formatter:on */
	}

	/**
	 * Save POST to COOKIES (with history).
	 *
	 * Input attrs:
	 * [reset]   => (boolean) Allow cookie reset for this field (def. POST[reset] )
	 * [history] => (int)     Number of previous values in attr[defval] (def. static::MAX_HISTORY )
	 */
	protected static function cookieSave(): void
	{
		if ( !$post = static::postData() ) {
			return;
		}

		$fields = static::getInputFields();
		$reset = $post['reset'] ?? false;
		$cookieUser = static::cookieCfg( $reset );
		$cookieKeep = static::cookieCfg();
		$cookieData = static::cookieData();

		foreach ( Input::fieldsEach( $fields, true ) as $field ) {
			$Input = new Input( $field, $post );
			$name = $Input->name();
			$value = $Input->val();

			/*
			 * ---------------------------------------------------------------------------------------------------------
			 * Get history values
			 *
			 * NOTE:
			 * Don't calculate defval's on reset
			 * No history for checkboxes
			 */
			$keep = !$Input->get( 'reset', $reset );
			$defVals = (array) ( $cookieData[$name] ?? []);

			if ( $keep && '' !== $value ) {
				$slice = $Input->get( 'history', static::MAX_HISTORY );
				$slice = in_array( $Input->type(), [ 'group', 'checkbox', 'switch' ] ) ? 1 : $slice;

				array_unshift( $defVals, $value ); // prepend new value
				$defVals = array_unique( $defVals );
				$defVals = array_slice( $defVals, 0, $slice ); // reset index
			}

			// Set or delete cookie
			foreach ( $defVals as $i => $value ) {
				setcookie( static::NAME . "[$name][$i]", $value, $keep ? $cookieKeep : $cookieUser );
			}

			// Append POST values to COOKIES
			$_COOKIE[static::NAME][$name] = $keep ? $defVals : null; // remember whole history
		}
	}

	/**
	 * Render submit FORM.
	 */
	protected static function renderForm( Inputs $Inputs, $cfg = [] ): void
	{
		/* @formatter:off */
		echo strtr( '<form method="post" name="{name}" action="{action}">', [
			'{name}'   => static::NAME,
			'{action}' => $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'],
		]);
		/* @formatter:on */

		$Inputs->cfg( 'table', [ 'class' => 'form-table' ] );
		$Inputs->renderTable();

		/* @formatter:off */
		$cfg = array_replace_recursive([
			'button' => [
				'text' => null,
				'type' => 'primary',
				'name' => 'submit',
				'wrap' => true,
				'attr' => [],
			],
		], $cfg );

		submit_button(
			$cfg['button']['text'],
			$cfg['button']['type'],
			$cfg['button']['name'],
			$cfg['button']['wrap'],
			$cfg['button']['attr'],
		);
		/* @formatter:on */

		echo '</form>';
	}
}
