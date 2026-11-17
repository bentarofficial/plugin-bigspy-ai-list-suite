=== BIGSPY AI List Suite ===
Contributors: bentar
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPL-2.0-or-later

AI listing generator, post writer and site insights for directory websites.

== Description ==

One plugin with three modules you can turn on or off: Listing Generator, Post Writer and Site Insights. It replaces BIGSPY AI LIST Generator, BIGSPY AI Post Writer and BigSpy Site Insights: on activation it turns those off and copies their settings.

With BIGSPY AI Theme WP it uses the theme's AI Designer key, so the key is entered once.

Updates come from GitHub Releases in the WordPress dashboard.

== Changelog ==

= 1.4.0 =
* Create listing: new Pricing page URL field. The page is read and used as the main source for prices, plan limits, trials and refunds.
* Create listing: warns when the website already has a listing (one product, one listing) and links to it.
* Create listing: Category field. New setting "New categories": by default the AI may only use categories that already hold listings, and paths that do not match are left out and reported instead of creating near-duplicates.
* Create listing: fields renumbered, the last AI model is remembered, saved internal links are offered as suggestions instead of pre-filling every row.
* Listing facts box on the product screen: starting price, free plan, free trial, card required, refund policy, platforms, with a "last checked" date, a "Re-check with AI" button that reads the pricing page, and a "Facts checked" column on Products that turns red after the number of days set in the settings (default 90). The AI fills the facts when a listing is created; they count as checked only after the editor confirms them.
* Affiliate click tracking: clicks from any page to a listed product's website are counted per page. Optional SubID parameter per listing adds the page slug to the outgoing link so the affiliate network reports which page made the sale. Report "Affiliate clicks, last 30 days" by page and by article type on the settings page.
* Write post: pick a listed product in External Anchors to fill its website and affiliate URLs; Editor notes; Focus keyword (used exactly when given); the brief box shows what to include for the chosen article type; warns before writing a second article of the same type about the same product; the last AI model is remembered; internal anchors are no longer pre-filled.

= 1.3.0 =
* Write post: choose the article type (Guide, Best List, Tutorial, Pricing, Trial & Demo, Offers & Deals, Alternatives by need, Case Study & Testing, Migration). Each type shows its recommended length, and the length follows the type, so there is no word count to enter.
* Write post: choose the value the article must give the reader (discover a solution, plain-language explanation, selection criteria, risks and who it is not for, what to do after signing up).
* Write post: no keyword density target and no "X vs Y" framing.
* Create listing: a listing is always the full profile of one product. New Editor notes field for first-hand findings; without notes the AI never claims hands-on testing.
* Create listing: length follows the evidence (700-1,500 words) instead of a fixed 2,000 words, and filler is not allowed. Specific limits, renewal pricing and "who it is not for" are required.
* Listings and posts: published listings, their categories and posts are offered to the AI as internal links, and links to this site outside that list are removed. A listing may link up to 3 sources from the web research results.
* Note: a custom System prompt saved in the settings replaces the built-in listing prompt, including the new length rules.

= 1.2.1 =
* Settings: a link to serper.dev under the Serper key field, and in the Listing Generator notice when the key is missing, so you know where to get the Web Research key.

= 1.2.0 =
* Popular listings can be ordered by website traffic from Site Insights, or by a blend of traffic and clicks. The traffic score is synced to listing meta (_bsi_traffic_score) and used for sorting only.

= 1.1.0 =
* Embed badge module (from the BIGSPY AI List theme): "Featured on" badge generator on listing pages with Light, Neutral and Dark styles and a copy button, the [bigspy_embed_badge] shortcode, and heading and label settings copied from the old theme. Hidden while the old theme still shows its own badge.

= 1.0.0 =
* First release. Replaces BIGSPY AI LIST Generator 2.9.9, BIGSPY AI Post Writer 1.2.4 and BigSpy Site Insights 0.3.0, keeping their data.
* One settings page with module switches; old settings are copied and the old plugins turned off; updates from GitHub in the dashboard.
* Listing Generator module (from BIGSPY AI LIST Generator 2.9.9): settings on the suite page, new "Create listing" page, shared AI key, prompt uses the site name.
* Post Writer module (from BIGSPY AI Post Writer 1.2.4): new "Write post" page, image SEO fields in the media library, prompt uses the site name.
* Site Insights module (from BigSpy Site Insights 0.3.0): settings on the suite page, keys never printed, Site Insights block (locked while the theme List mode is off), data kept on uninstall unless chosen.
* Modules work together: new listing drafts get their website looked up, listings named in a post description become internal links, AI Designer models are offered with the theme connection, overview at the top of the settings page.
* AI Designer tools (with BIGSPY AI Theme WP): suite overview, listing insights, create listing drafts.
