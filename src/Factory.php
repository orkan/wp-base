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
	 * @return Asset
	 */
	public function Asset()
	{
		/* @formatter:off */
		return $this->Asset ?? $this->Asset = new Utils\Asset([
			'assets_loc' => $this->get( 'plu_assets_loc' ),
			'version'    => $this->get( 'plu_version' ),
		]);
		/* @formatter:on */
	}
}
