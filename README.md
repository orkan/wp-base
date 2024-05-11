# WordPress plugin boilerplate `v1.0.1`
Extendable PHP classes for easy customization.

## Out-of-the-box:
- Dashboard: Settings page and unlimited Tools pages
- Custom DB cache
- Ajax requests support
- Custom admin Notices and Transients
- CSS/JS assets: build, minify & enqueue

## Introduction:
This package was made to provide a foundation to create your own WordPress plugin with a basic functionality already implemented, like the Settings page or DB cache supprort.

This package will install in Composer [vendor] dir, making it inaccessible from WordPress installation.
You must create your own folder in WP [plugins] dir and extend only those classes you will need in your project.
A working example of such plugin can be found here: [WP Base1](https://github.com/orkan/wp-base1)

## Setup:
Inside your WP plugin entry point file `[WP]/wp-content/plugins/[your_plugin]/plugin.php` you can `run()` only those parts of this package you want. A minimal example could be:

```php
namespace My\Name;
/*
 * Plugin Name: My plugin
 */
Factory::Plugin()->run();
```
The most important is the `Factory.php` class file, which holds the index of all classes used by this plugin.
For example we can create new Plugin class file to change the plugin name displayed in Dashboard:
```php
namespace My\Name;
class Plugin extends \Orkan\WP\Base\Plugin
{
	const NAME = 'My Plugin';
}
```
Then we must create new Factory class to let it pick our modified Plugin class instead:
```php
namespace My\Name;
class Factory extends \Orkan\WP\Base\Factory
{
	public static function Plugin()
	{
		return static::$Plugin ?? static::$Plugin = new Plugin( static::Factory() );
	}
}
```
Tip: The "magic" word `static` here, will pick our `Plugin` class from our own `My\Name` namespace.

## CSS/JS assets:
This package comes with some example Tools and their CSS/JS assets (`/assets` dir) and FORM input definitions ( `/config` dir). To run them, you'll need copy these folders from [vendor] to your [WP] plugin dir. Then in your `plugin.php` entry point add:
```php
Factory::Formix()->run(); // Example how to create custom FORM with various inputs
Factory::Mailer()->run(); // Example with WP Mail form
Factory::Ajaxer()->run(); // Example how to handle Ajax requests
Factory::Settings()->run(); // All Tools are internally a part of Settings page, so we need it too!
```
The links will show up in the: Dashboard > Plugins > My Plugin - meta row.
The CSS/JS assets are pre-build, but you can modify them and build again on each page refresh by adding these constants to `wp-config.php` file:
```php
define( 'ORK_ASSET_REBUILD_CSS', true );
define( 'ORK_ASSET_REBUILD_JS', true );
define( 'ORK_ASSET_MINIFY_CSS', true );
define( 'ORK_ASSET_MINIFY_JS', true );
//define( 'ORK_ASSET_FORCE_MIN', true );
```
In `debug` mode the plugin will rebuild and always enqueue the un-minified versions, unless `ORK_ASSET_FORCE_MIN` is set.
By default the `debug` mode is set to `true` if the Plugin::VERSION constant is set as `"@" . "Version@"` string.
However, this can also be set manually, from the `plugin.php` file as follows:
```php
Factory::cfg( 'debug', true );
```
Tip: Another way to rebuild assets is by using command: `composer run rebuildOrkWpBaseAssets` described in next section.

## Composer:
IMPORTANT: This README assumes following directory structure of your Composer/WordPress installation:
```
/htdocs
  |- /html <-- WordPress files
  |- /vendor <-- Composer files
```
Add composer autoloader to `wp-config.php` file:
```php
require __DIR__ . '/../vendor/autoload.php';
```
These are the required fields in your plugin's `composer.json` file:
```json
"name": "my/wp-plugin",
"type": "wordpress-plugin",
"require": {
	"orkan/wp-base": "^1"
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
These are the required fields in your root `composer.json` file:
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
Additionally, if you decide to build your assets automatically by using Composer scripts feature eg. on every "dump autoload", you can use the included method `Orkan\\WP\\Base\\Composer::rebuildAssets` form `/src/Composer.php` file, but that will require modyfication in your root composer.json:
```json
"config": {
	"vendor-dir": "vendor",
},
"extra": {
	"ork-wp-base": {
		"factory-class": "My\\Name\\Factory",
		"wp-config": "/../html/wp-config.php"
	}
},
"scripts": {
	"rebuildOrkWpBaseAssets": "Orkan\\WP\\Base\\Composer::rebuildAssets",
	"post-autoload-dump": [
		"@rebuildOrkWpBaseAssets"
	]
}
``` 

## Requirements:
- PHP ^7
- Composer ^2
- WordPress ^6

## License:
[MIT](https://github.com/orkan/wp-base/LICENCE)

## Author
[Orkan](https://github.com/orkan)

## Updated
Sat, 11 May 2024 17:35:49 +02:00
