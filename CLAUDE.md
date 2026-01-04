# Respectify WordPress Plugin

## Building and Releasing

### Build the plugin

```bash
cd "/Users/work/Documents/Windows Documents/projects/My stuff/github/respectify/respectify-wordpress"
./build.sh
```

This runs PHPStan, builds with scoped dependencies, and copies to `../respectify-wordpress-svn/trunk/`.

### Publish to WordPress.org

After building, go to the SVN directory and run these commands in order:

```bash
cd "../respectify-wordpress-svn"

# 1. Check for new/changed files
svn status

# 2. Add any new files (shown as ? in status)
svn add trunk/path/to/new/file.png

# 3. Commit trunk changes
svn commit -m "Release version X.X.X" --username respectify

# 4. Create the tag from trunk
svn cp trunk tags/X.X.X

# 5. Commit the tag
svn commit -m "Tagging version X.X.X" --username respectify
```

### Version Locations

Update version in these files before building:
- `respectify/respectify.php` - Plugin header `Version:` and `define('RESPECTIFY_VERSION', ...)`
- `respectify/README.txt` - `Stable tag:` and changelog section
- `respectify/includes/respectify-constants.php` - `RESPECTIFY_VERSION` constant
