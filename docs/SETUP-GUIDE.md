# Tech Akhil SEO Audit Tool — Setup Guide (v1.9.8)

This guide describes the **v1.9.8 review candidate**. The bundled PDF manual is `docs/free-seo-audit-tool-by-tech-akhil-documentation.pdf`.

## Quick start
1. Back up the site before updating a production installation. If a staging site is available, test there first.
2. In WordPress Admin, open **Plugins → Add New Plugin → Upload Plugin**.
3. Choose the Tech Akhil SEO Audit Tool ZIP, install it, and activate it. If updating an existing copy, use the normal WordPress plugin update/replace workflow and avoid keeping duplicate copies of the same plugin.
4. Create or edit a page and add `[seo_audit_checklist_tool]` in a Shortcode block or supported page-builder shortcode widget. Publish the page and test the form.
5. Open **SEO Audit** in WordPress Admin to access the admin audit form and available settings.
6. Use the **Audit Activity / Audited Websites** screen to review the minimal domain/date activity list and its date filters.

## Feature overview
- Bounded same-host crawl (up to 30 pages, subject to a time budget).
- Critical, Warning, and Notice issue categories with expandable details and a prioritized action plan.
- Optional Focus Keyword Analyzer for one focus keyword and up to ten related keywords.
- Selected sitemap/robots, JSON-LD, viewport/language, UX, content, link, and security-header signals.
- Browser print/save-to-PDF and temporary CSV export.
- Configurable report theme and accent colour.
- Optional public “Powered by Tech Akhil” attribution; disabled by default and displayed only when an administrator opts in.
- Optional administrator-authorized Google Search Console connection.
- Minimal audited-domain/date activity records, with records pruned after one year and a cap of 2,000 records.

## Shortcode
`[seo_audit_checklist_tool]`

## External tools
- PageSpeed Insights: https://pagespeed.web.dev/
- Google Search Console: https://search.google.com/search-console/
- Schema Markup Validator: https://validator.schema.org/

PageSpeed Insights and the Schema Markup Validator are external websites. This plugin does not run a server-side PageSpeed API request for the built-in audit.

## CSV export and attribution
CSV export tokens are single-use and expire after two hours. If a link expires or has already been used, run a new audit to generate another link. Text cells beginning with spreadsheet formula characters are prefixed as a mitigation against CSV formula injection. Public attribution is disabled by default and only appears when the site administrator enables it in settings. Plugin-owned admin screens have a separate administrative footer.

## Important limitations
The crawler does not execute JavaScript and cannot guarantee full-site coverage. Some link, structured-data, analytics, UX, and security checks are lightweight heuristics. The tool is not a full accessibility audit, malware scanner, penetration test, or ranking predictor, and it cannot guarantee search ranking outcomes. Google Search Console requires an administrator to authorize access to an eligible property.

## WordPress.org directory assets
The screenshots described in `readme.txt`, plugin icon, and directory banner are **WordPress.org repository assets**. They must be uploaded separately to the root `assets/` directory of the plugin’s WordPress.org SVN repository; they do not belong in `trunk/assets/` and do not need to be included in the installable plugin ZIP. The `docs/brand-assets/` files in this package are reference brand marks, not substitutes for genuine screenshots of the running plugin.

## Included brand assets
Tech Akhil wordmark and lettermark reference files are in `docs/brand-assets/`.
