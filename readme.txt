=== Tech Akhil SEO Audit Tool ===
Contributors: techakhil
Tags: seo, audit, technical-seo, keyword-analyzer, website-audit
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.9.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Run a bounded on-page SEO audit with prioritized fixes, optional focus keyword checks, and useful technical signals.

== Description ==

**Plugin developer:** Akhilesh Kumar Meena  
**Brand:** Tech Akhil  
**Developer profile:** https://techakhil.com/akhilesh-kumar-meena/  
**Plugin documentation:** https://techakhil.com/free-seo-audit-tool/
**Source Code (GitHub):** https://github.com/techakhilofficial/tech-akhil-seo-audit-tool

**Plugin homepage:** https://techakhil.com/free-seo-audit-tool/

Tech Akhil SEO Audit Tool checks a website's on-page and selected technical SEO signals and presents findings in Critical, Warning, and Notice groups, along with a prioritized action plan. It is created by Akhilesh Kumar Meena as a product of Tech Akhil.

The crawler follows a bounded set of same-host pages and checks common elements such as page titles and meta descriptions, headings, canonical and noindex signals, image alternative text, duplicate titles/descriptions within the crawl, selected internal and external link counts, broken internal-link targets, robots.txt and sitemap signals, mobile viewport and language declarations, structured-data presence, mixed content, selected response/security headers, heading-level signals, low-visible-text heuristics, and exact duplicate visible text among crawled pages.

The optional Focus Keyword Analyzer checks the first submitted page for a focus keyword and optional related keywords across selected on-page elements, including the title, meta description, H1, extracted page text, opening text, URL slug, image alt text, and link text. Its score is an internal checklist indicator—not a Google ranking score. It does not provide keyword search volume, keyword difficulty, or live ranking data.

The report can be printed or saved as a PDF through the browser and exported as a temporary CSV. PageSpeed Insights, Google Search Console, and the Schema Markup Validator are opened as external tools. PageSpeed analysis is not run through a server-side PageSpeed API call by this plugin.

== Features ==

* Built-in audit form inside the WordPress admin, plus the public shortcode form.
* Dashboard shortcode field with a one-click Copy button.
* Page-level H1/H2/H3 counts and internal/external link counts.
* Exact duplicate visible-text detection across crawled pages (heuristic; not semantic similarity).

* Bounded same-host crawl, with a limit of up to 30 pages and a time budget.
* Critical, Warning, and Notice issue groups with expandable issue details.
* Prioritized action plan with estimated effort labels.
* Optional Focus Keyword Analyzer with related keyword checks.
* Page-by-page technical signals and selected page data.
* Print / Save PDF and temporary CSV export (export links are single-use and expire after two hours).
* Configurable report theme and accent colour.
* Optional public “Powered by Tech Akhil” attribution, disabled by default and shown only after the site administrator opts in.
* Audit Activity view with unique audited domains and latest audit dates.
* Audit Activity filters for Today, This month, This year, a custom date, and a custom date range.
* Free external links to PageSpeed Insights, Google Search Console, and the Schema Markup Validator.
* Optional administrator-only Google Search Console connection.

== Installation ==

1. Back up your website and test the plugin on a staging site before production use.
2. In WordPress, go to Plugins > Add New Plugin > Upload Plugin.
3. Upload the plugin ZIP, install it, and activate it.
4. Create or edit a page and add the shortcode below in a Shortcode block or supported page builder:
   [seo_audit_checklist_tool]
5. Publish the page and open it to use the audit form.
6. To configure the report theme, accent colour, external tool links, or optional Search Console connection, open the SEO Audit menu in the WordPress admin area.

== Frequently Asked Questions ==

= Does the plugin require a third-party API for its built-in audit? =

No. Core HTML and response checks run with WordPress HTTP functions. Google Search Console and PageSpeed API-based insights are separate integrations and are not required for the built-in checks. Backlink and competitor datasets are not included.


= How do I display the audit form? =

Add [seo_audit_checklist_tool] to a WordPress page using a Shortcode block or a supported page builder, then publish the page.

= Does the Focus Keyword Analyzer show Google rankings or keyword volume? =

No. It checks selected on-page locations for the supplied focus keyword and related terms. Its score is an internal checklist indicator and does not represent Google rankings, search volume, or keyword difficulty.

= Does the plugin run a PageSpeed test on my server? =

No. The plugin links to Google's PageSpeed Insights website for separate analysis. It does not call the PageSpeed API from your server and does not require a PageSpeed API key for its core audit features.

= Does the crawler inspect every page and JavaScript-rendered content? =

No. The crawl is bounded, stays on the same host, and does not execute JavaScript. It may miss pages or content that are only available after JavaScript execution, and internal-link target checks are limited.

= Is Google Search Console required? =

No. The core audit works without connecting Google Search Console. The optional integration requires an administrator to connect and authorize an eligible Search Console property.

= Are audit reports stored for long-term comparison? =

The plugin does not provide a permanent report archive in this version. Audit Activity stores normalized audited domains and timestamps, de-duplicates a domain for the same day, prunes records older than one year, and keeps up to 2,000 activity records. CSV export data is held temporarily; its random single-use download link expires after two hours.

= Does a successful audit guarantee better rankings? =

No. The report is a practical checklist of selected signals. It is not a complete SEO, accessibility, security, analytics, or conversion audit and cannot guarantee search rankings.

== Privacy and Data Handling ==

