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

# Building

For the actual build for Wordpress (publishing the plugin) go to the parent folder and run `$ build.sh`.

This does the above, but only with non-dev dependencies installed (ie not including php-scoper etc.) This
is required (a) just for neatness but also (b) because php-scoper installs Jetbrains editor config files
and that triggers WP's plugin checker due to naming.

So: build without any dev dependencies. The script of course does require php-scoper but runs it from
the development folder.


