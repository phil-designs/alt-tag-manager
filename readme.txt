=== PhilDesigns Alt Tag Manager ===
Contributors: phildesigns
Tags: alt tags, accessibility, media library, images, seo
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find images missing alt tags in your media library and active theme templates. Fix them manually or auto-generate with AI.

== Description ==

**PhilDesigns Alt Tag Manager** scans your WordPress media library and active theme templates for images missing alt text, then lets you fix every gap without leaving the admin — type alt tags by hand, or let Anthropic Claude write them for you.

**Features**

* Full scan of the media library for images missing alt tags
* Full scan of the active theme (parent and child) template files for hard-coded `<img>` tags missing alt attributes
* AI-powered alt tag generation via the Anthropic Claude API
* Bulk AI generation — generates alt tags for every untagged image in the media library in one pass
* Rate-limit protection — 500ms delay between API requests plus automatic retry on 429 errors
* Content sync — when an alt tag is saved (manually or via AI), matching `<img>` tags are updated across all post content, custom post types, and ACF WYSIWYG fields
* CSV export for the media library and theme scans
* CSV import for bulk manual updates
* Ignore individual theme issues so repeat false-positives stay out of your scan results

== Installation ==

1. Upload the `alt-tag-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Media > Alt Tag Settings** and paste in your Anthropic API key to enable AI generation (optional — manual tagging works without it).
4. Go to **Media > Search Alt Tags** and run a scan of your media library and active theme.
5. Add alt tags manually per image, generate them one at a time with AI, or click **Bulk AI Generate** to process every missing tag in one go.

== Frequently Asked Questions ==

= Do I need an Anthropic API key? =

Only for AI-powered alt tag generation. Scanning for missing tags and adding them manually works without any API key.

= What AI model is used? =

Alt tags are generated using Anthropic's Claude API. Claude analyzes each image and writes a concise, accessibility-focused description.

= Will saving an alt tag also update my post content? =

Yes. When an alt tag is saved — manually or via AI — the plugin finds every published post, page, and custom post type whose content references that attachment and rewrites the `alt` attribute in place. ACF WYSIWYG field content is included.

= What does the theme scanner check for? =

It parses every `.php` template file in your active theme directory (and parent theme, if applicable) and flags hard-coded `<img>` tags that are missing an `alt` attribute or have an empty one.

= Can I bulk-update alt tags without AI? =

Yes. Export the media library CSV, fill in the **Alt Tag** column for each image, then re-import the file using the **Import CSV** button. The plugin will bulk-update every matched attachment.

== Screenshots ==

1. Media library scan — images missing alt tags displayed with inline edit fields.
2. AI-generated alt tag suggestion for a single image.
3. Settings page for entering the Anthropic API key.

== Changelog ==

= 1.2.0 =
* Added bulk AI generation for all media library images missing alt tags.
* Added rate-limit protection: 500ms delay between requests and automatic retry on 429 errors.
* Added content sync — saving an alt tag now rewrites matching `<img>` tags in post content, CPTs, and ACF WYSIWYG fields.
* Added manual bulk updates via CSV import.
* Added listener for media library changes.

= 1.1.0 =
* Added parent theme tab and scan support.
* Fixed child-theme scanner bug.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.0 =
Adds bulk AI generation, content sync, and CSV import. No breaking changes — safe to update.
