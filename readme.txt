=== Tech Akhil SEO Audit Tool ===
Contributors: techakhil
Donate link: https://techakhil.com/donate/
Tags: seo, audit, technical-seo, keyword-analyzer, website-audit
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.9.14
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Run a bounded on-page SEO audit with prioritized fixes, optional focus keyword checks, and selected technical signals.

== Description ==

Tech Akhil SEO Audit Tool checks selected on-page and technical SEO signals and groups findings into Critical, Warning, and Notice categories with a prioritized action plan. It is a practical checklist, not a ranking predictor or a complete security/accessibility audit.

The bounded same-host crawler checks titles, descriptions, headings, canonical/noindex signals, image alt text, links, robots.txt, sitemaps, mobile viewport, language, structured-data presence, mixed content, selected response headers, and basic content heuristics. It crawls up to 30 pages subject to a time budget and does not execute JavaScript.

The optional Focus Keyword Analyzer checks the submitted page for a supplied focus keyword and related terms in selected on-page locations. Its score is an internal checklist indicator; it does not provide rankings, search volume, or keyword difficulty. Reports can be printed/saved as PDF in the browser or exported to a temporary, single-use CSV link that expires after two hours.

Plugin developer: Akhilesh Kumar Meena. Brand: Tech Akhil.
Documentation: https://techakhil.com/free-seo-audit-tool/
Plugin homepage: https://techakhil.com/free-seo-audit-tool/
Source code: https://github.com/techakhilofficial/tech-akhil-seo-audit-tool

== Features ==

* Admin audit form and public shortcode: [seo_audit_checklist_tool]
* Critical, Warning, and Notice findings with a prioritized action plan.
* Optional focus and related keyword checks.
* Page-by-page data and selected technical signals.
* Browser print/save-to-PDF and temporary CSV export.
* Configurable report theme and accent colour.
* Optional public attribution, disabled by default.
* Optional local Audit Activity with domain/date only, saved only after an explicit opt-in.
* Optional administrator-authorized Google Search Console integration.
* External links to PageSpeed Insights and the Schema Markup Validator.

== Installation ==

1. Back up your site and test on staging before production use.
2. In WordPress, go to Plugins > Add New Plugin > Upload Plugin and upload the ZIP.
3. Activate the plugin.
4. Add [seo_audit_checklist_tool] to a page using a Shortcode block or supported page builder, then publish it.
5. Open the SEO Audit menu to use the admin form and settings.

== Frequently Asked Questions ==

= Does the built-in audit require a third-party API? =
No. The core audit uses WordPress HTTP functions to request the supplied URL and selected same-host pages. Google Search Console is an optional integration. PageSpeed Insights and the Schema Markup Validator are opened as external websites; this plugin does not call their APIs from the server.

= Does the plugin show Google rankings, search volume, or keyword difficulty? =
No. The Focus Keyword Analyzer checks selected on-page locations for user-supplied terms. Its score is not a Google ranking score.

= Does the crawler inspect all pages and JavaScript-rendered content? =
No. Crawling is bounded, limited to the same host, and does not execute JavaScript. Some pages, links, and signals may be missed.

= Is Google Search Console required? =
No. The core audit works without it. The optional integration requires an administrator to configure OAuth credentials and authorize access to an eligible property.

= Are reports stored permanently? =
No. Audit Activity optionally stores a normalized domain and timestamp, de-duplicates the same domain per day, prunes records older than one year, and caps the list at 2,000 records. CSV export data is temporary and its random, single-use link expires after two hours.

= Does a successful audit guarantee better rankings? =
No. Results are selected technical/on-page signals and heuristics; no ranking or performance outcome is guaranteed.

== Privacy and Data Handling ==

The audit sends HTTP requests from the WordPress server to the submitted URL and selected same-host pages to inspect HTML and response headers. Before an audit runs, the person submitting the form must acknowledge that the site temporarily stores a site-keyed hash of the request IP in a WordPress transient for a 60-second abuse-prevention cooldown. The raw IP is not stored in Audit Activity, and the hash is not sent to Tech Akhil. The optional keyword analyzer processes page text in memory for its checks; this is not intended as a permanent content archive.

Audit Activity stores the normalized audited domain and timestamp in the local WordPress database only when the person submitting the audit checks the optional opt-in. Without that opt-in, no Activity record is added. The plugin does not intentionally send analytics telemetry to Tech Akhil. CSV data is stored temporarily for up to two hours; export tokens are random and single-use. CSV text cells beginning with spreadsheet formula characters are prefixed as a mitigation against formula injection.

