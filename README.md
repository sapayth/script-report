# Script Report

A WordPress plugin that audits and visualizes JS/CSS script and style dependencies. See exactly what is enqueued on any admin or frontend page, who required it, and in what order it loads.

## Requirements

- WordPress 5.0+
- PHP 7.4+

## Installation

1. Clone the repo into `wp-content/plugins/` so the plugin lives at `wp-content/plugins/script-report/`:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/YOUR_USERNAME/script-report.git script-report
   ```
2. Activate the plugin in **Plugins** in the admin.

## Usage

1. **Enable the report**  
   Add `?script_reports=true` to the URL, or `&script_reports=true` if the URL already has query parameters.

   Examples:
   - `https://yoursite.com/wp-admin/` → `https://yoursite.com/wp-admin/?script_reports=true`
   - `https://yoursite.com/wp-admin/admin.php?page=wpuf-profile-forms` → `https://yoursite.com/wp-admin/admin.php?page=wpuf-profile-forms&script_reports=true`
   - `https://yoursite.com/some-page/` → `https://yoursite.com/some-page/?script_reports=true`

2. **Who can see it**  
   - Users with **Administrator** capability (`manage_options`) can always open the report.
   - Otherwise, the report is only available when the constant below is set (e.g. for local/dev).

3. **Optional: allow without admin (e.g. local/dev)**  
   In `wp-config.php`:

   ```php
   define( 'SCRIPT_REPORT_DEBUG', true );
   ```

   Then anyone with the URL can view the report. Do **not** enable this on production unless you need it temporarily.

## What you get

- **JavaScript** – Registered vs enqueued vs total needed, total size, and a list of every script that loads with:
  - Handle, file size, load order (#1, #2, …)
  - Badges: ENQUEUED, FOOTER, INLINE (with inline payload size)
  - “Enqueued by” (which top-level script pulled it in) and “Required by” (who depends on it)
- **CSS** – Same structure for styles.
- **Script modules (WP 6.5+)** – Registered and enqueued modules and their dependencies.
- **List vs Tree** – Toggle with `?script_reports=true&view=list` (default) or `&view=tree`. Tree shows dependency chains and flags circular or missing deps.
- **Filter** – Type in the filter box to narrow by handle or `src` (client-side).

## Security

- Report is shown only if the user can `manage_options`, **or** `SCRIPT_REPORT_DEBUG` is defined and true, **or** the URL includes a valid `_wpnonce` for the `script_report_view` action (e.g. the “Script Report” admin bar link).
- Nonce validation is used for nonced links; direct `?script_reports=true` still requires capability or `SCRIPT_REPORT_DEBUG`.

## License

GPL v2 or later.
