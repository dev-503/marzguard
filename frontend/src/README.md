#frontend/src

This folder is kept for source organisation. In MarzGard the files in `frontend/dist/` are **already the authored source** — Vue 3 is loaded from the local global runtime (`vue.global.prod.js`), so no bundler or Node build step is required.

| File | Purpose |
| --- | --- |
| `dist/marzgard-public.js` | Vue 3 authentication client (headless) |
| `dist/marzgard-public.css` | Sorkhab tokens + components + Arad font |
| `dist/marzgard-admin.js` | Vue 3 settings screen |
| `dist/marzgard-admin.css` | Admin layout (imports the public token file) |
| `dist/marzgard-block.js` | Gutenberg dynamic block |
| `dist/vendor/vue.global.prod.js` | Vue 3.5.13 (local, no CDN) |
