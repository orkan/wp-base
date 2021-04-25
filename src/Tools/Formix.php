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
 * Settings tool: Formix.
 *
 * Example of using cookies to store data between form submission.
 *
 * @author Orkan <orkans+wpbase@gmail.com>
 */
class Formix extends Tools
{
	const TITLE = 'Formix';
	const NAME = 'formix';
	const ICON = 'forms';
	const MENU = true;

	/**
	 * Does input fields require reload?
	 * After submit the cookie inputs[defval] array is updated!
	 */
	private static $isDirty = true;

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

		if ( static::$Settings->isToolRender() ) {
			add_action( static::$hook . '_define_' . static::NAME, [ static::class, 'actionDefine' ] );
			add_action( static::$hook . '_render_' . static::NAME, [ static::class, 'actionRender' ] );
			add_action( static::$hook . '_submit_' . static::NAME, [ static::class, 'actionSubmit' ] );
		}

		$ready = true;
	}

	/**
	 * Tool: Define.
	 */
	public static function actionDefine(): void
	{
		foreach ( Input::fieldPluck( 'enqueue', static::getInputFields() ) as $handle ) {
			wp_enqueue_script( $handle );
		}

		static::$Factory->Asset()->enqueue( 'css/tool-formix.css' );
	}

	/**
	 * Tool: Submit.
	 */
	public static function actionSubmit(): void
	{
		static::cookieSave( static::getInputFields() );

		// COOKIE data updated, reload inputs!
		self::$isDirty = true;
	}

	/**
	 * Mix inputs with Settings page Section B inputs.
	 */
	protected static function getInputFields(): array
	{
		static $fields;

		if ( $fields && !self::$isDirty ) {
			return $fields;
		}

		$fields = parent::getInputFields();

		// Import fields from Plugin > Settings: Section B
		$pluFields = array_filter( static::$Settings->getInputFields(), function ( $v ) {
			return 'section_b' === $v['section'];
		} );
		$settings = &$fields['settings']['items']['table']['items']; // shortcut
		$settings = array_merge( $settings, $pluFields );
		Input::fieldsPrepare( $settings );

		// Set values
		foreach ( Input::fieldsEach( $fields, true ) as $name => &$field ) {
			$val1 = $_COOKIE[static::NAME][$name] ?? null;
			$val2 = static::$Settings::getOption( $name, true, null );
			$val3 = $field['defval'] ?? null;

			$field['defval'] = $val1 ?? $val2 ?? $val3;
		}

		self::$isDirty = false;
		return $fields;
	}

	/**
	 * Tool: Render.
	 */
	public static function actionRender(): void
	{
		$Table = new Inputs();

		foreach ( static::getInputFields() as $field ) {
			$Table->add( new Input( $field ) );
		}

		try {
			// Get all Inputs from Table (flattened)
			$Inputs = Input::inputsAll( $Table->elements( true ), true );

			// Show admin notice?
			if ( $notice = $Inputs['notice']->val() ) {
				static::$Settings->adminNotice( $Inputs['notice']->get( 'title' ), $notice );
			}

			// Update: Formix > Notes
			$notes = strtr( $Inputs['notes']->val(), [ '{text_b1}' => $Inputs['text_b1']->val() ] );
			$Inputs['notes']->cfg( 'html', $notes );

			// Show messages if any...
			echo static::$Settings->adminNotices();
		}

		catch ( \Exception $E ) {
			echo $E->getMessage();
		}

		// The FORM
		static::renderForm( $Table, [ 'button' => [ 'text' => 'Save data' ] ] );
	}
}
