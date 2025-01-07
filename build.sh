#!/bin/bash

# Exit immediately if a command exits with a non-zero status
set -e

# Get the absolute path of the current script's directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Define directories with absolute paths
PLUGIN_DIR="$(realpath "$SCRIPT_DIR/respectify")"
TEMP_BUILD_DIR="$SCRIPT_DIR/temp_build"
FINAL_BUILD_DIR="$SCRIPT_DIR/build"

# Define the absolute path to php-scoper
PHPCS_PREFIXER="$PLUGIN_DIR/vendor/bin/php-scoper"

echo "PLUGIN_DIR: $PLUGIN_DIR"
echo "TEMP_BUILD_DIR: $TEMP_BUILD_DIR"
echo "FINAL_BUILD_DIR: $FINAL_BUILD_DIR"
echo "PHPCS_PREFIXER: $PHPCS_PREFIXER"

# Check if php-scoper exists
if [ ! -f "$PHPCS_PREFIXER" ]; then
  echo "Error: php-scoper not found at '$PHPCS_PREFIXER'. Please run 'composer install' in '$PLUGIN_DIR' first."
  exit 1
fi

# Ensure the build directory exists
if [ ! -d "$FINAL_BUILD_DIR" ]; then
  echo "Build directory '$FINAL_BUILD_DIR' does not exist. Creating it..."
  mkdir -p "$FINAL_BUILD_DIR"
  echo "Build directory created."
fi

# Delete all contents inside TEMP_BUILD_DIR and FINAL_BUILD_DIR without deleting the directories themselves
echo "Deleting contents of TEMP_BUILD_DIR: $TEMP_BUILD_DIR"
if [ -d "$TEMP_BUILD_DIR" ]; then
  find "$TEMP_BUILD_DIR" -mindepth 1 -delete
  echo "Contents of '$TEMP_BUILD_DIR' deleted."
else
  echo "Temporary build directory '$TEMP_BUILD_DIR' does not exist. Creating it..."
  mkdir -p "$TEMP_BUILD_DIR"
  echo "Temporary build directory created."
fi

echo "Deleting contents of FINAL_BUILD_DIR: $FINAL_BUILD_DIR"
find "$FINAL_BUILD_DIR" -mindepth 1 -delete
echo "Contents of '$FINAL_BUILD_DIR' deleted."

echo "Step 1 completed."


# Step 2: Create Temporary Build Directory
echo "Creating temporary build directory..."
mkdir -p "$TEMP_BUILD_DIR"

# Step 3: Copy Essential Files and Directories to Temporary Build Directory
echo "Copying essential files and directories to temporary build directory..."

# List of essential files
ESSENTIAL_FILES=(
  "readme.txt"
  "license.txt"
  "index.php"
  "respectify.php"
  "uninstall.php"
  "composer.json"
  "composer.lock"
  "scoper.inc.php"
)

# Copy essential files
for file in "${ESSENTIAL_FILES[@]}"; do
  if [ -f "$PLUGIN_DIR/$file" ]; then
    cp "$PLUGIN_DIR/$file" "$TEMP_BUILD_DIR/"
    echo "Copied $file"
  else
    echo "Warning: '$file' not found in '$PLUGIN_DIR/'. Skipping."
  fi
done

# List of essential directories
ESSENTIAL_DIRS=(
  "public"
  "admin"
  "includes"
)

# Copy essential directories
for dir in "${ESSENTIAL_DIRS[@]}"; do
  if [ -d "$PLUGIN_DIR/$dir" ]; then
    cp -r "$PLUGIN_DIR/$dir" "$TEMP_BUILD_DIR/"
    echo "Copied directory $dir/"
  else
    echo "Warning: Directory '$dir/' not found in '$PLUGIN_DIR/'. Skipping."
  fi
done

# Debug: print what's there
echo "Listing contents of TEMP_BUILD_DIR:"
ls -la "$TEMP_BUILD_DIR"

# Add any additional files or directories as needed
# Example: cp -r "$PLUGIN_DIR/assets" "$TEMP_BUILD_DIR/"
# Uncomment and modify the line below if you have additional directories
# cp -r "$PLUGIN_DIR/assets" "$TEMP_BUILD_DIR/"

# Step 4: Install Production Dependencies in Temporary Build Directory
echo "Installing production dependencies in temporary build directory..."
cd "$TEMP_BUILD_DIR"
composer install --no-dev --optimize-autoloader --no-interaction
cd ..

