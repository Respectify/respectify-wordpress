# respectify-wordpress

Wordpress Comments integration with the Respectify API.

## Development

This uses `composer` for package management, with `php-scoper` for bundling libraries without versioning conflicts.

Use:
```bash
$ vendor/bin/php-scoper add-prefix --config=scoper.inc.php
cd build
$ composer dump-autoload -o
```
to add the scope prefix (RespectifyScoper, see scoper.inc.php.)
