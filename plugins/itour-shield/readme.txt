=== iTour Shield ===
Contributors: mohamedgouda
Tags: security, cache, firewall, two-factor, performance
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Speed and security in one plugin: page cache, hidden login, masked WordPress paths, firewall, two-factor login, file scan and hardening.

== Description ==

iTour Shield works on any WordPress site and needs no other plugin. Everything is under **Shield** in the admin menu.

* **Overview**: site checks with a link to the setting that fixes each one.
* **Speed**: page cache for visitors (cleared automatically when content changes, then refilled), deferred scripts, no emoji or embed scripts, slower editor heartbeat, database clean-up.
* **Login & paths**: login page at an address you choose; wp-login.php and wp-admin show “not found” to guests. Public links can use other folder names instead of wp-content and wp-includes (files are never moved). Optional REST API prefix.
* **Firewall**: runs before other plugins. Blocks SQL injection, path traversal, code and script injection, scanner tools, listed IPs and ranges, and too many requests per minute.
* **Login protection**: lockouts after failed logins, one answer for wrong usernames and wrong passwords, two-factor login with any authenticator app plus ten backup codes, email when an administrator signs in from a new IP.
* **Hardening**: security headers, optional HSTS, file editor off, XML-RPC off, usernames hidden, WordPress version hidden, sensitive files blocked, no PHP in uploads, no folder listings.
* **File scan**: daily comparison of WordPress core and WordPress.org plugins with the official copies, PHP hidden in uploads, known malware patterns. Emails you when something new turns up.
* **Activity**: sign-ins, lockouts, blocked requests, scans and setting changes.

Deactivating the plugin removes every file change it made (.htaccess block, wp-config.php line, cache and firewall loaders).

== Installation ==

1. Upload the `itour-shield` folder to `wp-content/plugins/` and activate it.
2. Open **Shield → Overview** and work down the list.
3. Add your own IP under **Firewall → Never block**.
4. Turn on two-factor login in your profile.

== Frequently Asked Questions ==

= I changed the login address and lost it =

Add `define( 'ITOUR_SHIELD_DISABLE_PATHS', true );` to wp-config.php, sign in at wp-login.php, fix the setting, then remove the line. The new address is also emailed to the site admin when it changes.

= I lost my phone with the authenticator app =

Sign in with one of your backup codes, or ask another administrator to turn two-factor off in your user profile.

= The firewall blocks something it shouldn't =

Add the IP under **Firewall → Never block**, or add `define( 'ITOUR_SHIELD_NO_FIREWALL', true );` to wp-config.php while you investigate. Every block is listed under **Activity** with the reason.

= Does it work on Nginx? =

Yes. Shield can't write Nginx config, so **Hardening → Server** shows the rules to paste into the site's server block. Folder renaming works once those rules are in place and `define( 'ITOUR_SHIELD_NGINX', true );` is in wp-config.php.

= My site is behind Cloudflare or a load balancer =

Add `define( 'ITOUR_SHIELD_TRUSTED_PROXY', true );` to wp-config.php so Shield reads the visitor's IP from X-Forwarded-For. Only do this when a proxy really sits in front of the site; otherwise visitors could fake their IP.

= Can I use it with another caching plugin? =

Use only one page cache. If another plugin owns wp-content/advanced-cache.php, Shield leaves it alone and says so on the Speed tab.

== Changelog ==

= 0.1.2 =
* Arabic translation.
* Fix: admin screen scrolled sideways in right-to-left languages.

= 0.1.1 =
* Nginx rules on the Hardening tab; folder renaming on Nginx.
* Blocked IPs never get a cached page.
* Fix: rewriting server rules from WP-CLI dropped the folder-name lines.

= 0.1.0 =
* First release.