If an administrator connects Google Search Console, OAuth credentials and returned access/refresh tokens are stored in this WordPress site's database while configured/connected. Disconnecting deletes the saved integration option. The site administrator is responsible for protecting the WordPress database and OAuth client secret.

== External services ==

**Google OAuth 2.0 and Google Search Console APIs (optional):** Used only after a site administrator configures an OAuth client ID/secret and explicitly starts the connection. The integration requests the read-only `https://www.googleapis.com/auth/webmasters.readonly` scope. Depending on the action, the plugin sends Google the OAuth client ID, redirect URI, temporary state, authorization code, client secret, refresh token, selected Search Console property, date range, or URL submitted for inspection. It requests accessible properties, up to ten search-query performance rows, or URL inspection data. Requests occur when the administrator connects, opens/uses the integration, submits an inspection, or a token refresh is needed. Google processes the data under its own terms and privacy policy.

* Google APIs Terms: https://developers.google.com/terms
* Google Privacy Policy: https://policies.google.com/privacy
* Google API Services User Data Policy: https://developers.google.com/terms/api-services-user-data-policy

**User-supplied websites:** When an audit is submitted, the WordPress server requests the supplied URL and selected pages on that same host. Those website hosts receive the normal HTTP requests needed to return their HTML and headers. The plugin does not intentionally transmit the audit content to Tech Akhil.

**PageSpeed Insights:** Opened only when a user clicks its link. The browser then communicates directly with Google; this plugin does not call the PageSpeed API from the WordPress server. Google Terms: https://policies.google.com/terms?hl=en-IN. Google Privacy Policy: https://policies.google.com/privacy.

**Schema Markup Validator:** Opened only when a user clicks its link. The browser communicates with https://validator.schema.org/; this plugin does not send audit data to it automatically or call a validator API from the WordPress server. Schema.org Terms: https://schema.org/docs/terms.html. This review did not identify a separate validator-specific privacy policy; Google's general privacy policy is available at https://policies.google.com/privacy.

== Limitations ==

* The crawler does not execute JavaScript and cannot guarantee full-site coverage.
* Broken-link, schema, content, analytics/form, and security-header checks are limited heuristics.
* The tool is not a penetration test, malware scanner, complete accessibility audit, or ranking predictor.
* Search Console data requires administrator authorization and access to the relevant property.

== Screenshots ==

1. Audit form with URL and optional keyword fields.
2. Crawl report summary with issue counts and prioritized action plan.
3. Expandable Critical, Warning, and Notice groups.
4. Admin settings and optional Search Console connection.
5. Technical signals and page-by-page report.

== Upgrade Notice ==

= 1.9.14 =
Includes reviewer-focused hardening for Google Search Console property validation, optional local activity disclosure, strict boolean settings sanitization, CSV export handling, and external-service disclosures. Test on staging before production use.

= 1.9.13 =
Includes maintenance and security-related code improvements, plus aligned setup and user documentation. Back up your site and test updates on staging before production use.

== Changelog ==

= 1.9.14 =
* Validate the selected Search Console property before saving refresh requests and verify inspected URLs against the selected property.
* Keep optional Audit Activity opt-in and document local-only data handling and public cooldown acknowledgement.
* Use strict allow-list sanitizers for boolean settings.
* Document Google OAuth/Search Console data flow with Google API Terms, Privacy Policy, and User Data Policy links.
* Retain enqueued JavaScript assets and WordPress inline-style API rather than inline script blocks.
* Harden CSV export input handling and spreadsheet formula mitigation.

= 1.9.13 =
* Use a narrowly scoped safe redirect for Google OAuth authorization and allow only accounts.google.com for that redirect.
* Separate raw public audit URL extraction from HTTP(S)-only URL sanitization.
* Keep plugin metadata, setup documentation, and the user guide aligned with version 1.9.14.

= 1.9.12 =
* Sanitize and validate the Google OAuth authorization code once before use.
* Pass an explicit CSV escape parameter to avoid PHP 8.4 fputcsv deprecation warnings.
* Update setup documentation and move older release notes to changelog.txt.

= 1.9.11 =
* Move report print behavior to the enqueued front-end script.
* Move inline styles to enqueued stylesheets or the WordPress inline-style API.
* Harden scalar validation for request inputs.

See changelog.txt in the plugin package for older release notes.

== Support and Documentation ==

* Documentation: https://techakhil.com/free-seo-audit-tool/
* Developer profile: https://techakhil.com/akhilesh-kumar-meena/
* Support forum: https://wordpress.org/support/plugin/tech-akhil-seo-audit-tool/
* Reviews: https://wordpress.org/support/plugin/tech-akhil-seo-audit-tool/reviews/
