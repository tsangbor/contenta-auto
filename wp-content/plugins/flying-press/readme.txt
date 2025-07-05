=== FlyingPress ===
Requires at least: 4.7
Tested up to: 6.8.1
Requires PHP: 7.4
Stable tag: 5.0.2

== Description ==
Lightning-Fast WordPress on Autopilot

== Changelog ==

= 5.0.2 - 05 June, 2025 =

- Fix: Empty URLs being inserted to the queue in certain cases 
- Fix: Separate cache for mobile devices was not generated post v5.0.1

= 5.0.1 - 03 June, 2025 =

- Improvement: Use native WP HTTP request library to handle multiple requests seamlessly even if cURL multi is unavailable
- Improvement: Only consider allowed cookies while adding a url to the preload queue
- Fix: Ensure custom cache ignore cookies are correctly added to the advanced-cache.php
- Fix: Error while triggering non-existent WP CLI commands 
- Fix: A warning of undefined `HTTP_COOKIE` in non HTTP contexts 

= 5.0.0 - 28 May, 2025 =

- New: Automatic and more accurate page optimizations by our new intelligent, fast and highly efficient **CloudOptimizer**
- New: Robust DB based and CPU friendly cache preloading algorithm
- New: Powerful in-house background processing mechanism to efficiently manage cache preloading without overloading the CPU
- New: Separate cache and optimizations for mobile devices
- New: Automatic cache preloading for mobile devices
- New: Count pages currently in queue besides total cached pages count
- New: Fully revamped UI for better user experience
- New: Dedicated **Optimizations** tab for CSS, Javascript, Images, Fonts, Iframes and Videos
- New: Automatically generate more accurate used css
- New: Automatically detect and lazyload offscreen background images
- New: Automatically detect ideal offscreen elements and lazily render them(CSS based lazy render)
- New: Automatically preload critical images( including background images, video posters ) with high priority
- New: Automatically preload critical fonts for faster page loads
- New: Automatically exclude critical videos  from lazy loading
- New: Automatically exclude critical iframes from lazy loading
- New: Lazy load videos
- New: Automatically detect third-party scripts to delay  their execution
- New: Option to load delayed scripts when browser is free(load on idle)
- New: Dedicated cloud service to find accurate youtube thumbnails in order to generate lightweight previews
- New: Filter hook to generate separate cache by cookie names
- New: Filter hook to add cache ignore query parameters
- Improvement: Renamed optimizations with more meaningful titles and descriptions in the UI
- Improvement: Compatibility with CURCY WooCommerce multi-currency plugin
- Improvement: Robust gzip compression strategy for cached files resulting in three times lesser CPU usage
- Improvement: Delay JS is incredibly stable now without needing any exclusions
- Improvement: Preload links now use native **Speculation Rules** API, disabling WordPreess's inbuilt Speculation Rules
- Improvement: Logged-in user cache is now generated without any page optimizations
- Improvement: Option to purge pages from the admin bar and moved to the settings page
- Improvement: Automatically resume cache preloading process if got stuck due to some reason
- Improvement: Heartbeat frequency will be set to 60s if enabled in bloat settings
- Improvement: Post revisions are limited to 3 if enabled in bloat settings
- Improvement: Efficient cache page counting algorithm
- Improvement: A more reliable way to add/remove constants in the `wp-config.php` file
- Fix: An issue where WP CRON was not being disabled properly
- Fix: Self-hosting Google fonts was not working for fonts defined in inline styles
- Fix: Prevent preloading posts of private post types when comment count is updated
- Fix: Youtube iframes inside `p` tags were resulting in broken styles when preview is self-hosted due to invalid tags hierarchy

= 4.16.3 - 07 February, 2025 = 

- Fix: Force disable browser cache when serving cached pages.
- Fix: Incorrect apache fallback configuration for cached pages.

= 4.16.2 - 20 January, 2025 = 

