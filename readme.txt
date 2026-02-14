=== Script Report ===
Contributors: sapayth
Tags: scripts, styles, dependencies, debugging, developer tools
Stable tag: 1.0.0
Requires at least: 5.0
Requires PHP: 7.4
Tested up to: 6.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit and visualize JS/CSS script dependencies. Add ?script_report=true (or &script_report=true if the URL has other params) to see what is enqueued and in what order.

== Description ==

Script Report audits and visualizes JavaScript and CSS script and style dependencies on any admin or frontend page. See exactly what is enqueued, who required it, and in what order it loads.

= Features =

* **JavaScript** – Registered vs enqueued vs total needed, total size, and a list of every script that loads with handle, file size, load order, badges (ENQUEUED, FOOTER, INLINE), "Enqueued by" and "Required by"
* **CSS** – Same structure for styles
* **Script modules (WP 6.5+)** – Registered and enqueued modules and their dependencies
* **List vs Tree** – Toggle with `?script_report=true&view=list` (default) or `&view=tree`. Tree shows dependency chains and flags circular or missing deps
* **Filter** – Narrow by handle or src (client-side)

= Access =

* Users with Administrator capability (`manage_options`) can open the report
* Add `?script_report=true` to the URL, or `&script_report=true` if the URL already has query parameters (e.g. `wp-admin/admin.php?page=wpuf-profile-forms` becomes `...&script_report=true`)
* Optional: set `define( 'SCRIPT_REPORT_DEBUG', true );` in wp-config.php to allow access without admin (e.g. local/dev). Do not enable on production unless needed temporarily

== Installation ==

1. Upload the plugin files to `wp-content/plugins/script-report/`, or install through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen.
3. Add `?script_report=true` to the URL, or `&script_report=true` if the URL already has query parameters (as an Administrator) to view the report.

== Frequently Asked Questions ==

= Who can see the report? =

Users with the Administrator capability can always open it. Otherwise, the report is only available when `SCRIPT_REPORT_DEBUG` is set in wp-config.php (e.g. for local/dev), or when using a URL that includes a valid nonce (e.g. the "Script Report" admin bar link).

= Is it safe for production? =

Yes. The report is only shown to users who can `manage_options`, or when explicitly enabled via `SCRIPT_REPORT_DEBUG` or a nonced link. Do not set `SCRIPT_REPORT_DEBUG` on production unless you need it temporarily.

== Changelog ==

= 1.0.0 =
* Initial release.
