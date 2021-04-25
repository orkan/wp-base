<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
namespace Orkan\WP\Base;

/**
 * Plugin: Factory.
 *
 * @author Orkan <orkans+wpbase@gmail.com>
 */
class Factory
{
	/**
	 * Config:
	 */
	protected static $cfg = [];

	/**
	 * Services:
	 */
	protected static $Factory;
	protected static $Plugin;
	protected static $Settings;
	protected static $Asset;
	protected static $Mailer;
	protected static $Formix;
	protected static $Ajaxer;

	// =================================================================================================================
	// Config:
	// =================================================================================================================

	/**
	 * Merge config values.
	 *
	 * @param array $defaults Low priority config - will NOT replace $this->cfg
	 * @param bool  $force    Hight priority config - will replace $this->cfg
	 * @return Factory
	 */
	public static function merge( array $defaults, bool $force = false )
	{
		/* @formatter:off */
		static::$cfg = $force ?
			array_replace_recursive( static::$cfg, $defaults ) :
			array_replace_recursive( $defaults, static::$cfg );
		/* @formatter:on */

		return static::Factory();
	}

	/**
	 * Set/Get config value.
	 */
	public static function cfg( string $key = '', $val = null )
	{
		$last = static::$cfg[$key] ?? null;

		if ( isset( $val ) ) {
			static::$cfg[$key] = $val;
		}

		if ( '' === $key ) {
			return static::$cfg;
		}

		return $last;
	}

	/**
	 * Get config value or return default.
	 */
	public static function get( string $key = '', $default = '' )
	{
		return static::cfg( $key ) ?? $default;
	}

	// =================================================================================================================
	// Services:
	// =================================================================================================================

	/**
	 * @return Factory
	 */
	public static function Factory()
	{
		return static::$Factory ?? static::$Factory = new static();
	}

	/**
	 * @return Plugin
	 */
	public static function Plugin()
	{
		return static::$Plugin ?? static::$Plugin = new Plugin( static::Factory() );
	}

	/**
	 * @return Settings
	 */
	public static function Settings()
	{
		return static::$Settings ?? static::$Settings = new Settings( static::Factory() );
	}

	/**
	 * @return Asset
	 */
	public static function Asset()
	{
		return static::$Asset ?? static::$Asset = new Asset( static::Factory() );
	}

	/**
	 * @return Tools\Mailer
	 */
	public static function Mailer()
	{
		return static::$Mailer ?? static::$Mailer = new Tools\Mailer( static::Factory() );
	}

	/**
	 * @return Tools\Formix
	 */
	public static function Formix()
	{
		return static::$Formix ?? static::$Formix = new Tools\Formix( static::Factory() );
	}

	/**
	 * @return Tools\Ajaxer
	 */
	public static function Ajaxer()
	{
		return static::$Ajaxer ?? static::$Ajaxer = new Tools\Ajaxer( static::Factory() );
	}
}
