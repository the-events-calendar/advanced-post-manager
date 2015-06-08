=== Advanced Post Manager ===

Contributors: ModernTribe, mattwiebe, jkudish, nickciske, peterchester, shanepearlman, borkweb, zbtirrell
Donate link: http://m.tri.be/4o
Tags: developer-tools, custom post, filter, column, metabox, taxonomy, wp-admin, admin, Post, post type, plugin, advanced, tribe
Requires at least: 3.2
Tested up to: 4.2.2
License: GPL v2
Stable Tag: 3.10

Turbo charge your posts admin for any custom post type with sortable filters and columns, and auto-registration of metaboxes.

== Description ==

This is a tool for developers who want to turbo-charge their custom post type listings with metadata, taxonomies, and more. An intuitive interface for adding (and saving) complex filtersets is provided, along with a drag-and-drop interface for choosing and ordering columns to be displayed. Metaboxes are also automatically generated for all your metadata-entry needs.

* Add columns to the post listing view
* Filter post listings by custom criteria
* Easily add metaboxes to custom post types
* Automatically add registered taxonomies to post listings
* Sort by post metadata

See docs/documentation.html in the plugin directory for full documentation.

The team at Modern Tribe stands by our work and offers light support every Wednesday to the community via the WordPress.org support forums. Feel free to ask a question if you're having a problem with implementation or if you find bugs.

= SUBMITTING PATCHES =

If you’ve identified a bug and want to submit a patch, we’d welcome it at our <a href=“https://github.com/moderntribe/advanced-post-manager”>GitHub page for Advanced Post Manager.</a> Simply cue up your proposed patch as a pull request, and we’ll review as part of our monthly release cycle and merge into the codebase if appropriate from there. (If a pull request is rejected, we’ll do our best to tell you why). Users whose pull requests are accepted will receive credit in the plugin’s changelog. For more information, check out the readme at our GitHub page. Happy coding!

== Frequently Asked Questions ==

= Why doesn't anything happen when I activate the plugin? =

This plugin is for developers. Nothing will happen until you write some code to take advantage of the functionality it offers.

= Translators (mostly imported from Events Calendar PRO) =

* Afrikaans from Liza Welsh
* Brazilian Portuguese from Gustavo Bordoni
* British English from John Browning
* Bulgarian from Nedko Ivanov
* Chinese from Massound Huang
* Czech from Petr Bastan
* Danish from Hans Christian Andersen
* Dutch from Dirk Westenberg
* Estonian from Andra Saimre
* Finnish by Elias Okkonen
* French from Sylvain Delisle
* German from Oliver Heinrich
* Greek from Yannis Troullinos
* Hungarian from Balazs Dobos
* Icelandic by Baldvin Örn Berndsen
* Indonesian by Didik Priyanto
* Italian from Gabriele Taffi
* Latvian from Raivis Dejus
* Lithuanian from Gediminas Pankevicius
* Portuguese from Sérgio Leite
* Romanian from Cosmin Vaman
* Russian from Alexander Tinyaev
* Serbian from Marko Manojlovic
* Slovak from Emilia Valova
* Slovenian from Žiga Vajdic
* Spanish from Juanjo Navarro
* Swedish from Jonas Reinicke
* Turkish by Nadin Kokciyan
* Ukranian by Vasily Vishnyakov

== Screenshots ==

1. The filters and columns in action
2. Automatically registered metaboxes for data entry

== Changelog ==

= [Unreleased] unreleased =

= [3.10] 2015-06-09 =

* Bug - fixed escaping throughout
* Bug - fixed comments bubbles when they exist in the table
* Tweak - Brought version in line with other Modern Tribe plugins
* Tweak - Added some changelog formatting enhancements after seeing keepachangelog.com :)
* Feature - added translation support
* Feature - Added Brazilian Portuguese translation files, courtesy of Gustavo Bordoni
* Feature - Added Spanish translation files, courtesy of robotic translation
* Feature - Partial language support for Afrikaans, Bulgarian, Chinese (Taiwan), Czech, Danish, Dutch, English (UK), Estonian, Finnish, French (France), German, Greek, Hungarian, Icelandic, Indonesian, Italian, Latvian, Lithuanian, Portuguese (Portugal), Romanian, Russian, Serbian, Slovak, Slovenian, Swedish, Turkish, and Ukrainian imported from Events Calendar PRO

= 2.0 =

* New design plus tons of fixes

= 1.0.9 =

* Increase the version of the included demo plugin in order for it's update nag to go away

= 1.0.8 =

* Fix PHP notice regarding the $screen object

= 1.0.7 =

* Fix for loading JS/CSS on Windows-based servers
* Ensure the demo plugin checks that the main plugin is active to prevent white screens

= 1.0.6 =

* Add `class_exists()` conditionals to allow inclusion in 3rd-party code
* Fix a PHP notice

= 1.0.5 =

* Fix undefined indices
* Add an action for when active columns are determined

= 1.0.4 =

* CSS tweak for long select/input fields in filters
* More thorough gettext, including filterable textdomain
* Fix column bug introduced in 1.0.3

= 1.0.3 =

* Extra checks to ensure no empty columns

= 1.0.2 =

* Fix filter initialization bug

= 1.0.1 =

* Un-hide some UI elements that should show.
* Metabox HTML tweaks.

= 1.0 =

* Initial Release
