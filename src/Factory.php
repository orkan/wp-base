<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
namespace Orkan\WP\Base;

use Orkan\Config;

/**
 * Plugin: Factory.
 *
 * @author Orkan <orkans+wpbase@gmail.com>
 */
class Factory
{
	use Config;

	/**
	 * Plugin main identifiers which have to be overwritten in derived class.
	 *
	 * Moved here since Plugin class is not always instantiated,
	 * in contrast to Factory class which seems to be a must have.
	 *
	 * [SLUG]
	 * Unique WP plugin identifier saved in cfg[plu_slug] and used in:
	 * - options page url
	 * - options inputs namespace
	 * - DB cache & transients
	 * - JS namespace var name
	 */
	const NAME = '';
	const SLUG = '';
	const VERSION = '3.0.0';

	/**
	 * Services:
	 */
	protected $Plugin;
	protected $Settings;
	protected $Asset;

	/**
	 * Setup.
	 */
	public function __construct( array $cfg = [] )
	{
		$this->cfg = $cfg;

		if ( !static::NAME ) {
			throw new \RuntimeException( 'Missing required Factory::NAME constant!' );
		}

		if ( !static::SLUG ) {
			throw new \RuntimeException( 'Missing required Factory::SLUG constant!' );
		}
	}

	// =================================================================================================================
	// Services:
	// =================================================================================================================

	/**
	 * @return Plugin
	 */
	public function Plugin()
	{
		return $this->Plugin ?? $this->Plugin = new Plugin( $this );
	}

	/**
	 * @return Settings
	 */
	public function Settings()
	{
		return $this->Settings ?? $this->Settings = new Settings( $this );
	}

	/**
	 * @return Utils\Asset
	 */
	public function Asset()
	{
		/* @formatter:off */
		return $this->Asset ?? $this->Asset = new Utils\Asset([
			'assets'    => $this->get( 'plu_assets_loc' ),
			'filter'    => $this->get( 'plu_assets_filter' ),
			'version'   => $this->get( 'plu_version' ),
		]);
		/* @formatter:on */
	}
}
