=== Instant Quote Widget ===
Contributors: (your agency)
Tags: booking, quote, lawn care, shortcode, elementor
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Embeds the Instant Quote & Booking widget (React) into any WordPress page via shortcode, Elementor HTML widget, or the block editor's Shortcode block.

== Description ==

This plugin loads a single small JavaScript bundle that renders the Instant
Quote widget into a Shadow DOM, so it never inherits or clashes with your
theme's CSS. No external APIs, no paid services, no payment processor —
this is a demo/prototype build.

== Installation ==

1. Zip the `instant-quote-widget` folder (this folder) and upload it via
   Plugins → Add New → Upload Plugin, or copy it directly into
   `wp-content/plugins/`.
2. Activate "Instant Quote Widget" in the Plugins screen.
3. Add `[instant_quote_widget]` to any page/post, or paste
   `<div data-instant-quote-widget></div>` into an Elementor HTML widget.

== Shortcode options ==

`[instant_quote_widget max_width="640px"]` — controls the widget's max
container width (defaults to 640px).

== Changelog ==

= 1.0.0 =
Initial prototype release.
