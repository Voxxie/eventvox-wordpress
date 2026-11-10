# EventVOX voor WordPress

WordPress-plugin voor organisaties die met EventVOX werken. De plugin zelf staat in `eventvox/`; de uitleg voor de
gebruikers in `eventvox/readme.txt` en op de docs-site.

## Wat zit erin

| Onderdeel | Blok / shortcode / widget | Hoe |
|-----------|---------------------------|-----|
| Webshop | `[eventvox_webshop]` | iframe van `<EventVOX>/shop/?t=<organisatie>`; met `?evx_event=<event>` in het adres van de pagina opent hij dat event (`shop/buy`) |
| Vrijwilligers | `[eventvox_vrijwilligers]` | iframe van `shop/volunteer?t=` |
| Pick-Up formulier | `[eventvox_pickup]` | iframe van `pickup-embed/?tenant=` (de losse, inlijstbare versie van het formulier) |
| Komende events | `[eventvox_events]`, widget, blok | `GET /public/shop/{tenant}/events` |
| Pick-Up rondes | `[eventvox_pickup_rondes]`, widget, blok | `GET /public/pickups/{tenant}` |
| Vrijwilliger worden | `[eventvox_vrijwilliger_worden]`, widget, blok | `GET /public/shop/{tenant}` (`volunteer_signup`, `volunteer_intro`) |

- De iframes groeien mee met hun inhoud: de EventVOX-pagina's sturen `{ source: 'eventvox-shop', type: 'height' }`
  (en `type: 'top'` om naar boven te scrollen); `assets/embed.js` luistert alleen naar het frame zelf en naar het
  adres van EventVOX. EventVOX staat dat inlijsten toe op https-sites (`frame-ancestors 'self' https:`).
- De lijsten worden op de server opgehaald (`wp_remote_get`, zonder login) en een paar minuten bewaard in een
  transient; een fout één minuut. Bezoekers zien niets als er niets te tonen is, beheerders een korte melding.
- Instellingen > EventVOX: organisatie-ID (tenant-UUID, in EventVOX bij Webshop > Delen), de pagina's met de
  iframes (daar linken de widgets naartoe), en onder Geavanceerd de adressen van EventVOX en de API.
- De adressen van EventVOX (`https://eventvox.nl`) en de API (`https://api.eventvox.nl`) staan als standaard in
  `EventVOX_Settings::DEFAULTS` en worden alleen opgeslagen als iemand een ander adres invult (een testomgeving): zo
  krijgt elke site een nieuw adres met een update. Verhuist EventVOX weer, zet dan het oude adres in `OLD_DEFAULTS` en
  verhoog `SETTINGS_VERSION`: `migrate()` maakt een opgeslagen oud adres eenmalig leeg (versie 0.1 sloeg de standaard
  `frontend.ingestel.nl` / `backend.ingestel.nl` wel op).
- Blokken zonder build-stap: de server tekent ze (`render_callback`); `assets/blocks.js` geeft alleen de voorvertoning
  en de instellingen in de editor.

## Updates via GitHub Releases

WordPress kijkt standaard alleen op wordpress.org. Deze plugin doet het via GitHub:

- In de kop van `eventvox/eventvox.php` staat `Update URI: https://github.com/Voxxie/eventvox-wordpress`. Daardoor
  vraagt WordPress (5.8+) het filter `update_plugins_github.com` om updates, en nooit wordpress.org.
- `includes/class-updater.php` beantwoordt dat met de nieuwste release
  (`https://api.github.com/repos/Voxxie/eventvox-wordpress/releases/latest`, 6 uur bewaard). WordPress vergelijkt de
  versie zelf en toont dan "Er is een nieuwe versie beschikbaar" bij Plugins, met "Details bekijken" (de release notes)
  en automatische updates als die aan staan. Dashboard > Updates > Opnieuw controleren vraagt meteen opnieuw.
- **De repository moet openbaar zijn.** GitHub geeft anders een 404 aan de sites.

### Een release uitbrengen

1. Zet je wijzigingen op `main` (via een pull request).
2. Vul in `eventvox/readme.txt` onder Changelog de nieuwe versie in (optioneel, maar handig).
3. GitHub > Releases > **Draft a new release** > **Choose a tag** > typ de nieuwe versie, bijvoorbeeld `v0.2.0`
   (hoger dan de vorige) > **Create new tag on publish**. Schrijf in de beschrijving wat er nieuw is: dat zien de
   beheerders bij "Details bekijken".
4. **Publish release**. De workflow `Release` (`.github/workflows/release.yml`) maakt dan `eventvox.zip` met dat
   versienummer erin en hangt hem aan de release. Na een minuut staat hij onder Assets.
5. De sites zien de update binnen 6 uur (of meteen met Opnieuw controleren).

Een release zonder `eventvox.zip` wordt niet aangeboden; een pre-release of concept ook niet. Het versienummer in de
code hoef je niet zelf aan te passen: de workflow zet de tag erin (`bin/build-zip.sh`).

### Eerste installatie op een site

Download `eventvox.zip` van de nieuwste release, en in WordPress: Plugins > Nieuwe plugin > Plugin uploaden. Daarna
komen updates vanzelf.

## Lokaal proberen

`bin/build-zip.sh 0.1.0` maakt `eventvox.zip`. Of zet een symlink van `eventvox/` in `wp-content/plugins/` van een
test-WordPress. Onder Instellingen > EventVOX > Geavanceerd kun je een lokale EventVOX (`http://localhost:...`) kiezen.