- Improvement: htaccess rules added before WP rules to avoid overriding custom rules.  
- Improvement: Better object caching compatibility during cache preloading.  
- Improvement: WooCommerce assets retained on the account page.  
- Improvement: Improved archive page auto-purging.  
- Fix: Pages now cached with KBoard plugin active.  
- Fix: Translated URLs cached with TranslatePress.  
- Fix: Fixed deferral of stylesheets without media attributes.  

= 4.16.1 - 12 December, 2024 = 

- Fix: A browser console warning regarding preloaded images not being promptly utilized for third-party CDNs post v4.16

= 4.16.0 - 11 December, 2024 = 

- New: Filter hooks to exclude JS files from delay and defer (for third-party plugin developers)
- Improvement: A remarkable 80% reduction in CPU usage during cache preloading for sites with large number of pages
- Improvement: Enhanced compatibility with the SureCart plugin
- Improvement: Enable defer inline by default when defer JavaScript is enabled    
- Improvement: Cache was not purged properly when ACF option pages are updated in certain cases
- Improvement: CORS compliant image preloading to ensure efficient resource reuse for better performance
- Improvement: Do not optimize images inside `template` tags for broader compatibility
- Fix: A PHP notice triggered by early textdomain loading in WP v6.7.1


= 4.15.9 - 21 November, 2024 = 

- Improvement: Display alerts after importing configurations and activating license
- Improvement: Simplify smart link preloading for enhanced performance
- Fix: The admin bar was not displaying for logged-in users in certain cases when FlyingCDN is active

= 4.15.8 - 13 November, 2024 =

- Improvement: Prevent pages from downloading as gzip on OpenLiteSpeed servers
- Improvement: Refactor WPML integration to improve performance
- Improvement: Preload pages with appropriate user agent for greater compatibility
- Fix: Remove unwanted encoded string from `cache_bust` query parameter that caused invalid URLs


= 4.15.7 - 07 November, 2024 = 

- Improvement: Use mime module as a fallback to prevent pages from downloading as gzip
- Fix: A warning about an undefined array key `HTTP_HOST` while purging FlyingCDN cache in the CLI context
- Fix: Element attribute values were incorrectly captured by the HTML parser in certain cases 

= 4.15.6 - 01 November, 2024 = 

- Improvement: Further enhancements to reduce the negative impact of third-party scripts on overall performance

= 4.15.5 - 31 October, 2024 = 

- Improvement: Removed htaccess rule to conditionally check for legacy cached files
- Improvement: Ensure responsive attributes are added only if `srcset` is correctly generated for an image
- Improvement: Leverage WordPress native way to add query parameters to a URL for enhanced compatibility
- Fix: Third-party scripts negatively impacted the pagespeed scores in certain scenarios

= 4.15.4 - 22 October, 2024 = 

- Fix: Incorrect rewriting of internal SVG reference URLs caused broken styles in CSS minify
- Fix: An error while generating srcset for responsive images in certain cases 

= 4.15.3 - 09 October, 2024 = 

- Improvement: Add 'srsltid' to default ignore query parameters

= 4.15.2 - 05 October, 2024 = 

- Improvement: Disable smart link preloading for logged-in users to reduce server load
- Fix: An error while generating image srcset in certain scenarios

= 4.15.1 - 02 October, 2024 = 

- Improvement: Hosting independent detection of WP.Cloud platform for broader compatibility
- Improvement: Remove unnecessary resource hints after self-hosting third-party CSS and JS
- Fix: External JS requests were not downloaded properly post v4.15.0 release

= 4.15.0 - 01 October, 2024 = 

