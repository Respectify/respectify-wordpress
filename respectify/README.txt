=== Respectify ===
Contributors: vintagedave, respectify
Tags: comments, moderation, community, user engagement, spam
Tested up to: 6.7
Stable tag: 0.2.2
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
