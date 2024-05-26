# WordPress plugin boilerplate `v3.0.0`
Extendable PHP classes for easy customization.

## Out-of-the-box:
- Dashboard: Settings and Tools pages (unlimited)
- Custom DB cache
- Ajax requests support
- Custom admin Notices and Transients
- CSS/JS assets: combine from partials, build, minify & enqueue

## Introduction:
This package was made to provide a foundation to create your own WordPress plugin with a basic functionality already implemented, like the Settings page or DB cache supprort.

This package will install in Composer [vendor] dir, making it inaccessible from WordPress installation.
You must create your own folder in WP [plugins] dir and extend only those classes you will need in your project.
A working example of such plugin can be found here: [Base1](https://github.com/orkan/wp-base1)

## Create plugin:
Create your plugin entry point file `[WP]/wp-content/plugins/[your_plugin]/plugin.php`
then  `run()` only the necessary parts:
```php
namespace My\Name;
/*
 * Plugin Name: My plugin
 */
$Factory = new Factory();
$Factory->Plugin()->run();
$Factory->Settings()->run();
```

## Tools:
The [Base1](https://github.com/orkan/wp-base1) package comes with some example Tools and their CSS/JS assets (`/assets` dir) and FORM input definitions ( `/config` dir). The included Tools are:
```php
$Factory->Mailer()->run(); // Example with WP Mail form
$Factory->Formix()->run(); // Example how to create custom FORM with various inputs
$Factory->Ajaxer()->run(); // Example how to handle Ajax requests
```
Links to these Tools will be automatically displayed in the: Dashboard > Plugins > My Plugin - meta row.

## CSS/JS assets:
The example CSS/JS assets are pre-build and minified, but you can modify them and re-build on each page refresh by adding these constants to `wp-config.php` file:
```php
define( 'ORK_ASSET_REBUILD_CSS', true );
define( 'ORK_ASSET_REBUILD_JS', true );
define( 'ORK_ASSET_MINIFY_CSS', true );
define( 'ORK_ASSET_MINIFY_JS', true );
```
Another way to rebuild assets is by using a Composer script command: `composer run rebuildOrkWpBaseAssets` described in next section.

## Composer:
Composer is required to install this plugin and all its dependencies and also to support autoloading class files.
This README assumes following directory structure in your Composer/WordPress installation:
```
/htdocs
  |- /html <-- WordPress files
  |- /vendor <-- Composer files
  |- composer.json <-- root file
```
Add composer autoloader to `wp-config.php` file:
```php
require __DIR__ . '/../vendor/autoload.php';
```
Turn your plugin into Composer package by adding these lines in your plugin's `composer.json` file:
```json
"name": "my/wp-plugin",
"type": "wordpress-plugin",
"require": {
	"orkan/wp-base": "^3"
},
"autoload": {
	"psr-4": {
		"My\\Name\\": "src"
	}
},
"extra": {
	"installer-name": "my-plugin"
}
```
To properly install WP plugins from Composer repositories add these lines  to your root `composer.json` file:
```json
"extra": {
	"installer-paths": {
		"html/wp-content/plugins/{$name}": [
			"type:wordpress-plugin"
		],
		"html/wp-content/themes/{$name}": [
			"type:wordpress-theme"
		]
	}
}
```
If you decide to build your assets automatically by using Composer scripts feature eg. on every "dump autoload", you can use the included method `Orkan\\WP\\Base\\Utils\\Composer::rebuildAssets`
```json
"extra": {
	"ork-wp-base": {
		"wp-config": "html/wp-config.php"
	}
}
"scripts": {
	"rebuildMyPluginAssets": [
		"@putenv FACTORY=Orkan\\WP\\MyPlugin\\Factory",
		"Orkan\\WP\\Base\\Utils\\Composer::rebuildAssets"
	],
	"post-autoload-dump": [
		"@rebuildMyPluginAssets"
	]
}
``` 
Now you can also manually rebuild assets by running this command: `composer run rebuildMyPluginAssets`

## Requirements:
- PHP ^7
- Composer ^2
- WordPress ^6

## License:
[MIT](https://github.com/orkan/wp-base/LICENCE)

## Author
[Orkan](https://github.com/orkan)

## Updated
Sun, 26 May 2024 17:31:07 +02:00
