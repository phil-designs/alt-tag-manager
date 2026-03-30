# Alt Tag Manager

Tags: javascript, php
Requires at least: 3.6.0
Tested up to: 6.9.1
License: GPL2

## Description

Find images missing alt tags in the media library and in active theme templates. Add tags manually or auto-generate them with AI.

## Tested on 
* Firefox 
* Safari
* Chrome
* Opera
* MS Edge

## Website 
http://www.phildesigns.com/

## Installation 
1. Upload ‘alt-tag-manager’ to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add you Anthropic API key on the Media > Alt Tag Settings page for AI alt tag generation
4. Got to the Media > Search Alt Tags page and scan your library and active theme for missing alt tags.
5. Add alt tags manually or use the AI generated alt tag option or Bulk AI generated option.

## Highlights
* Bulk AI generation — generates alt tags for all images missing one in the media library
* Rate limit protection — 500ms delay between requests + automatic retry on 429 errors
* Content sync — when an alt tag is saved (manually or AI), it updates matching <img> tags across all post content, CPTs, and ACF WYSIWYG fields

## Key Features
- Full scan of the media library for missing alt tags
- Full scan of active theme or parent and child theme for missing alt tags within the template markup
- CSV file down load
- Bulk generated tags via AI or manually using the csv file import
- Auto update for the database when alt tags are added or updated

## Changelog 

Version 1.2.0
• Initial release

Version 1.1.0 
• Minor bump for the parent theme tab feature + the child-theme scanner bug fix

Version 1.0.0
• Initial release.
