# Script Report – Features

## Trigger

- Add `?script_report=true` to any admin URL to replace the page with the dependency report.

## JavaScript

- **Stats:** Total registered, directly enqueued, total scripts needed (enqueued + dependencies), total file size (uncompressed).
- **List:** Every script that loads (enqueued + all dependencies), sorted by handle.
- **Per script:** Handle, file size, badges (ENQUEUED, FOOTER), “Enqueued by” (top-level enqueued parent), “Required by” (dependents in the set).
- **File size:** Resolves script `src` to local path (content URL, includes URL, relative) and shows size when the file exists.

## CSS

- **Stats:** Total registered, directly enqueued, total styles needed, total file size.
- **List:** Every style that loads, with same relationship info as scripts.
- **Per style:** Handle, file size, ENQUEUED badge, “Enqueued by”, “Required by”.

## Script modules (WP 6.5+)

- **Stats:** Total registered, enqueued.
- **List:** All registered modules with handle, file size when available, ENQUEUED badge, MODULE badge, “Depends on” list.
- Uses public APIs with reflection fallback for `WP_Script_Modules` internals.

## Detection (in tree logic)

- **Circular dependencies:** Detected when walking the tree (tree view is not currently rendered).
- **Missing dependencies:** Handles that are depended on but not registered are detected in tree walk.

## Report UI

- Page shows: current time.
- Sections: JavaScript dependencies, CSS dependencies, Script module dependencies.
- Inline styles for layout, badges, list and stats.

## Useful features that are missing

| Feature | Benefit |
|--------|---------|
| **Tree view** | Expose the existing tree logic (e.g. collapsible tree or Tree/List toggle) so dependency chains are easier to follow. |
| **Search / filter** | Filter by handle or `src` to quickly find a script or “everything from plugin X”. |
| **Load order** | Show the order scripts/styles are actually printed (after dependency resolution) to debug ordering issues. |
| **Inline / localized data** | For scripts: which have `extra['data']` or `wp_localize_script`, and size of inline payload. |
| **Export** | Export the report as JSON or CSV for CI, docs, or diffing between environments. |
| **Registration source** | Which plugin/theme registered each handle (requires hooking `wp_register_script` / `wp_register_style` and storing caller). |
| **Duplicate / alias detection** | Same `src` (or content) under different handles to spot redundant enqueues. |
