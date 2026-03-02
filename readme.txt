=== Script Report ===
Contributors: sapayth
Donate link: https://sapayth.com/
Tags: debug, debug bar, development, performance, scripts
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Debug and audit JavaScript and CSS loading in WordPress. Analyze dependencies, detect issues, and improve performance on any page.

== Description ==

Script Report is a minimal and focused debugging tool for WordPress developers. It helps you audit and visualize JavaScript and CSS dependencies on any admin or frontend page.

When something loads out of order, loads twice, or slows down a page, Script Report helps you see exactly what is happening.

Use the Script Report link in the admin navbar on any page to open a complete breakdown of scripts and styles, their load order, dependencies, and origin. You can also append `?script_report=true` or `&script_report=true` to the URL.

=== Why Use Script Report? ===

Developers often ask:

Why is this script loading here  
Who enqueued this style  
Why is my dependency not working  
Is something loading twice  
What is affecting performance on this page  

Script Report gives you clear answers instantly.

=== What You Can Inspect ===

= JavaScript =

View a complete breakdown of:

* Registered scripts
* Enqueued scripts
* Total required scripts
* Load order
* File size
* Footer or header loading
* Inline scripts
* Enqueued by source
* Required by dependencies

= CSS =

Inspect styles with the same detailed structure:

* Registered styles
* Enqueued styles
* Total required styles
* Load order
* File size
* Dependency relationships
* Enqueued by source
* Required by dependencies

= Script Modules WordPress 6.5+ =

Audit registered and enqueued script modules along with their dependency chains.

=== Views ===

Switch between two views:

List view  
Clean, structured overview of all scripts and styles.

Tree view  
Visual representation of dependency chains. Circular or missing dependencies are clearly flagged.


=== Filtering ===

Quickly filter by handle or source to narrow down large lists. Filtering works client side for fast inspection.

== Installation ==

1. Upload the plugin files to `wp-content/plugins/script-report/`, or install through the WordPress Plugins screen.
2. Activate the plugin through the Plugins screen.
3. As an Administrator, click the Script Report link in the admin navbar on any page to view the report. You can also append `?script_report=true` or `&script_report=true` to the URL.

== Frequently Asked Questions ==

= Who can see the report? =

Users with the Administrator capability `manage_options` can open the report.

You may also enable access in development environments by defining:

`define( 'SCRIPT_REPORT_DEBUG', true );`

Do not enable this constant on production unless temporarily required.

= Is it safe for production? =

Yes. The report is only displayed to users who can `manage_options`, or when explicitly enabled using `SCRIPT_REPORT_DEBUG`.

No data is stored and nothing runs unless you open the report (via the admin bar link or the URL parameter).

= Does this affect site performance? =

No. Script Report only runs when you open it (admin bar or URL). Normal visitors and pages remain unaffected.

== Screenshots ==

1. Open the "Script Report" panel using admin navbar
2. List view showing scripts and styles with load order and metadata.
3. Tree view displaying dependency chains.
4. Script Report item in the top admin bar for quick access.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release of Script Report.