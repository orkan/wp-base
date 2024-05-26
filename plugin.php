<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
namespace Orkan\WP\Base;

/*
 * Plugin Name: Example WP Plugin entry point file
 * Description: WordPress plugin boilerplate.
 * Version: 3.0.0
 * Date: Sun, 26 May 2024 17:31:07 +02:00
 * Author: Orkan <orkans+wpbase@gmail.com>
 * Author URI: https://github.com/orkan
 */
$Factory = new Factory();
$Factory->Plugin()->run();
unset( $Factory );
