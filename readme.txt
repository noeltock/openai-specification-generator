=== OpenAPI Specification Generator ===
Contributors: Noel_Tock
Tags: openapi, rest-api, specification, wordpress, chatgpt, bard
Requires at least: 5.2
Tested up to: 5.8
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

OpenAPI Specification Generator  allows users to generate an OpenAPI Specification for the REST API endpoints of their WordPress website.

== Description ==

OpenAPI Specification Generator is a WordPress plugin that helps users generate an OpenAPI Specification (OAS) for the REST API endpoints of their WordPress website. Users can manage the endpoint details, add custom summaries and descriptions, and decide which endpoints to include in the generated OAS.

Features:

* Discover and list all REST API endpoints on the WordPress website, including custom post types.
* Customize endpoint summaries and descriptions.
* Select which endpoints to include in the generated OAS.
* Generate the OAS JSON file in a publicly accessible folder with a consistent URL.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-openapi-generator` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the 'Settings'->'OpenAPI Specification Generator ' screen to configure the plugin.

== Screenshots == 

1. This is the UI whereby you can train OpenAI on your WordPress API endpoints and content.

== Frequently Asked Questions ==

= Can I customize the endpoint summaries and descriptions? =

Yes, OpenAPI Specification Generator  allows you to input your custom summaries and descriptions for each endpoint.

= Can I choose which endpoints are included in the generated OAS? =

Yes, OpenAPI Specification Generator  provides an option to include or exclude each endpoint from the generated OAS.

= Can I generate the OAS for custom post types? =

Yes, OpenAPI Specification Generator  discovers and lists all available REST API endpoints, including custom post types.

= What happens when the plugin is deactivated or uninstalled? =

When the plugin is deactivated, no specific action is performed. When the plugin is uninstalled, it removes the plugin settings and deletes the generated OpenAPI Specification JSON file.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
