=== Respectify ===
Contributors: vintagedave, respectify
Tags: comments, moderation, community, user engagement, spam
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.4.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Healthy internet comments! Use Respectify to help your commenters post in a way that builds community.

== Description ==

Respectify helps you build healthy conversation on your site. Comments are analysed before being published, and when users show common ways of writing unhelpful comments -- ones that don't contribute well to a good conversation -- Respectify shows feedback and lets them edit.

You can guide healthy interaction, catch dogwhistles, keep comments on-topic, avoid objectionable material, filter spam, and more — ideal for sites serving marginalised communities, news sites covering sensitive topics, forums that have experienced brigading, political discussion where coded language is common, or any community where specific patterns tend to derail conversation. All of this is configurable in the Settings page.

Respectify's feedback is intended to encourage and teach good communication. It's not meant for censorship but for healthy engagement and understanding. We hope as your users write more comments, they will need Respectify's feedback less and less.

Respectify is a commercial service (it's meant to benefit the world, but it costs us money to run) so please [sign up for an API key](https://respectify.ai).

== Installation ==

Respectify is available in the Wordpress plugins directory, so:

1. Go to the Plugins menu in the Wordpress admin interface, and select Add New Plugin
2. Search for Respectify, and install
3. In Settings > Respectify, add your email and API key that you get from [respectify.ai](https://respectify.ai). (If you don't have one, [create an account](https://respectify.ai/register).)
4. That's it!

== Changelog ==

= Unreleased =
* Security: the settings page no longer puts your saved API key into the page. The API Key field is left empty and says a key is saved; leave it blank to keep the current key, or enter a new key to replace it. Test checks the saved key when the field is blank, and then always against the saved server and API version, never a server typed into the form, so the saved key is only ever sent to the server already configured. Browsers are also told not to autofill a password into the field

= 0.4.0 =
* Comment health is now one setting per item: threats, identity attacks, sexual content, harassment, insults, profanity, dismissive tone, logical fallacies, low effort posts, and comment quality. Each is Off, Permissive, Relaxed, Standard, Strict or Zero tolerance, and every item defaults to Standard. Permissive filters only the very worst: only extreme problems (eg a slur combined with a threat, or a credible threat against a named person) are sent back
* Threats, identity attacks, sexual content and harassment can be made stricter but never permissive, relaxed or turned off
* Replaces the minimum score slider (now the comment quality level), the per-check checkboxes (now Off for that item), the toxicity threshold, and the separate Moderation Tolerances panel. Existing settings are migrated automatically: a minimum score of 1-2 becomes Relaxed quality, 3 Standard, 4 Strict and 5 Zero tolerance; tolerance levels carry over; a switched-off check turns its items off (objectionable phrases: insults and profanity; negative tone: dismissive tone)
* Every comment is evaluated under your site's levels, sent with each request. Tolerances are no longer saved for your API key in the Respectify dashboard
* Respectify decides whether a comment falls short of your site's levels (including when it is severely toxic); the plugin follows that verdict and sends the comment back with feedback. Spam, topic relevance, undesired topics and dogwhistles are separate checks and work as before
* Each evaluated comment's per-item results (score, minimum and whether it was acceptable) are kept in the comment's `respectify_health` meta, without any comment text
* Comments that might be spam, but are not certainly spam, are no longer deleted: they are sent back to the commenter with a suggestion (eg to remove a link), together with any comment health feedback. A real person can fix the comment; a spammer won't bother. Comments that are spam are handled as before, following the How to Handle Spam setting
* Only the checks you have turned on are requested from Respectify, so you are never charged for a check that is off. With every check off, comments are published without calling Respectify at all
* Fixed: with Undesired Topics set to "Any mention at all", a comment that only briefly mentioned an undesired topic was sent back with a generic message instead of the undesired-topics explanation
* Fixed: the Undesired Topics mode ("Any mention at all" or a threshold) and the topic relevance settings are now validated when saved, and a saved threshold is kept when switching to "Any mention at all"; undesired topics are saved as plain text, one per line, up to 20 topics of up to 5000 characters each (what Respectify reads)
* Fixed a typo on the settings page
* Requires respectify-php 0.7.0

= 0.3.0 =
* New Moderation Tolerances settings: choose Relaxed, Standard, Strict or Zero tolerance for each kind of problem (threats, identity attacks, sexual content, harassment, insults, profanity, dismissive tone, logical fallacies and low effort posts). Every category defaults to Standard, so existing sites behave as before
* Threats, identity attacks, sexual content and harassment can be made stricter but never relaxed
* Categories left at Standard follow the tolerances saved for your API key in the Respectify dashboard
* When any category is relaxed, a comment's toxicity only triggers a revision request if Respectify also judges it beyond your site's tolerances, so relaxing a category (eg profanity) is not undone by the toxicity score. With no category relaxed, the toxicity threshold works exactly as before
* Requires respectify-php 0.6.0
* Now requires PHP 8.1 or later (PHP 8.0 is end-of-life), so bundled dependencies can take security fixes: guzzlehttp/psr7 updated to 2.13.1 (fixes CVE-2026-59882 and CVE-2026-55766)

= 0.2.7 =
* Fixed an API error that could block comments when comment scoring or dogwhistle checks were enabled without relevance checking; the article context is now fetched whenever any check needs it
* Comments are no longer held for moderation when a cached article context stops working after an API key change -- the context is regenerated automatically and the comment is re-evaluated
* Saving settings no longer discards cached article contexts unnecessarily (previously every save forced each post to re-initialise on its next comment)
* Updated the account panel for credit-based billing: it now shows your credit balance and no longer displays obsolete per-feature "in your plan" labels

= 0.2.6 =
* Fixed bug where assessment checkboxes could become unchecked during plugin upgrades
* Fixed logo truncation in comment feedback display
* Fixed duplicate comment submissions when users double-click the submit button

= 0.2.4 =
* Documents PHP 8.0+ requirement

= 0.2.2 =
* Added plugin icon
* Minor changes to readme

= 0.2.1 =
* Updated PHP SDK to v0.2.22 with improved subscription status handling
* Improved settings page UI with plan and feature indicators

= 0.2 =
* Initial version

== Human-readable code ==

The plugin's code can be found here: [github.com/Respectify/respectify-wordpress](https://github.com/Respectify/respectify-wordpress)

It uses the [Respectify PHP SDK](https://github.com/Respectify/respectify-php), which is an async API using ReactPHP.

Full documentation on the API (the PHP library, plus the REST API it wraps) is available here: [docs.respectify.ai](https://docs.respectify.ai/)
