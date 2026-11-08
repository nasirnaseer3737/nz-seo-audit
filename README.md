# NZ SEO Audit

A WordPress plugin demo by **Nasir Naseer** — on-page SEO audit for posts and pages, scored 0–100 with a plain-language checklist. Built as a portfolio piece showing production-style WordPress plugin code: a pure, testable audit engine, post metabox, admin dashboard, list-table column, dashboard widget, settings and a REST API.

> **Line count:** 800+ lines of PHP/JS/CSS. That clears the 300+ line plugin-code gate used by employers such as Crowd Favorite, which requires a GitHub link showing a WordPress plugin or significant feature.

## What it checks (10 checks, 100 points)

| Check | Points | Rule |
| --- | --- | --- |
| Title length | 12 | 30–60 characters |
| Meta description | 12 | 120–160 characters (prefers Yoast / Rank Math value when present) |
| Keyword in title | 10 | Target keyword appears in the title |
| Keyword in content | 10 | Keyword appears in the first paragraph |
| H1 count | 10 | Exactly one H1 |
| Headings structure | 8 | H2/H3 headings break up the content |
| Word count | 10 | 300+ words (configurable) |
| Images alt text | 10 | Every image has alt text |
| Links | 8 | At least one internal and one external link |
| Readability | 10 | Average sentence under 20 words (configurable) |

Each check is **pass** (full points), **warn** (half points) or **fail** (0). Grades: **80+ Good, 50–79 Needs work, below 50 Poor**.

## Where it shows up

- **Post editor — SEO Audit box:** score, grade, full checklist with an explanation per check, and the target keyword field. The score updates when you save the post.
- **SEO Audit dashboard (WordPress → SEO Audit):** all published posts/pages with scores and grades, plus counts per grade.
- **Posts/Pages list:** an SEO Score column.
- **WordPress dashboard widget:** the 5 lowest-scoring posts, so you always know what to fix next.
- **Settings (SEO Audit → Settings):** minimum word count and max average sentence length.

## REST API

- `GET /wp-json/nzsa/v1/audit/<post-id>` — audit a post (editors and above), returns score, grade and all checks.
- `GET /wp-json/nzsa/v1/lowest` — the 10 lowest-scoring published posts.

## Install

1. Copy the `nz-seo-audit` folder into `wp-content/plugins/`
2. WordPress admin → Plugins → activate **NZ SEO Audit**
3. Open any post, set a target keyword in the SEO Audit box, save — the score appears.
4. See the whole site in WordPress → **SEO Audit**.

Requires WordPress 6.0+ and PHP 8.0+.

## Files

| File | What it does |
| --- | --- |
| `nz-seo-audit.php` | Plugin header, activation defaults, boot, admin assets |
| `includes/class-nzsa-audit.php` | The audit engine — pure logic, no WordPress calls in the checks |
| `includes/class-nzsa-admin.php` | Metabox, dashboard, widget, settings, score column, score caching |
| `includes/class-nzsa-rest.php` | REST routes for auditing and listing low scores |
| `assets/css/admin.css` / `assets/js/admin.js` | Score/checklist styling and keyword-field hint |
| `uninstall.php` | Removes options and all stored scores/keywords on deletion |

## Verify locally

```bash
php -l nz-seo-audit.php
php -l includes/class-nzsa-*.php
php tests/test-plugin.php   # WordPress-function stubs: good page scores 90+, thin page scores low
```

## Notes

- Scores are cached in post meta (`_nzsa_score`) when a post is saved, so the dashboard never re-parses every post on load.
- The plugin only *reads* Yoast/Rank Math meta descriptions — it never writes to them or overwrites an SEO plugin.
- Deactivation keeps scores; deletion (uninstall) removes options and all stored scores/keywords.
