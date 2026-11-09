# EventVOX WordPress Plugin Agent Instructions

## Project Context
- A WordPress plugin for organisations that use EventVOX: the EventVOX webshop, volunteer page and Pick-Up form in
  auto-height iframes, and small lists (coming events, Pick-Up rounds, volunteer call) as shortcode, block and
  classic widget. Installed on sites we do not control, updated from GitHub releases.
- The plugin is the folder `eventvox/`; the zip that sites install contains exactly that folder.
- UI text is Dutch (text domain `eventvox`); code comments are English, short.

## Layout
- `eventvox/eventvox.php`: header (`Version`, `Requires at least: 5.8`, `Requires PHP: 7.4`, `Update URI`), constants,
  includes. One class per part in `eventvox/includes/`:
  `class-settings.php` (Instellingen > EventVOX, option `eventvox_settings`), `class-api.php` (`EventVOX_API::get`,
  server-side `wp_remote_get` with a transient cache), `class-embeds.php` (iframes), `class-lists.php` (the lists,
  shared by shortcodes, widgets and blocks), `class-widgets.php`, `class-blocks.php`, `class-updater.php`.
- `eventvox/assets/`: plain JS and CSS, no build step. `embed.js` resizes the frames (messages `{ source:
  'eventvox-shop', type: 'height' | 'top' }`, only from the frame itself and the EventVOX origin); `blocks.js`
  registers the blocks with `wp.element` calls, the server renders them (`render_callback`).
- `bin/build-zip.sh <version>` makes `eventvox.zip` with that version written into the header, the constant and
  `readme.txt`. `.github/workflows/release.yml` runs it for a published release and attaches the zip;
  `check.yml` lints PHP 7.4 and 8.3 and builds a zip on every push.

## What It Depends On
- EventVOX backend (Voxxie/eventvox-backend), without login: `GET /public/shop/{tenant}` (name, `volunteer_signup`,
  `volunteer_intro`), `GET /public/shop/{tenant}/events`, `GET /public/pickups/{tenant}` (`rounds`).
- EventVOX frontend (Voxxie/eventvox-frontend): `shop/?t=`, `shop/volunteer?t=`, `shop/buy?t=&event=`,
  `pickup-embed/?tenant=` (framable), `pickup-signup?tenant=` (link target).
- A change there must keep these working: sites update the plugin late or never.

## Conventions
- Escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses` where HTML is meant); sanitize settings in
  `EventVOX_Settings::sanitize`. Only https addresses for EventVOX (http only for localhost).
- Visitors see nothing when there is nothing to show or EventVOX fails; users who can `manage_options` get a
  short Dutch note why.
- Never raise the version by hand: the release tag sets it. Do not break the updater: `Update URI` stays
  `https://github.com/Voxxie/eventvox-wordpress`, the asset stays `eventvox.zip` with the folder `eventvox/`, the
  filter is `update_plugins_github.com`.
- Keep PHP 7.4 syntax (no `match`, no constructor promotion, no named arguments, no `str_contains`).
- Removing data goes in `uninstall.php` (options, transients, widget options).

## Checking
- `find eventvox -name '*.php' -print0 | xargs -0 -n1 php -l` and `bin/build-zip.sh 0.0.0-check`.
- In a test WordPress (WP-CLI or a local install), symlink `eventvox/` into `wp-content/plugins/`, point
  Instellingen > EventVOX > Geavanceerd to a local EventVOX, and check the shortcodes, a block in the editor, a
  widget, and the updater (mock the GitHub answer with `pre_http_request`; WordPress asks wordpress.org first and
  stops when that request fails).

## Documentation Rule
- `README.md`: how it works and how to release. `eventvox/readme.txt`: for site managers, with the changelog.
- The user manual page is `wordpress.md` in Voxxie/eventvox-docs. Update this file when the layout or the
  dependencies change.