- New: Smart preload links, preloading links in a predictive manner for blazing fast page navigations
- New: Compatibility for hosting providers powered by WP.Cloud
- New: Host third-party CSS and JS locally for specified CDN domains
- Improvement: Option to enable or disable lazy render
- Improvement: Prevent direct access to FlyingPress cached files
- Improvement: Fallback rules to serve legacy cached files are removed
- Improvement: Add `gbraid` to default ignore query parameters 
- Improvement: Add missing image width and height and responsive images features are enabled by default
- Improvement: Upgrade some dependencies for enhanced performance
- Fix: Logged in users were getting logged-out version of a page with FlyingCDN active

= 4.14.4 - 25 June, 2024 = 

- Improvement: New logo and color scheme to match our updated branding

= 4.14.3 - 19 June, 2024 = 

- Improvement: Disable conflicting optimizations in Perfmatters only if they are enabled in FlyingPress
- Improvement: Concise, clearer optimization descriptions in the UI
- Fix: An error of undefined constant `GLOB_BRACE` in non GNU systems

= 4.14.2 - 12 June, 2024 = 

- Fix: Pages got downloaded as gzipped file in OpenLiteSpeed server post v4.14 release 
- Fix: FlyingCDN was failing to cache pages due to missing headers in OpenLiteSpeed  

= 4.14.1 - 11 June, 2024 = 

- Fix: Double GZIP Compression of cached files resulted in gibberish output for some websites post v4.14 
- Fix: YouTube placeholder images were not lazily loaded post v4.13.5    

= 4.14.0 - 10 June, 2024 = 

- New: GZIP pre-compression for cached files, resulting in approximately 80% reduction in cache file size and improved performance
- Improvement: Compatibility for Nginx Helper plugin
- Improvement: Do not cache Pretty Links  
- Improvement: Superadmins can now access the FlyingPress dashboard in a multisite network
- Improvement: Do not minify JavaScript files that are empty or already minified 
- Improvement: Purge FlyingCDN cache while deactivating FlyingPress 
- Improvement: Purge FlyingCDN cache while purging a single page
- Improvement: Decode non-ASCII characters from an URL while generating and purging cache
- Fix: A warning of undefined property `stdClass:$plugin` in the SureCart plugin updater 
- Fix: An error in the PolyLang integration caused by non-existent taxonomy terms  
- Fix: An empty line before list of URLs in preload.txt sometimes caused the preload to hang

= 4.13.5 - 16 May, 2024 = 

- Improvement: Add CDN headers right after caching the page for faster caching in FlyingCDN
- Improvement: Check for sufficient permissions before purging and preloading cache from the CLI context 
- Improvement: Efficiently get elements by attribute in the HTML parser 
- Improvement: Keep WP native lazy loading enabled for better compatibility
- Improvement: Do not optimize images inside noscript tags
- Improvement: Upgrade some dependencies for enhanced performance
- Fix: Error while using lazy render controls inside Divi builder plugin
- Fix: Generating separate cache for mobile getting disabled after upgrading
- Fix: Error while unpacking arrays with string keys in PHP <= 8.0 

= 4.13.4 - 20 April, 2024 =

- Improvement: Cache compatibility for WeGlot URL translation
- Improvement: Leverage WordPress HTTP API to download third party resources for better compatibility

= 4.13.3 - 13 April, 2024 =

- Improvement: Better used CSS detection

= 4.13.2 - 12 April, 2024 =

- Improvement: Enhanced stability for the complete removal of unused CSS, ensuring a more reliable performance
- Improvement: Logic behind the Image Optimizer has been simplified for better efficiency
- Fix: Gravatar images inside srcset were not self-hosted correctly 
- Fix: Website assets were still using old CDN URLs whereas new FlyingCDN was active

= 4.13.1 - 5 April, 2024 = 

- Fix: Custom CDN URL not working after last update

= 4.13.0 - 5 April, 2024 = 

