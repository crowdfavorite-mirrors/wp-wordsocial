=== Plugin Name ===
Contributors: Akisbis
Donate link: http://www.tommyleunen.com/
Tags: facebook, twitter, wordsocial, social, social media, social networking, yourls, social network, WSO, wso.li, bit.ly, qtranslate, press-this, linkedin, autopost, auto-post, cross-post, cross-posting
Requires at least: 2.8
Tested up to: 3.2.1
Stable tag: 0.5.3

WordSocial is a plugin that allows you to automatically publish your posts and pages to your Facebook, Twitter and/or your LinkedIn account.

== Description ==

This plugin allows you to cross-post your blog posts and pages to your favorite Social networks (Facebook, Twitter and LinkedIn for now).

Several options are available to personnalize your content before publishing, like an image, a comment, etc.. You can also re-post your blog posts on update.

WordSocial (WSO) also uses a shortening method for your urls to save some space in your tweets. You can choose between wso.li, bit.ly, your YOURLS configuration or your bit.ly configuration.

= Key features =
* <strong>Simple to use.</strong>
* <strong>For Facebook, Twitter and LinkedIn.</strong>
* It uses a shortening method to save space in your tweets.
* Compatible with <strong>bit.ly</strong> and <strong>YOURLS</strong>.
* <strong>WordSocial never stores your login or password.</strong>
* Compatible with qTranslate.
* Compatible with Press-This.
* Compatible with WordPress MU.
* Compatible with custom post types.


Feel free to use the plugin and let me know if there are errors or if you have some ideas to improve it. You can contact me on <a href="http://twitter.com/tommy">Twitter @Tommy</a>, on <a href="http://www.facebook.com/WordSocial">Facebook</a>, on <a href="http://wordpress.org/tags/wordsocial?forum_id=10">the forum</a>, or by <a href="http://scr.im/wordsocial">email</a>.

<strong>Requires PHP 5.x with CURL and JSON enabled.</strong>


== Installation ==

1. Extract the zip file and just drop the contents in the <code>wp-content/plugins/</code> directory of your WordPress installation (or install it directly from your dashboard) and then activate the Plugin from Plugins page.
1. Go to options page <strong>WordSocial</strong>, uses Facebook Connect and Sign in with Twitter and customize with the information you want.
1. This is it.. You can now publish your posts on these networks.

== Frequently Asked Questions ==

= Does it work with PHP4 ? =
No. WordSocial only support PHP5. I don't know if it's a good idea to support PHP4, it becomes obsolete.

== Screenshots ==

1. Global options
2. Specific options for Twitter
3. Panel available in your post editor

== Changelog ==

= 0.5.3 =
- fixed the issue of posting every posts
- fixed posting with network sites enabled
- fixed an hashtag issue when there were no tags
- added the default short link in the shortening method for Twitter

= 0.5.2 =
- fixed scheduled post issue
- fixed issue when the user change his password with Facebook

= 0.5 =
- Improve the code, for better maintainability
- Compatibility with custom post types.
- Remove the delay before cross-posting.
- Added a way to publish to multiple fanpages on Facebook.
- Added a way to use Categories and Tags as #Hashtags on Twitter.
- Update Facebook API to 3.1.1

= 0.4.4 =
- Improve the MU system
- Fixed a bug with scheduled posts
- fixed some others minor bugs

= 0.4 =
- Compatibility with qTranslate
- Compatibility with Press-This
- Added LinkedIn
- Added a log system
- Code refactoring
- Better look'n feel in the admin

= 0.3 =
- Update Facebook API to 3.0.0
- Fixed a bug with scheduled posts.
- Added yourls and bit.ly

= 0.2 =
- Fixed some minors bugs.

= 0.1 =
- Initial version

== Upgrade Notice ==

Before version 0.4, the app will reset all your WSO data, so you have to re-setup the plugin.

== Add WordSocial in Press This ==

Replace the original file `wp-admin/press-this.php` by the WordSocial file `wp-content/plugins/wordsocial/press-this.php`