When an audit is submitted, the WordPress server requests the supplied URL and selected same-host pages to inspect their HTML and response signals. The optional Focus Keyword Analyzer extracts page text during processing for its checks; this is not intended to be a permanent content archive.

Audit Activity stores the normalized audited domain and timestamp, rather than the full submitted URL or visitor identity. Activity is de-duplicated by domain and day, records older than one year are pruned, and the list is capped at 2,000 records. CSV export data is stored temporarily for up to two hours and the random download token is single-use. Formula-leading text cells are prefixed in CSV output to reduce spreadsheet formula-injection risk. The plugin does not intentionally send analytics telemetry.

If an administrator connects Google Search Console, the optional integration communicates with Google to retrieve authorized Search Console data. OAuth credentials/tokens are stored in WordPress options while the connection is active. PageSpeed Insights and the Schema Markup Validator are external websites opened through links; when you use them, your browser communicates with those providers under their own policies.

== Limitations ==

* The crawler does not execute JavaScript and cannot guarantee full-site coverage.
* Internal-link target checks are limited and may not identify every broken link.
* Structured-data checks are lightweight signals and are not a complete schema validation service.
* Security-header checks are informational signals, not a penetration test or vulnerability assessment.
* Analytics and form-presence checks are basic HTML signals, not a full analytics or conversion audit.
* Search Console data requires administrator authorization and access to the relevant property.
* The plugin does not provide live ranking data, keyword search volume, keyword difficulty, or guaranteed ranking outcomes.

== Screenshots ==

1. Audit form with website URL, optional focus keyword, and related keyword fields.
2. Crawl report summary with issue counts, prioritized action plan, and report export controls.
3. Expandable Critical, Warning, and Notice issue groups with recommended fixes.
4. Admin settings for report theme, accent colour, external tools, shortcode, and optional Search Console connection.
5. Technical, mobile, UX, and security signals alongside page-by-page data.

== Changelog ==

= 1.9.8 =
* Fixed admin footer screen detection to use WordPress's current-screen API instead of reading the request page parameter, removing the related nonce-verification warnings.


= 1.9.7 =
* Extended temporary CSV export links to two hours while retaining single-use tokens.
* Added CSV formula-injection mitigation for text cells.
* Added fresh audit nonce retrieval for cached/back-restored forms and moved public cooldown application after nonce validation.
* Added consistent Tech Akhil footer branding across plugin admin screens.
* Made public-facing attribution optional and disabled by default.
* Added missing hidden admin destinations used by dashboard quick-action cards.
* Updated documentation for export expiry and attribution behaviour.


= 1.9.6 =
* Shortened the WordPress admin menu label to SEO Audit.
* Simplified the visible admin menu and improved report container spacing/responsive safeguards.
* Added Tech Akhil attribution footer to the Free version (public attribution was later made optional and disabled by default in v1.9.7).


= 1.9.3 =
* Renamed the plugin display name to Tech Akhil SEO Audit Tool.
* Added an admin audit form, dashboard shortcode Copy button, H3 heading counts, internal/external link counts, heading hierarchy heuristic, and exact duplicate visible-text detection across crawled pages.
* Kept PageSpeed API, Search Console API, backlink data and competitor data as separate integrations; core audit checks do not require these services.


= 1.9.0 =
* Added a colorful admin dashboard and dedicated report/settings navigation.
* Added saved toggles for optional keyword analysis and report sections/actions.
* Added scoped responsive admin styling; existing shortcode and crawl logic retained.


= 1.8.2 =
* Added Tech Akhil website, documentation, and plugin review links to the plugin metadata row.

= 1.8.1 =
* Updated the product display name to Free SEO Audit Tool by Tech Akhil.
* Added branded step-by-step PDF documentation to the plugin package.

= 1.8.0 =
* Simplified Audit Activity to show unique audited domains and the last audit date.
* Added Today, This month, This year, Custom date, and Custom date range filters.
* Stores domain and timestamp, de-duplicates the same domain per day, prunes records older than one year, and caps the activity list at 2,000 records.
* Migrates legacy audit activity to minimal domain/date records and removes detailed legacy logs.

= 1.7.0 =
* Added sitemap/robots discovery and basic XML sitemap validation.
* Added mobile viewport, language, empty-link text, form-label heuristic, structured-data, mixed-content, and visible-content signals.
* Added lightweight security-header checks and analytics/form-presence signals.
* Added a prioritized action plan with estimated effort labels.
* Kept PageSpeed analysis as an external link rather than a server-side API call.

= 1.6.0 =
* Added the optional Focus Keyword Analyzer for title, meta description, H1, extracted content, opening text, URL slug, related keywords, image alt, and link text.
* Removed server-side PageSpeed API calls, API-key setting, and PageSpeed setup menu.
* Removed automatic full-report history retention.
* Simplified the admin menu and retained report theme/accent settings, Search Console setup, and minimal activity logging.

== Support and Documentation ==

* Developer: Akhilesh Kumar Meena — https://techakhil.com/akhilesh-kumar-meena/
* Brand: Tech Akhil — https://techakhil.com/

* Documentation: https://techakhil.com/free-seo-audit-tool/
* Live SEO checker: https://techakhil.com/free-seo-checker/
* Author profile: https://techakhil.com/akhilesh-kumar-meena/
* Plugin support forum: https://wordpress.org/support/plugin/seo-audit-checklist-tool/
* Plugin reviews: https://wordpress.org/support/plugin/seo-audit-checklist-tool/reviews/