- New: Unveiling the new FlyingCDN, powered by Cloudflare Enterprise. Visit [FlyingCDN.com](https://flyingcdn.com/) 
- Improvement: Streamlined purging process for post taxonomies  
- Improvement: Auto purge and preload WeGlot translated URLs
- Improvement: Added 'gad_source' to default ignore query list
- Improvement: Responsive images uses native auto sizes
- Fix: Missing trailing slash in the page URL resulted in invalid cache file names in certain cases    

= 4.12.0 - 28 March, 2024 = 

- New: FlyingPress is now compatible with WeGlot translation 
- Fix: A notice regarding the redeclaration of controls with same name in Elementor
- Fix: Undefined array key HTTP_HOST in CLI context
- Fix: Post 4.11 release website styles got broken in certain cases 

= 4.11.0 - 18 March, 2024 = 

- New: WP CLI commands for preload cache, purge pages , purge everything and activate license , try `wp flying-press`
- Improvement: Efficient logic for checking if WP_CACHE constant is set
- Improvement: Adjusted FlyingPress controls after custom css toggle in Elementor
- Improvement: Higher loading priority for preloaded fonts
- Improvement: Upgraded assets minification library
- Fix: Adding display=swap to encoded Google font URLs resulted in invalid font
- Fix: A warning while fetching WooCommerce product categories 

= 4.10.3 - 01 March, 2024 = 

- Fix: Error rendering some blocks inside the Gutenberg block editor after v4.10
- Fix: Cache file name change via filter hook resulted in invalid cache file generation 

= 4.10.2 - 29 February, 2024 = 

- Improvement: Lazy Render toggle for Elementor legacy section elements
- Fix: Post v4.10 release , preload not starting after purging everything

= 4.10.1 - 28 February, 2024 = 

- New: Filter hook to disable FlyingPress footprint
- Improvement: Enhanced SVG compatibility for the new Lazy Render
- Fix: A warning regarding cache include queries

= 4.10 - 27 February,2024 =

- Removed: CSS Lazy Render based on content visibility
- New: JS Lazy Render, read more in [docs](https://docs.flyingpress.com/en/article/lazy-render-elements-up666e/)
- New: CDN cache headers to support wide range of CDN/proxy cache providers
- Improvement: Enhanced and more memory efficient capturing of elements in the HTML parser  
- Fix: A warning of invalid argument supplied to foreach

= 4.9.5 - 5 February, 2024 =
- Fix: Incorrect license notice even after reactivation
- Fix: Prevent auto purge on post type nav_menu_item
- Fix: Deprecation notice on PHP 8.2

= 4.9.4 - 31 January, 2024 =
- Fix: Licensing in multisite installations

= 4.9.3 - 30 January, 2024 =
- Improvement: Migrate to SureCart for license management

= 4.9.2 - 16 January, 2024 =
- Improvement: HTML elements finding is 2000% more faster
- Improvement: Google fonts are now downloaded separately for enhanced efficiency and compatibility

= 4.9.1 - 12 January, 2024 =
- Fix: Enhanced support for CSS @imports in CSS minify
- Fix: Prevent rewriting to self-hosted Google Fonts when not downloaded correctly
- Fix: Self-host YouTube thumbnails in JPG format instead of WebP
- Fix: Leftover ? issue while removing cache_bust query parameter

= 4.9.0 - 29 December, 2023 =
- New: Implemented a more efficient cache purging strategy
- Improvement: Enhanced compatibility with Perfmatters plugin
- Improvement: Compatibility with EWWW Image Optimizer plugin
- Improvement: Compatibility with ShortPixel Adaptive Images
- Improvement: Optimized cache page counting by considering folders only
- Improvement: Default include query parameters to always cache
- Fix: An error while updating product categories during stock updates 

= 4.8.0 - 5 December, 2023 =
- Removed: 'Generate separate cache for mobile' from UI, available via filter (refer docs.flyingpress.com)
- New: Cache include parameters - Query parameters for which separate cache should be generated
- New: Advanced settings pages for fine-tuning
- New: Exclude specific user roles when cache for logged in users is enabled via filter
- New: Purge integrated Cloudflare cache in Cloudways
- Improvement: Compatibility with the Cloudways Breeze plugin
- Improvement: Replace all error control operators with appropriate checking
- Improvement: Purge WooCommerce product categories and tags while updating stock
- Improvement: Add WP Meteor to the incompatible plugin list
- Improvement: Prevent caching of FlyingPress Rest API endpoints
- Fix: A deprecation notice on PHP >= 8.2

= 4.7.0 - 16 November, 2023 =
- New: Enable/disable cache preloading after saving settings and other relevant events
- Improvement: Purge Cloudflare APO cache when a list of pages are purged
- Improvement: Add instant.page plugin to list of incompatible plugins
- Improvement: Use deregister instead of dequeue for Woo cart fragments
- Improvement: Bump minimum PHP version to 7.4
- Improvement: Minor UI improvements
- Fix: Rest APIs calls not working when slash is forced in the URL

= 4.6.8 - 1 November, 2023 =
- Improvement: Contact support directly from the plugin dashboard
- Improvement: Added FlyingPress footprint with cached timestamp
- Improvement: Purge parent categories on updating WooCommerce product
- Fix: cache_bust not removed in some cases

= 4.6.7 - 26 September, 2023 =
- Fix: Entire pages cache getting cleared on updating any post after the latest upgrade
- Fix: Error when WCML is active but multi-currency is not enabled

= 4.6.6 - 22 September, 2023 =
- Improvement: Purge cache before preloading in Scheduled preload
- Improvement: Better cache purging while updating templates in different page builders
- Improvement: Remove WooCommerce block styles when block editor CSS is disabled in Bloat settings
- Fix: Compatibility with YITH multi-currency switcher plugin
- Fix: Compatibility with WCML multi-currency switcher plugin
- Fix: Distorted srcset attribute after hosting gravatar images locally

= 4.6.5 - 14 August, 2023 =
- Fix: Incorrect image size when using responsive images with FlyingCDN

= 4.6.4 - 7 August, 2023 =
- Fix: Scroll triggering clicks on mobile when JS files are delayed
- Fix: Warning when images have non-numerical width or height
- Fix: Empty needle warning in PHP < 8
- Improvement: Auto purge on saving in ACF options page

= 4.6.3 - 18 July, 2023 =
- Fix: YouTube placeholder breaking in some cases
- Improvement: Better warnings in settings page

= 4.6.2 - 18 July, 2023 =
- Improvement: Delay all JS is now compatible with more scripts

= 4.6.1 - 6 July, 2023 =
- Fix: Compatibility with Aelia Currency Switcher plugin
- Fix: BuddyBoss theme compatibility to prevent 401 errors while saving settings

= 4.6.0 - 3 July, 2023 =
- New: Host Gravatar images locally
- Fix: Remove cache_bust string when encoded

= 4.5.7 - 22 June, 2023 =
- Improvement: Compatibility with SG Optimizer (SiteGround)
- Improvement: Prevent caching of password protected pages

= 4.5.6 - 13 June, 2023 =
- Fix: Incorrect preloading of images with srcset
- Improvement: Updated library for CSS and JS minify

= 4.5.5 - 10 June, 2023 =
- Fix: Inline background images not loading with lazy loading enabled

= 4.5.4 - 9 June, 2023 =
- Fix: Cached pages not serving for mobile in some cases

= 4.5.3 - 7 June, 2023 =
- Improvement: Preload post thumbnail image and exclude from lazy loading
- Improvement: Use WebP images for YouTube placeholder
- Improvement: Calculate height if only width is present, and vice versa
- Improvement: Prevent double purging in Cloduflare APO
- Improvement: Better detection of robots.txt and sitemap to exclude from caching
- Fix: Skip adding width and height if it's already present
- Fix: Encoding attribute values in HTML parsing

= 4.5.2 - 31 May, 2023 =
- Improvement: Add width and height of Gravatar images
- Improvement: Support for TranslatePress
- Improvement: Prevent altering images inside script tags
- Improvement: Prevent data URI images from being preloaded
- Improvement: Added version number in settings
- Improvement: Theme detection in usage tracking
- Improvement: CDN rewrite when URL is not full path
- Fix: Check full URL against keywords in exclude pages from caching
- Fix: Purge and preload cache when a scheduled post is published
- Fix: Warning on updating WooCommerce product via Rest API

= 4.5.1 - 25 May, 2023 =
- Improvement: Keep execution order of JavaScript when delayed
- Improvement: General support for all translation plugins
- Improvement: Integration for WPML and Polylang
- Improvement: Static files are now stored in root cache directory
- Improvement: Better detection of URLs to preload
- Fix: Auto purge and preload when permalink of a post is changed
- Fix: Warnings from Cloudways Varnish integration
- Fix: Preloading getting stuck in some cases

= 4.5.0 - 19 May, 2023 =
- New: Self-generate preload list, eliminating the need for a sitemap when preloading
- New: Significant reduction in CPU usage by 300% during cache preloading
- New: Delay preload by 0.5s between each page to avoid server overload
- New: Added a filter to adjust the 0.5s delay in preloading cache
- New: Added a filter to modify the JavaScript delay timeout
- Improvement: Update license status from reactivation
- Fix: Resolved PHP warning encountered during cache purging and preloading
- Fix: Hashing query strings to generate cache file names to avoid long file names
- Fix: License activation in multisite subfolder installations

= 4.4.0 - 12 May, 2023 =
- New: Export or import configuration
- New: Manually activate or change license key
- New: Usage tracking to improve the plugin
- Improvement: Automatically purge SpinupWP cache
- Improvement: Only cache pages with 200 status code
- Fix: Incorrect HTML attribute detection in some cases

= 4.3.1 - 9 April, 2023 =
- Improvement: Preload post cache when a comment is manually approved
- Fix: Remove Google Fonts option removing tags in the same line
- Fix: Incorrect preloading of responsive images

= 4.3.0 - 6 April, 2023 =
- New: Bloat remover!
- Improvement: License activation for multisites
- Improvement: Process @rules without nesting in remove unused css
- Fix: Automatic purging of WP Engine throwing errors
- Fix: Cache file name when there is array in query strings
- Fix: Filter for disable cache preloading
- Fix: Duplicate preload tags when multiple title tags are found
- Fix: Warnings on caching and preloading

= 4.2.3 - 29 March, 2023 =
- Improvement: Automatically purge RunCloud, WP Engine and GridPane cache
- Improvement: Check parent directory for wp-config.php if not found
- Fix: Get sitemap URL from SEOPress

= 4.2.2 - 16 March, 2023 =
- Fix: Serve mobile cache using PHP when web server is not available

= 4.2.1 - 16 March, 2023 =
- Fix: Unable to add products after v4.2.0

= 4.2.0 - 16 March, 2023 =
- New: Generate separate cache for mobile
- Improvement: Auto purging on saving ACF fields

= 4.1.0 - 07 March, 2023 =
- New: Automatically purge Kinsta and Rocket.net cache
- New: Filter to disable cache preloading
- New: Filter to modify optimized HTML
- Improvement: Add crossorigin to preload fonts
- Improvement: Remove ?cache_bust query string
- Fix: Prevent unwanted purge and preload on saving navigation menus

= 4.0.7 - 24 February, 2023 =
- Improvement: Auto purge WooCommerce product and related pages on batch update
- Improvement: Better HTML page detection

= 4.0.6 - 23 February, 2023 =
- Fix: Automatic updates not available in some sites
- Improvement: Generate separate cache for different roles when logged in
- Improvement: Give warning when WP_CACHE is defined in wp-config.php
- Improvement: Better HTML page detection

= 4.0.5 - 21 February, 2023 =
- Improvement: Remove existing WP_CACHE constant from wp-config.php
- Improvement: Add WP Optimize to incompatible plugins list

= 4.0.4 - 17 February, 2023 =
- Improvement: Use HTTP/2 for cache preloading
- Fix: Defer not applied to multline scripts
- Fix: Remove whitespace in scripts after delaying
- Fix: Bypass caching for Bricks Builder editing pages 

= 4.0.3 - 16 February, 2023 =
- Fix: Verify wp-config.php file exists and write permission
- Fix: Prevent Optimize Google Fonts removing other link tags
- Fix: Skip processing non-standard inline scripts
- Fix: Add display-swap to font-face with single rule

= 4.0.2 - 15 February, 2023 =
- Fix: A typo in image preload tag
- Fix: Parsing of style attributes with quotes
- Fix: Exclude above fold images was applying even lazy loading is disabled

= 4.0.1 - 14 February, 2023 =
- Fix: Get correct Rest API URL in subfolder installation

= 4.0.0 - 13 February, 2023 =
- Read our blog post before updating: flyingpress.com/blog/introducing-v4

= 3.10.0 - 20 December, 2022 =
- New: Cloudflare APO compatibility - Automatically purge CF APO cache when purging FlyingPress

= 3.9.0 - 29 April, 2022 =
- New: Fetchpriority attribute for images, fonts and css files
- New: Decoding (syn/async) attribute for images
- Removed: Feature to disable jQuery migrate
- Removed: Option to use JavaScript lazy load (will use browser native by default)

= 3.8.0 - 21 December, 2021 =
- New: Disable jQuery migrate
- Removed: FlyingCDN integration (migrate to FlyingCDN Wallet - https://flyingpress.com/blog/flyingcdn-wallet/)
- Improvement: Purge necessary pages when updating WooCommerce product via API
- Fix: Broken 'Open a ticket' link
- Fix: Responsive images not available after mgirating to FlyingCDN Wallet

= 3.7.0 - 22 November, 2021 =
- New: Keyless activation - No need to enter license key!

= 3.6.0 - 10 September, 2021 =
- New: Responsive images using FlyingCDN
- Fix: Preload image from srcset if found

= 3.5.0 - 12 June, 2021 =
- New: Use placeholder images for YouTube videos
- New: Self-host YouTube placeholder images
- Removed: Settings for lazy loading videos (will be enabled by default)
- Fix: Ignore empty keywords in list
- Fix: Incorrect ABSPATH is some hosting providers

= 3.4.0 - 07 June, 2021 =
- New: Enable or disable scripts to load on user interaction
- New: Only "safe" optimizations are enabled by default
- Fix: x-flying-press-source header will display LiteSpeed or Apache
- Fix: Use get_id() instead of ID for WooCommerce compatibility
- Improvement: Remove async attribute when defer is enabled
- Improvement: Minor UI improvements

= 3.3.0 - 29 May, 2021 =
- New: Defer inline JavaScript
- Removed: Exclude jQuery from defer
- Removed: Fix render-blocking jQuery scripts
- Improvement: Better detection of CSS and JS files
- Fix: Purge and preload WooCommerce products when updated via Rest API
- Tweak: Added SG Optimizer to non-compatible plugins

= 3.2.0 - 19 May, 2021 =
- New: Enable beta versions
- Improvement: Register user interaction listeners only when needed

= 3.1.0 - 31 Mar, 2021 =
- New: Lazy Render! Skip rendering of elements until needed

= 3.0.0 - 01 Mar, 2021 =
- New: New HTML parsing engine!
- Improvement: 2x cache preload time
- Improvement: 5x-10x lower server resource usage
- Improvement: Notifications after saving settings now floats above all
- Tweak: Enable adding width and height attributes by default
- Tweak: Added common list of 3rd party scripts to load on user interaction
- Fix: Use WP_CONTENT_URL and WP_CONTENT_DIR constants instead of hard-coded values
- Fix: Prevent base64 images from preloading
- Fix: Preload only first feature image
- Fix: Lazy loading iFrames added using Thrive Architect
- Fix: Overwrite existing font-display to enable swap when fallback font enabled

= 2.13.0 - 08 Feb, 2021 =
- Tweak: Remove self-hosting internal CSS
- Fix: Add gzip when not enabled in server
- Fix: Prevent parsing of HTML twice

= 2.12.0 - 05 Feb, 2021 =
- New: Auto purge Varnish cache
- New: Added hooks after purging cache (for 3rd party integrations)
- Tweak: Default settings - switched lazy loading to Browser Native
- Tweak: Default settings - disabled exclude jQuery from defer
- Tweak: Default settings - enabled fix render-blocking jQuery Scripts
- Tweak: Generate Critical & Used CSS only when CSS Minify is enabled

= 2.11.0 - 04 Feb, 2021 =
- New: Support for Multisites

= 2.10.0 - 31 Jan, 2021 =
- New: Auto preload images excluded from lazy loading
- Tweak: Disable WordPress inbuilt lazy loading
- Fix: Incorrect icon in Cache settings

= 2.9.0 - 21 Jan, 2021 =
- New: Auto change hash of minified files when CDN is enabled/disabled
- New: Minify JS files having .min.js extension

= 2.8.0 - 07 Jan, 2021 =
- New: Force include CSS selectors in Critical & Used CSS
- New: Added UTF-8 encoding for cached pages
- Fix: Empty imagesrcset and imagesizes on preload tag
- Fix: Exclude images not respecting background images
- Tweak: UI improvements

= 2.7.0 - 04 Dec, 2020 =
- New: Database Cleaner
- Tweak: Minor UI improvements
- Fix: Detect dynamic classes from delayed JS files
- Fix: Continue serving page on parsing failure

= 2.6.0 - 30 Oct, 2020 =
- New: Add missing width & height attributes to images
- New: Separate options to purge CSS/JS/Fonts and Critical/Used CSS
- Tweak: Changed default image lazy loading method to JavaScript
- Tweak: Allow 'space' character in keyword input fields
- Tweak: Updated cookie list to bypass cache
- Tweak: Confirmation before purging Critical/Used CSS
- Tweak: Increased Critical/Used CSS generation API timeout
- Tweak: UI improvements

= 2.5.0 - 23 Oct, 2020 =
- New: Ignore custom query strings
- Fix: Only preload images from origin site
- Fix: Prevent preloading all features images in archives

= 2.4.0 - 22 Oct, 2020 =
- New: Preload critical images
- New: Cache Lifespan - Automatically purge and preload cache after a lifespan
- Tweak: Disable optimize for logged in users by default

= 2.3.0 - 15 Oct, 2020 =
- New: Purge current page
- New: View site without any optimization (?no_optimize)
- New: Support for Jilt cookies
- Fix: Undefined index warnings

= 2.2.0 - 03 Oct, 2020 =
- Preload fonts - Prioritize loading fonts that required immediately for the render
- Additional auto purge - purge pages when a post is published/updated
- Preload cache automatically after post is published/updated
- UI improvements

= 2.1.0 - 26 Sept, 2020 =
- Generate separate critical CSS and 'used' CSS
- Removed minifying and separating inline styles
- Automatically purge blog archive page
- New Facebook group link for FlyingPress community
- Removed roadmap

= 2.0.0 - 10 Sept, 2020 =
- Generate cache locally
- Speed up cache generation by around 10x
- Purge cached pages (HTML files) alone
- Support server side caching layers by disabling inbuilt cache
- Automatically exclude WooCommerce cart, checkout, account page from caching
- Exclude pages from caching
- Caching without having a sitemap
- Detect native sitemap
- Optimize for logged in users
- Lazy load videos
- Other bugs fixes and improvements

= 1.0.0 - 31 Jul, 2020 =
- Stable release!