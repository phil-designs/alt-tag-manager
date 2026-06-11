# PhilDesigns Alt Tag Manager

**Contributors:** phildesigns  
**Tags:** alt tags, accessibility, media library, images, seo  
**Requires at least:** 5.8  
**Tested up to:** 7.0  
**Stable tag:** 1.2.0  
**Requires PHP:** 7.4  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## Description

Find images missing alt tags in your media library and active theme templates. Fix them manually or auto-generate with AI (Anthropic Claude).

## Tested On
* Firefox
* Safari
* Chrome
* Opera
* MS Edge

## Website
https://www.phildesigns.com/

## Installation
1. Upload `alt-tag-manager` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Media > Alt Tag Settings** and enter your Anthropic API key to enable AI generation.
4. Go to **Media > Search Alt Tags** and scan your media library and active theme for missing alt tags.
5. Add alt tags manually, generate them with AI per image, or run a **Bulk AI Generate** for all missing tags at once.

## Key Features
- Full scan of the media library for missing alt tags
- Full scan of the active theme (parent and child) template files
- AI-powered alt tag generation via Anthropic Claude API
- Bulk AI generation — processes every untagged media image in one pass
- Rate-limit protection — 500ms delay between requests + automatic retry on 429 errors
- Content sync — saving an alt tag rewrites matching `<img>` tags across post content, CPTs, and ACF WYSIWYG fields
- CSV export and import for bulk manual updates

## Changelog

### 1.2.0
- Added bulk AI generation for all media library images missing alt tags
- Added rate-limit protection with 500ms delay and automatic retry on 429 errors
- Added content sync — alt tag saves now update matching `<img>` tags in post content, CPTs, and ACF WYSIWYG fields
- Added manual bulk updates via CSV import
- Added listener for media library changes

### 1.1.0
- Added parent theme tab and scan support
- Fixed child-theme scanner bug

### 1.0.0
- Initial release
