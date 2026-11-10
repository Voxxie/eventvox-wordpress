=== EventVOX ===
Contributors: eventvox
Tags: events, tickets, webshop, vrijwilligers, pick-up
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zet de EventVOX-webshop, de aanmeldpagina voor vrijwilligers en het Pick-Up formulier op je eigen WordPress-site.

== Description ==

Voor organisaties die met EventVOX werken.

* **Webshop**, **vrijwilligers** en **Pick-Up formulier**: de pagina's van EventVOX zelf in een iframe die meegroeit met
  de inhoud. Inloggen, betalen en aanmelden gebeuren bij EventVOX, zoals altijd.
* **Komende events**, **Pick-Up rondes** en **Vrijwilliger worden**: korte lijsten en oproepen voor een pagina of de
  zijbalk, met een knop naar de webshop, het formulier of de aanmeldpagina.

Alles kan als blok (zoek op "EventVOX"), als shortcode en als widget.

Shortcodes:

* `[eventvox_webshop]`, `[eventvox_vrijwilligers]`, `[eventvox_pickup]` (met `hoogte="900"` voor de starthoogte)
* `[eventvox_events aantal="5" knop="Naar de webshop"]`
* `[eventvox_pickup_rondes aantal="5" knop="Aanmelden"]`
* `[eventvox_vrijwilliger_worden tekst="..." knop="Word vrijwilliger"]`

== Installation ==

1. Plugins > Nieuwe plugin > Plugin uploaden: kies eventvox.zip (van de nieuwste release op GitHub).
2. Activeer de plugin.
3. Instellingen > EventVOX: vul het organisatie-ID in. Dat staat in EventVOX bij Webshop > Delen.
4. Maak pagina's met de webshop, de vrijwilligerspagina en het Pick-Up formulier en kies ze bij de instellingen; de
   widgets linken daarnaartoe.

== Frequently Asked Questions ==

= Hoe krijg ik nieuwe versies? =

Zoals bij andere plugins: bij Plugins verschijnt "Er is een nieuwe versie beschikbaar". Automatische updates kun je
daar ook aanzetten. De versies komen van GitHub (Voxxie/eventvox-wordpress), niet van wordpress.org.

= Ik zie niets op de pagina =

Bezoekers zien niets als er niets te tonen is. Ingelogd als beheerder zie je een korte melding waarom (bijvoorbeeld
dat het organisatie-ID ontbreekt of dat Pick-Up uit staat).

== Changelog ==

= Volgende versie =
* EventVOX staat nu op eventvox.nl. De adressen worden bij deze update vanzelf overgezet; een zelf ingevuld adres
  (een testomgeving) blijft staan.

= 0.1.0 =
* Eerste versie: webshop, vrijwilligers en Pick-Up in een iframe; komende events, Pick-Up rondes en vrijwilliger worden
  als blok, shortcode en widget; updates via GitHub.
