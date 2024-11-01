=== Upload to Envira from Gravity Forms ===
Contributors: rhinogroup
Tags: gravity forms,envira gallery
Requires at least: 4.0
Tested up to: 5.1.0
Stable tag: 1.1.0
Requires PHP: 5.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Attaches photos posted in a Gravity Form to a selected Envira Gallery.

== Description ==

Attaches photos posted in a Gravity Form to a selected Envira Gallery.

Once installed, a new section will appear in the Settings for your Gravity Form, called Envira Gallery.

You'll find the following settings for each Form:

- Enable/disable functionality
- Select Gallery to save photo into
- Publish status of photo (Published | Draft)
- Select GF Field to use as photo title
- Select GF Field to use as photo caption

== Installation ==

Use WordPress’ Add New Plugin feature, searching “Envira Gravity Forms”, or download the archive and:

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Create/Edit your Gravity Form
1. Access the Form Settings to manage the Envira Gallery section


== Frequently Asked Questions ==

= Can each form push photos into a different gallery? =

Yes. The plugin enables separate settings for each form.

= Can the photos be posted in draft status so they don't show up until being approved? =

Yes. Use the Publish Status option to set new uploads to Draft status.

== Screenshots ==

1. The Envira Gallery settings in a Gravity Form.
2. A photo posted in a Gravity Form and saved into an Envira Gallery.

== Changelog ==

= 1.1.0 =
* Enhancement - Added support for GF multi-file uploader.
* Fix - Corrected error in GF settings when no Envira galleries have been created.

= 1.0.1 =
* Dev - Corrected plugin slug naming issue.

= 1.0 =
* Initial release.