#!/bin/bash

# AAVAC Bot - Build Script
# Creates a distributable ZIP file ready for WordPress installation

echo "🚀 Building AAVAC Bot plugin..."

# Configuration
PLUGIN_SLUG="aavac-bot"
PLUGIN_DIR="antek-chat-connector"
BUILD_DIR="build"
DIST_DIR="dist"
VERSION=$(grep "Version:" $PLUGIN_DIR/antek-chat-connector.php | awk '{print $3}')

echo "📦 Version: $VERSION"

# Clean previous builds
echo "🧹 Cleaning previous builds..."
rm -rf $BUILD_DIR
rm -rf $DIST_DIR
mkdir -p $BUILD_DIR
mkdir -p $DIST_DIR

# Copy plugin files
echo "📂 Copying plugin files..."
cp -r $PLUGIN_DIR $BUILD_DIR/$PLUGIN_SLUG

# Copy documentation (user-facing only)
echo "📚 Copying documentation..."
cp README.md $BUILD_DIR/$PLUGIN_SLUG/
cp QUICK-START.md $BUILD_DIR/$PLUGIN_SLUG/
cp SETUP-GUIDE.md $BUILD_DIR/$PLUGIN_SLUG/

# Remove development files
echo "🗑️  Removing development files..."
cd $BUILD_DIR/$PLUGIN_SLUG

# Remove git files
rm -rf .git
rm -f .gitignore
rm -f .gitattributes

# Remove development docs
rm -f CLAUDE.md
rm -f IMPLEMENTATION-SUMMARY.md

# Remove IDE files
rm -rf .vscode
rm -rf .idea
rm -f *.code-workspace

# Remove macOS files
find . -name ".DS_Store" -delete
find . -name "._*" -delete

# Remove backup files
find . -name "*~" -delete
find . -name "*.bak" -delete
find . -name "*.swp" -delete

# Remove node_modules if exists
rm -rf node_modules

# Remove test files if exists
rm -rf tests

cd ../..

# Create ZIP file
echo "📦 Creating ZIP file..."
cd $BUILD_DIR
zip -r ../dist/$PLUGIN_SLUG-$VERSION.zip $PLUGIN_SLUG -q
cd ..

# Create checksums
echo "🔐 Generating checksums..."
cd $DIST_DIR
sha256sum $PLUGIN_SLUG-$VERSION.zip > $PLUGIN_SLUG-$VERSION.zip.sha256
md5sum $PLUGIN_SLUG-$VERSION.zip > $PLUGIN_SLUG-$VERSION.zip.md5
cd ..

# Cleanup build directory
echo "🧹 Cleaning up..."
rm -rf $BUILD_DIR

# Done
echo "✅ Build complete!"
echo ""
echo "📦 Distribution package:"
echo "   $DIST_DIR/$PLUGIN_SLUG-$VERSION.zip"
echo ""
echo "🔐 Checksums:"
echo "   SHA256: $DIST_DIR/$PLUGIN_SLUG-$VERSION.zip.sha256"
echo "   MD5:    $DIST_DIR/$PLUGIN_SLUG-$VERSION.zip.md5"
echo ""
echo "📊 Package size:"
du -h $DIST_DIR/$PLUGIN_SLUG-$VERSION.zip
echo ""
echo "🎉 Ready for installation!"
echo ""
echo "To install:"
echo "  1. Go to WordPress Admin → Plugins → Add New"
echo "  2. Click 'Upload Plugin'"
echo "  3. Choose: $DIST_DIR/$PLUGIN_SLUG-$VERSION.zip"
echo "  4. Click 'Install Now' → 'Activate Plugin'"
