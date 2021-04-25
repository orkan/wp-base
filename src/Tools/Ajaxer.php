<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
namespace Orkan\WP\Base\Tools;

use Orkan\Input;
use Orkan\Inputs;
use Orkan\WP\Base\Tools;

/**
 * Settings tool: Ajaxer.
 *
 * Example of using cookies to store data between form submission.
 *
 * @author Orkan <orkans+wpbase@gmail.com>
 */
class Ajaxer extends Tools
{
	const TITLE = 'Ajaxer';
	const NAME = 'ajaxer';
	const ICON = 'update';
	const MENU = true;

	/* @formatter:off */

	/**
	 * Ajax actions.
	 */
	const ACTION = [
		'selector' => 'ork_ajaxer_selector',
		'echotext' => 'ork_ajaxer_echotext',
	];

	/* @formatter:on */

	/**
	 * Register all hooks.
	 */
	public static function run(): void
	{
		static $ready;

		if ( $ready ) {
			return;
		}

		parent::run();

		$isAjax = wp_doing_ajax();
		$isPage = !$isAjax && static::$Settings->isToolRender();

		if ( $isAjax && in_array( $_REQUEST['action'], static::ACTION ) ) {
			static::$Factory->Plugin()->ajaxSetExceptionHandler();
			add_action( 'wp_ajax_' . static::ACTION['selector'], [ static::class, 'actionAjaxSelector' ] );
			add_action( 'wp_ajax_' . static::ACTION['echotext'], [ static::class, 'actionAjaxTexter' ] );
		}
		elseif ( $isPage ) {
			add_action( static::$hook . '_define_' . static::NAME, [ static::class, 'actionDefine' ] );
			add_action( static::$hook . '_render_' . static::NAME, [ static::class, 'actionRender' ] );
		}
	}

	/**
	 * Handle Ajax request.
	 */
	public static function actionAjaxSelector()
	{
		static::$Plugin->ajaxCheckNonce();

		$name = $_REQUEST['name'] ?? null;
		$value = static::$Factory->get( $name, 'NULL' );

		// Add pause to test Ajax race conditions. See: tool-ajaxer.js
		usleep( rand( 0, 2000000 ) );

		wp_send_json_success( "name: $name, value: $value" );
	}

	/**
	 * Handle Ajax request.
	 */
	public static function actionAjaxTexter()
	{
		static::$Plugin->ajaxCheckNonce();

		$text = $_REQUEST['text'] ?? null;
		$error = $_REQUEST['error'] ?? null;

		if ( Input::filterCheckbox( $error ) ) {
			throw new \RuntimeException( $text );
		}

		// Add pause to test Ajax race conditions. See: tool-ajaxer.js
		usleep( rand( 0, 2000000 ) );

		wp_send_json_success( "text: $text" ); // + die()
		wp_send_json_error( "WP error in " . __METHOD__ ); // + die()
	}

	/**
	 * Tool: Define.
	 */
	public static function actionDefine(): void
	{
		$fields = static::getInputFields();

		// Javascript: window.ork = {...}
		/* @formatter:off */
		$jsData = [
			'ajaxer' => [
				'action' => [
					'selector' => static::ACTION['selector'],
					'echotext' => static::ACTION['echotext'],
				],
			],
			'l10n' => [
				'wait' => 'Wait for response...',
			],
		];
		/* @formatter:on */

		foreach ( Input::fieldsEach( $fields ) as $name => $input ) {
			$jsData['name2id'][$name] = Input::buildId( $input['name'] );
		}

		static::$Factory->Asset()->enqueue( 'css/tool-ajaxer.css', [ 'forms' ] );
		static::$Factory->Asset()->enqueue( 'js/tool-ajaxer.js', [], static::$Settings::jsObject( $jsData ) );

		//wp_enqueue_style( 'ork-bootstrap', '/' . static::$Factory->get( 'assets_loc' ) . '/bootstrap/bootstrap.min.css' );
	}

	/**
	 * Get inputs.
	 */
	protected static function getInputFields(): array
	{
		static $fields;

		if ( $fields ) {
			return $fields;
		}

		$fields = parent::getInputFields();

		foreach ( array_keys( static::$Factory->cfg() ) as $k ) {
			$fields['selector']['items']['selector_select']['items'][$k] = "cfg[$k]";
		}

		return $fields;
	}

	/**
	 * Tool: Render.
	 */
	public static function actionRender(): void
	{
		$Inputs = new Inputs( [], [ 'element' => 'div' ] );

		foreach ( static::getInputFields() as $field ) {
			$Inputs->add( new Input( $field ) );
		}

		$Inputs->renderParagraphs();
	}
}