# debug
echo "Checking if vendor directory exists in TEMP_BUILD_DIR:"
if [ -d "$TEMP_BUILD_DIR/vendor" ]; then
  echo "vendor directory exists."
else
  echo "Error: vendor directory does not exist in TEMP_BUILD_DIR."
  exit 1
fi

# Step 4.1: Copy exclude-wordpress-* files to TEMP_BUILD_DIR
echo "Step 4.1: Copying exclude-wordpress-* files to temporary build directory..."

# Define the array of exclude filenames
EXCLUDE_FILES=(
  "exclude-wordpress-classes.json"
  "exclude-wordpress-functions.json"
  "exclude-wordpress-constants.json"
  # Add more exclude files here as needed
)

# Iterate over each exclude file and copy it
for exclude_file in "${EXCLUDE_FILES[@]}"; do
  # Define source and destination paths
  EXCLUDE_SOURCE="$PLUGIN_DIR/vendor/sniccowp/php-scoper-wordpress-excludes/generated/$exclude_file"
  EXCLUDE_DEST="$TEMP_BUILD_DIR/vendor/sniccowp/php-scoper-wordpress-excludes/generated/$exclude_file"
  
  # Create destination directory if it doesn't exist
  mkdir -p "$(dirname "$EXCLUDE_DEST")"
  
  # Copy the exclude file if it exists
  if [ -f "$EXCLUDE_SOURCE" ]; then
    cp "$EXCLUDE_SOURCE" "$EXCLUDE_DEST"
    echo "Copied $exclude_file to '$EXCLUDE_DEST'."
  else
    echo "Error: Exclude file '$EXCLUDE_SOURCE' does not exist."
    exit 1
  fi
done

echo "All exclude-wordpress-* files copied successfully."

# Check if php-scoper exists
if [ ! -f "$PHPCS_PREFIXER" ]; then
  echo "Error: php-scoper not found at '$PHPCS_PREFIXER'. Please run 'composer install' in '$PLUGIN_DIR' first."
  exit 1
else
  echo "php-scoper found at '$PHPCS_PREFIXER'."
fi

# Step 5: Run PHP-Scoper on Temporary Build Directory and Output to Final Build Directory
echo "Running PHP-Scoper to prefix namespaces..."
echo "php-scoper path: $PHPCS_PREFIXER"
echo "Configuration file: $TEMP_BUILD_DIR/scoper.inc.php"
echo "Working directory: $TEMP_BUILD_DIR"

"$PHPCS_PREFIXER" add-prefix \
  --config="$TEMP_BUILD_DIR/scoper.inc.php" \
  --working-dir="$TEMP_BUILD_DIR" \
  --force

# Delete the copied sniccowp/php-scoper-wordpress-excludes files, because they trigger 
# the check of php-scoper in the final build directory
echo "Deleting sniccowp/php-scoper-wordpress-excludes files from TEMP_BUILD_DIR..."
rm -rf "$TEMP_BUILD_DIR/vendor/sniccowp/php-scoper-wordpress-excludes"

# Delete the respectify-php/phpdocumentor-markdown-customised files
# Not required (it's for building doc) and the Wordpress plugin-check plugin flags it
# because json_encode is discouraged
echo "Deleting respectify-php/phpdocumentor-markdown-customised files from TEMP_BUILD_DIR..."
rm -rf "$TEMP_BUILD_DIR/build/respectify/respectify-php/phpdocumentor-markdown-customised"


# Copy the prefixed files to the final build directory
# Create Temporary Build Directory
echo "Creating final build directory..."
mkdir -p "$FINAL_BUILD_DIR"
echo "Copying prefixed files to final build directory..."
cp -r "$TEMP_BUILD_DIR/"* "$FINAL_BUILD_DIR/"

# debug:
echo "Listing contents of FINAL_BUILD_DIR:"
ls -la "$FINAL_BUILD_DIR"

# Step 6: Clean Up Temporary Build Directory
echo "Cleaning up temporary build directory..."
rm -rf "$TEMP_BUILD_DIR"

# Step 7: Verify Build Directory for Unwanted Files
echo "Verifying build directory for unwanted files..."
UNWANTED_PATTERNS=("jetbrains" "php-scoper")

for pattern in "${UNWANTED_PATTERNS[@]}"; do
  if find "$FINAL_BUILD_DIR" -iname "*$pattern*" | grep .; then
    echo "Error: '$pattern' tools found in the build directory."
    exit 1
  fi
done

echo "No unwanted tools found. Build is clean."

echo "Build completed successfully. Check the '$FINAL_BUILD_DIR' directory."
