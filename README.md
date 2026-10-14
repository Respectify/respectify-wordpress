# respectify-wordpress

Wordpress Comments integration with the Respectify API.

## Development

This uses `composer` for package management, with `php-scoper` for bundling libraries without versioning conflicts.

Use:
```bash
$ vendor/bin/php-scoper add-prefix --config=scoper.inc.php
$ cd build
$ composer dump-autoload -o
```
to add the scope prefix (RespectifyScoper, see scoper.inc.php.)

Often I have to do this twice. Why, I don't know. It's easy to test, by running a local WP instance - you will
get errors about class not found.

## Tests and static analysis

From `respectify/`, after `composer install`:

```bash
$ vendor/bin/phpunit
$ php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress
```

The tests run against the unscoped respectify-php library in `vendor/` and need the version `composer.json`
requires. To test against a local respectify-php checkout before that version is tagged, install it from a
Composer file outside the repo, so `composer.json` and `composer.lock` are not changed:

```bash
$ # local.json: a copy of composer.json, plus
$ #   "repositories": [{"type": "path", "url": "/path/to/respectify-php",
$ #                     "options": {"symlink": true, "versions": {"respectify/respectify-php": "0.7.0"}}}]
$ COMPOSER=/path/outside/repo/local.json composer update respectify/respectify-php
```

Run `composer install` afterwards to go back to the locked version.

# Building

For the actual build for Wordpress (publishing the plugin) go to the parent folder and run `$ build.sh`.

This does the above, but only with non-dev dependencies installed (ie not including php-scoper etc.) This
is required (a) just for neatness but also (b) because php-scoper installs Jetbrains editor config files
and that triggers WP's plugin checker due to naming.

So: build without any dev dependencies. The script of course does require php-scoper but runs it from
the development folder.


