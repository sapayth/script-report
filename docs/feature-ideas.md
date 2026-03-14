# Script Report – Feature Ideas

Researched and ranked by impact and implementation feasibility.

---

## High Impact, Well-Scoped

### 1. Render-Blocking Detection
Flag scripts/styles loaded in `<head>` without `async` or `defer`. Directly answers "what's slowing my page?" — a core use case already described in the readme. Surface as a warning badge in the list view.

### 2. Duplicate Detection with Warnings
The plugin already tracks when the same file URL appears under multiple handles, but doesn't surface it prominently. Show a warning badge when the same `src` is registered under multiple handles, or when a known library (e.g. jQuery) is loaded from a non-standard source.

### 3. Export Report (JSON / CSV)
One-click download of the current page's dependency data. Useful for sharing with team members, filing bug reports, or diffing between environments.

### 4. Inline Script Content Preview
The plugin already tracks inline/localized script data. Add an expandable preview of the actual content — currently it shows the inline script exists but not what is in it.

---

## Medium Impact

### 5. Version Conflict Detection
Detect when the same handle is registered with different versions, or when a known library appears from a non-standard source with an unexpected version string.

### 6. External vs. Local Asset Distinction
Tag assets loaded from external CDNs (e.g. `fonts.googleapis.com`, `cdnjs`, `unpkg`) vs. local files. Relevant for privacy, GDPR audits, and performance.

### 7. WP_DEBUG Console Warnings
When `WP_DEBUG` is enabled, automatically log circular or missing dependencies to the browser console — without requiring the panel to be opened.

### 8. Settings Page
A simple admin settings page for:
- Enabling access for specific roles beyond `manage_options`
- Toggling the admin bar link on/off
- Enabling `SCRIPT_REPORT_DEBUG` via UI instead of `wp-config.php`

---

## Smaller Wins

### 9. Keyboard Shortcut
Open/close the panel with a configurable hotkey (e.g. `Ctrl+Shift+S`).

### 10. Dark Mode
Panel respects `prefers-color-scheme` or includes a manual toggle button.

### 11. Copy Handle Button
Click to copy a handle name to clipboard. Practical when you need to write `wp_dequeue_script()` or `wp_deregister_script()`.

### 12. Filter by Load Position
Filter list to show only header-loaded vs. footer-loaded scripts.

---

## Recommendation

**Render-blocking detection** and **Copy Handle button** offer the most utility for the least complexity. Both stay true to the plugin's mission: see exactly what is happening, then fix it.
