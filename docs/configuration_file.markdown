# Configuration file

Caracal system has two configuration files. Default values are stored in system configuration file loaded at `units/config.php` while per-site configuration is stored inside of site's root directory under `config.php` (usually `site/config.php`).

Any value defined in system configuration can be overridden in site specific configuration.


## Paths

System configuration defines set of different paths for accessing different elements. Even though all of these can be changed it's not advisable to change system paths (starting with `$system_`).

Developers can do clever modifications of these paths to serve different images for based on domain for example. 


## Language configuration

Configuration file uses two global variables for language configuration. Array `$available_languages` contains a list of supported languages available on the site while `$default_language` specifies which language should be treated as default one and used when site is browsed without language code specified.

In addition to these system defined additional global variables `$language` containing currently used language code and `$language_rtl` boolean value denoting whether the current language is RTL (right to left). Value of these two variables should not be changed as it can lead to unpredictable behavior.

System has no limit on number of supported languages nor does it exclude certain languages. However, backend and other system constants are not translated to all languages. List of languages system itself is translated to can be obtained by looking into `system/languages_*.json` files.


## Default session options

Variable `$session_type` defines default session type for the system. Cookie usage is at this point mandatory as most of the mechanism relies on some sort of data retention through different page views. 

The following session types are supported:

- `NORMAL` - Regular session with default timeout of 15 minutes;
- `BROWSER` - Session which expires the moment browser is closed;
- `EXTENDED` - Expiration time is set for 30 days.

Default session type is configured as `BROWSER`. All of the supported session types have their expiration timer reset on each page view.

Please note that older Internet Explorer versions have issues with `NORMAL` session type due to bug which tested cookie expiration date in local timezone while storing expiration time in GMT.

`DNT` (do not track) header at this moment is not supported and is silently ignored. This feature is planed for upcoming versions.


## Database configuration

Database use is not mandatory, many of the modules rely on it to store their data. System is built against MySQL with some support for SQLite. PostgreSQL as of this moment is not supported.

To configure database use `$db_type` needs to be set to something else other than `DatabaseType::NONE`. The following constants are supported:

- `NONE` - No database will be used;
- `MYSQL` - MySQL database; 
- `PGSQL` - PostgreSQL database;
- `SQLITE` - SQLite database.

After database type is set, configuration is done through `$db_config` array containing `host`, `user`, `pass` and `name` keys.


## Caching

Caching is split into two separate mechanisms operating independently. JavaScript and CSS code is compiled, optimized and stored separately as file on the disk while page cache can be stored in different ways. This is done to ensue optimized code cache can still benefit from the browser-level cache. Additionally page caching mechanism allows for extra functionality which is not supported in code cache.


### Page caching

In normal operation of the site majority of the content and templates do not require constant rendering and database retrieval. Page cache significantly improves response time of the system when presenting pages to the user.

Page caching is configured by setting the value to the `$cache_method` global variable to one of the following values from the `Core\Cache\Type` class:

- `NONE` - No page caching is used;
- `FILE_SYSTEM` - Cache pages will be stored in `$cache_path` directory alongside compiled code;
- `MEMCACHED` - Memcached server will be used to store cached pages. This value requires additional configuration in `$memcached_config` array for `host` and `port` indicating where server is located.

Cache expiration time is set to 24h (86400 seconds) by default. This time can be change by modifying value stored in `$cache_expire_period`.


### Optimizing JavaScript and stylesheets

In order to optimize JavaScript code system will utilize Google's Closure compiler service and store optimized file in `$cache_path` directory. Level of optimizations is set to `SIMPLE` and can not be changed at the moment.

Stylesheet optimizations are considerably simpler in nature and consist of simplifying expressions, removing comments and unnecessary characters. Selectors and attributes are not modified in any way during this process. This is done on purpose to avoid any potential issues where unintended effects could arise from such optimizations and to provide maximum control to developers over how styles are defined and used.

To avoid unnecessary optimization unique id based on files used is generated and used as file name where final code is stored. Alongside file containing optimized code system will store integrity information which will be included during page rendering process. Integrity files are SHA384 hashes ensuring code has not been changed on the way to the user's browser.

Turning on code optimizations is done by setting `true` to `$optimize_code` global variable.

Additional page loading speedup can be achieved by setting `$include_styles` to `true`. This will result in whole generated CSS file to be included in the `<head>` tag of the rendered page. Speed gains from such practice are variable and mainly depend on page size as TCP/IP protocol is optimized towards bigger transfers.

Note: If `$cache_path` directory is not present system will try to create it, however it might fail to do so depending on permissions and hosting configuration. This will result in page being rendered without styles and scripts. To fix this issue code optimization needs to be turned off or directory permissions modified to allow Caracal to write to it.


## Security options

Providing greater security for users is always a good idea regardless if site operates on sensitive data or not. Caracal implements large number of security features to allow these protections to take effect.

Enforcing of SSL encryption of HTTP protocol (HTTPS) can be done by setting `$force_https` variable to `true`. As a result users landing on insecure `http://` page will be automatically and permanently (301) redirected to `https://` page. It's important to test HTTPS before turning this option as redirects are permanent and might cause issues for users.

Default stance for cross-domain referral URLs in Caracal is to provide only domain without any additional information or path. This is done with intention on protecting user's privacy while keeping some backwards compatibility with services which require such information. Referrer policy can be changed by setting `$referrer_policy` variable.

Embedding Caracal website is configured by default to be allowed only on same origin (domain). This configuration can be changed by setting new value to `$frame_options`. It's important to note that backend relies on ability to use IFrames. Changing this option might result in file uploads to stop working in backend.


## URL generator features

To provide URL and paths in easier to read fashion which are more friendly to search engines Caracal uses URL generator class. More information can be found [here](path_handling.markdown).

Caracal's URL parser supports both standard query strings and custom formats as defined in [path handling document](path_handling.markdown). When generating URLs developers have an option to generate URL which depends on URL rewrite plugin being enabled or not. Difference in URL generated is fairly minimal. For example:

With URL rewrite: `http://domain.com/contact-us`  
Without:          `http://domain.com?/contact-us`

By default system generates URL without rewrite support simply to support wider array of supported systems, however default site template turns this feature on. To manually enable URL rewrite `$url_rewrite` variable needs to be set to `true`.


## Gravatar configuration

Support for Gravatar profile images is built-in to the system and can be used by calling `gravatar_Get($email, $size)` function. Basic configuration doesn't need to be changed, however it's possible to do so by modifying `$gravatar_url`, `$gravatar_rating` and `$gravatar_default` variables.

The following placeholder strings can be supplied in URL:

- `email_hash` - MD5 hash of user's email in lowercase;
- `size` - Size of the image requested;
- `default` - Default image;
- `rating` - Allowed rating for images.
