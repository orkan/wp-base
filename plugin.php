<?php
/*
 * This file is part of the orkan/wp-base package.
 * Copyright (c) 2024 Orkan <orkans+wpbase@gmail.com>
 */
namespace Orkan\WP\Base;

/*
 * Plugin Name: Ork Base
 * Description: WordPress plugin boilerplate.
 * Version: 1.0.0
 * Date: Sat, 11 May 2024 16:28:53 +02:00
 * Author: Orkan <orkans+wpbase@gmail.com>
 * Author URI: https://github.com/orkan
 */

// =====================================================================================================================
// All pages
Factory::Factory()->merge([
// 	'debug'   => false,
	'premium' => false,
]);
Factory::Plugin()->run();

// =====================================================================================================================
// Dashboard, Ajax
if ( is_admin() ) {
	Factory::Mailer()->run();
	Factory::Formix()->run();
	Factory::Ajaxer()->run();
	Factory::Settings()->run(); // Register tools first!
}
