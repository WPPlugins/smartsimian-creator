=== SmartSimian Creator ===
Contributors: SeventhSteel
Donate link: http://www.smartsimian.com/
Tags: custom post types, custom taxonomies, custom fields, fields, content management, post types, content types, connection types, direct connections, relationships, user fields
Requires at least: 3.4
Tested up to: 3.9
Stable tag: 2.0.1
License: GPL2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Take your website to the next level of customization.

== Description ==

Whatever type of website you need, Creator from [SmartSimian Software](http://www.smartsimian.com/) can get you there.

Instead of being limited to WordPress's out-of-the-box blogging tools -- posts, pages, tags, comments -- Creator allows you to create your own custom content, fields, taxonomies, and connections.

**Content types** let you create custom content based on what you need, such as:

* Portfolio Items
* Presentations
* Events
* Properties
* Menu Items
* Churches
* Movie Reviews

**Fields** let you add information to your content:

* Simple text and number fields
* Richly-editable text boxes
* True-or-false checkboxes
* Links and URLs
* File uploads
* Date/time fields

**Taxonomies** are ways to categorize your content with dropdowns or checklists, such as:

* Event Type
* Movie Rating
* Issue
* Location

**Connections types** let you create direct relationships between your content or users, such as:

* Articles connected to multiple authors
* Events connected to Attendees
* Car Models connected to Car Brands
* Ingredients connected to Recipes

If you like Creator, add-ons available at [smartsimian.com](http://www.smartsimian.com/) allow you to go further:

* Create custom displays and views for your content with [Templates](http://smartsimian.com/downloads/smartsimian-templates/)
* Create lists of content and easy-to-build search forms with [Search](http://smartsimian.com/downloads/smartsimian-search/)
* Add submission forms with [Submissions](http://smartsimian.com/downloads/smartsimian-submissions/)
* Analyze your data in CSV exports with [Reports](http://smartsimian.com/downloads/smartsimian-reports/)

== Installation ==

= Automatic Install =

1. Log into your WordPress dashboard and go to Plugins &rarr; Add New
2. Search for "SmartSimian Creator"
3. Click "Install Now"
4. Click "Activate Now"

= Manual Install =

1. Download the plugin from the download button on this page
2. Unzip the file, and upload the resulting `smartsimian-creator` folder to your `/wp-content/plugins` directory
3. Log into your WordPress dashboard and go to Plugins
4. Activate SmartSimian Creator

== Screenshots ==

1. Adding a content type
2. Viewing our sample custom content types
3. Adding fields
4. The fields in action
5. The resulting content, organized

== Changelog ==

= 2.0.1 =

* Update to box.js for BackBone 1.1 (WP 3.9 update)
* Turn developer constants off

= 2.0.0 =

* Redesigned, responsive layouts for admin single pages
* Bulk importer/exporter
* Integrated WP dashicons as new icon set
* "Are you sure" message before deletions
* Simple UI tooltip system
* Allow content types to include title, editor, other default meta boxes
* Hide title/slug template options for content by default
* Taxonomies and connection types can now be added/synced when creating fields
* Taxonomy fields now have "order" option to go with orderby
* Connections can now have more than one content type per side
* Extracted WYSIWYG option in longtext field types to a rich_text field type
* Removed connection sort/label options
* Better handling of connections with the same content type on both sides
* System names can now be edited when adding a new item
* Updated P2P engine
* Remember active tab after form submit
* Maintain taxonomy hierarchy in checklists
* Fixed quick-edit repopulation issue
* Post titles now constantly filtered according to title_template
* Better handling for link fields
* Removed dependency on a late static binding
* Improved connection type labels in various places in the UI
* Cleaner sysname generation
* Query UI narrowing options can now check against global post
* In UI list screens, default showing is now 20
* Many UI text changes
* Many more bug fixes

= 1.2.1 =

* Added generic $before and $after parameter to div_container
* A few undefined index fixes for licenses
* Updates to license screen, fixes erroneous expired license notification

= 1.2.0 =

* Extracted template component functionality
* Turned on licensing for extensions
* Added 'network' to reserved terms list
* Fixed undefined index bug in simian_check_for_updates

= 1.1.4 =

* Removed license requirement for base plugin only

= 1.1.3 =

* Fixed bug hiding title fields on non-Simian post type edit screens

= 1.1.2 =

* Added GPL2 language
* false_label now shows up in templates
* Cleaned up title/slug handling on post edit screens
* simian_get_fields now able to return only meta fields
* Added classes to fieldset table output
* Updated jQuery timepicker to 1.4
* Added jQuery autosize plugin for UI text boxes
* Text boxes now use code font where appropriate
* Changes simian_is_json behavior to fix Dismiss button bug
* excerpt_more now accepts HTML
* Added admin notice if permalinks are not turned on
* Changed Builder language to Creator

= 1.1.1 =

* Multiple meta/tax rows in query UI now counted properly
* Added stripslashes where necessary in admin UI columns, text fields
* Minor UI fixes in uploads.js
* true_label and false_label now accept HTML
* Added empty_option option for text fields
* Reworded a few connection error messages
* Added filter to run before a p2p connection occurs to check for custom errors
* Added user query support to simian_get_pagination
* User templates now properly categorized as such on template list page
* Properly hide inner containers in newly-generated rows in repeater
* Disabled duplicate-clicks on admin pages with javascript method

= 1.1.0 =

* Added complete licensing/updating system. Turned off by default
* Re-arranged home screen; components no longer need activation
* Removed a general option and cleaned up other option wording
* Added extension and license API functions, modified components API
* Switched to wp_filter_post_kses from wp_kses_post for html sanitization
* Minor cleanup of uploads.js
* Add nopriv ajax hook for not-logged-in submission form uploads
* Added wp_link_pages as template block option
* Enabled filters when outputting excerpt or content template blocks
* Allow non-custom content types to create templates
* Properly stripslashes in before, after, and alt text sections of template UI
* Updated wording for use_template functionality in template UI
* Fixed featured image support in submission forms
* Template blocks now respect unfiltered_html capability
* Shortcodes in templates now more reliable
* Removed uninstall.php
* Template generation now accounts for multiple uploads per field properly

= 1.0.4 =

* Logic cleanup when saving meta fields
* Partial support for connection rules in query ui added
* Fixed text_template bug in which full p2p object was called instead of name
* Allow simian_add_item to force a system name or fail
* Content types now have show_ui always on but show_in_menu toggleable
* Added clean display error on fields if connection type doesn't exist
* Set new character limits for content type, taxonomy, and connection type system names
* Fix embedding when filtering content
* Fixed template UI bug in which image_link could not stay set to blank

= 1.0.3 =

* Custom term and connection links now just run through kses instead of sanitize_text_field
* Added additional taxonomy options to Template UI to show slugs, descriptions, count
* Added 'user' to reserved terms list
* Attempt to generate sysname in simian_add_item
* Deleted outdated jquery ui files
* Scoped jQuery UI CSS to just inside 'simian' class; added option to disable entirely
* Changed sort() to asort() in Fields UI (fixes bug in commit #52)
* Removed extra if conditional in fields output preventing submitted data from reappearing after error
* Connection Type sysnames now forced to remain below 44 characters
* Display content types next to taxonomy labels in Fields UI
* Select2 ajax multiselect for posts now limits to relevant post type
* Removed Connection UI Advanced tab and all options within
* Reordered component load to tweak Builder submenu order
* Added filter for connection type args
* Minor css and text cleanup, fixed path to jquery ui images

= 1.0.2 =

* Reorganized core/includes/functions.php
* Added Item API functions simian_add_item, simian_update_item, simian_delete_item, simian_restore_item
* Fixed bug in UI displaying content title and slug selections
* Builder toolbar item now only appears in admin and only for users with manage_options cap
* Fieldset connection list now populates correctly (recent bug)
* In Query UI, inner row elements generated after extra rows have been added below now generate the correct counter
* Query UI's generated list of meta fields now no longer includes non-meta fields, also will display sysname if no label
* Wrapping a link around each inner template of a template now works
* Template UI Options style tweak while selecting inner templates
* Template UI text clarifications. "Use As" switched to "Location", drop down option labels refined.
* When saving results in error on single pages, entered data will now persist on all components

= 1.0.1 =

* Removed references to Simian_Help
* Removed contextual help tab test
* Allow URL label in template builder to use HTML
* Added 'date' to reserved terms list to avoid admin column conflict
* Don't allow creating fields in fieldsets for connection types with same 'from' and 'to' value
* Disabled can_create_post option for connection types in field sets

= 1.0.0 =

* Initial release.