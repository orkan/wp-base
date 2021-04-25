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
 * Settings tool: Mailer.
 *
 * @author Orkan <orkans+wpbase@gmail.com>
 */
class Mailer extends Tools
{
	const TITLE = 'Mailer';
	const NAME = 'mailer';
	const ICON = 'email-alt';
	const MENU = true;

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
			add_action( static::$hook . '_render_' . static::NAME, [ static::class, 'actionRender' ] );
			add_action( static::$hook . '_submit_' . static::NAME, [ static::class, 'actionSubmit' ] );
			add_action( 'wp_mail_failed', [ static::class, 'actionWpMailFailed' ] );
		}

		$ready = true;
	}

	/**
	 * Show more detailed error message.
	 */
	public static function actionWpMailFailed( \WP_Error $E )
	{
		foreach ( $E->get_error_messages() as $msg ) {
			static::$Settings->adminNotice( 'Mail', $msg, [ 'type' => 'error' ] );
		}
	}

	/**
	 * Form inputs.
	 */
	protected static function getInputFields(): array
	{
		global $current_user;

		/* @formatter:off */
		$fields = [
			'from' => [
				'type'     => 'text',
				'title'    => 'From',
				'class'    => 'regular-text',
				'defval'   => sprintf( '%s <%s>', get_option( 'blogname' ), get_option( 'admin_email' ) ),
			],
			'to' => [
				'type'     => 'text',
				'title'    => 'To',
				'class'    => 'regular-text',
				'defval'   => sprintf( '%s <%s>', $current_user->display_name, $current_user->user_email ),
			],
			'subject' => [
				'type'     => 'text',
				'title'    => 'Subject',
				'class'    => 'regular-text',
				'defval'   => 'Subject string',
			],
			'message' => [
				'type'     => 'textarea',
				'class'    => 'large-text',
				'title'    => 'Message',
			],
		];
		/* @formatter:on */
		Input::fieldsPrepare( $fields );

		return $fields;
	}

	/**
	 * Feed Inputs with POST data.
	 */
	protected static function getInputs(): Inputs
	{
		$post = static::postData();
		$Inputs = new Inputs();

		foreach ( self::getInputFields() as $field ) {
			$Inputs->add( new Input( $field, $post ) );
		}

		$Inputs->cfg( 'table', [ 'class' => 'form-table' ] );

		return $Inputs;
	}

	/**
	 * Tool: Submit.
	 *
	 * @link https://www.php.net/manual/en/mail.configuration.php
	 * @link https://developer.wordpress.org/reference/functions/wp_mail/
	 */
	public static function actionSubmit(): void
	{
		$Inputs = static::getInputs();

		/* @formatter:off */
		$result = wp_mail(
			$Inputs->find('to')->val(),
			$Inputs->find('subject')->val(),
			$Inputs->find('message')->val(),
			[
				'From: ' . $Inputs->find('from')->val(),
			],
		);
		/* @formatter:on */

		[ $type, $msg ] = $result ? [ 'info', 'Sent' ] : [ 'error', 'Failed' ];
		static::$Settings->adminNotice( 'Mail', $msg, [ 'type' => $type ] );
	}

	/**
	 * Tool: Render.
	 */
	public static function actionRender()
	{
		$Inputs = static::getInputs();
		$Inputs->find( 'message' )->val( '' );

		static::renderForm( $Inputs, [ 'button' => [ 'text' => 'Send' ] ] );
	}
}
