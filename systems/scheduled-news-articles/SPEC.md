# System: Scheduled News / Articles (static, self-publishing)

A complete editorial subsystem for a **static site** — no CMS, no database, no build server, no cron. An AI agent writes a long-horizon article calendar; every article ships to disk on day one but stays invisible to readers, the index, the sitemap and crawlers until its publication date arrives. On the morning of that date it appears everywhere, automatically, with zero human action.

Also documents the **editorial operating model** that produces the content: how an agent picks topics, assigns keywords, writes to a fixed block grammar, sources photos, and paces a 15-month calendar.

**Type:** full feature subsystem (content model + generator + runtime gating + SEO + editorial process).

**Reference stack:** static HTML + vanilla JS + Python 3 build script + one PHP file for the sitemap. No dependencies, no npm, no framework. Ports cleanly onto Next.js/Astro/Eleventy (notes at the end).

**Proven at:** 30 articles, ~1,200 words each, publishing 1st + 3rd Mondays across 15 months.

---

## Integration Prompt

> Paste everything below this line into the target project.

---

You are given a task to build a **scheduled news/articles system** for a static website.

Reference stack (map onto project equivalents if different):
- **Content store:** one `articles.json` (metadata) + one `content.py` (body copy).
- **Generator:** Python 3 script → one static HTML file per article.
- **Runtime:** two vanilla JS files (index renderer + publication gate).
- **Sitemap:** one PHP file (or any server-side language) that filters by date.

If the project has a CMS or database, **do not use this system** — it exists specifically for hosting with no server-side application layer.

### 1. Overview

The core idea: **all articles exist on disk from day one; the publication date decides visibility.**

```
articles.json ──┬─→ build_articles.py ──→ article-<slug>.html   (one per article)
                ├─→ news-index.js      ──→ /news card grid       (filters date <= today)
                └─→ sitemap.php        ──→ /sitemap.xml          (filters date <= today)

article-<slug>.html ──→ article-gate.js  (seals page + noindex until date)
```

Three independent consumers read the same JSON, so metadata cannot drift between the index, the page, and the sitemap.

### 2. Data model — `articles.json`

Single source of truth. Lives at the site root, publicly fetchable (the index renderer reads it from the browser).

```json
{
  "_comment": "Single source of truth for News & Articles. news-index.js renders entries whose date has arrived; article-gate.js seals individual pages until then; sitemap.php lists only published entries. Dates are 1st and 3rd Mondays.",

  "author": {
    "name": "Jane Doe",
    "shortName": "Jane",
    "role": "President & CEO, Example Company",
    "image": "assets/author-jane.webp",
    "url": "/about"
  },

  "articles": [
    {
      "slug": "armor-protection-levels-explained",
      "title": "NIJ, CEN, VPAM and STANAG: Reading Armor Protection Levels Without Getting Fooled",
      "dek": "Four standards, four vocabularies, and one expensive way to get it wrong. What each level actually certifies, and how to write the one you need into a contract.",
      "date": "2025-12-15",
      "category": "Materials & Testing",
      "keyword": "armored vehicle protection levels explained",
      "image": "assets/ballistic-test.webp",
      "imageAlt": "Witnessed ballistic testing of a transparent armor panel",
      "readMinutes": 7
    }
  ]
}
```

Field contract:

| Field | Rule |
|---|---|
| `slug` | lowercase, hyphenated, unique. Becomes `/news/<slug>` and `article-<slug>.html`. Never change after publication (breaks links + rankings). |
| `title` | 55–75 chars. Written for humans first; the target keyword appears naturally, not bolted on. |
| `dek` | 140–165 chars — doubles as `<meta description>`, OG description and the index card blurb. One sentence of tension, one of payoff. |
| `date` | `YYYY-MM-DD`. **This field alone controls visibility.** String comparison against today's ISO date — no timezone math anywhere. |
| `category` | From a closed set (4–6 total). Shown as a chip; used for `articleSection` in JSON-LD. |
| `keyword` | One primary search phrase. One per article, never duplicated across the calendar. |
| `image` | Path relative to site root, no leading slash. Reused across articles is fine (see §8). |
| `imageAlt` | Real description of the photo, not the keyword. |
| `readMinutes` | Integer, ≈ words ÷ 170, rounded. |

**Single-author model.** `author` is one object shared by all articles — right for a founder/CEO byline series. For multi-author, change to `"authors": { "jane": {...} }` and add an `author` key per article; the generator's `author[...]` lookups become `authors[meta["author"]]`.

### 3. Content store — `content.py`

Body copy lives separately from metadata, as **typed blocks, not HTML**. This is what makes restyling every article a one-line change.

```python
# content.py
BODIES = {
    "armor-protection-levels-explained": [
        ("p", "Every armored vehicle conversation eventually arrives at a letter and a number..."),
        ("h2", "The four standards you will actually encounter"),
        ("p", "**NIJ** — the United States National Institute of Justice. Familiar to anyone..."),
        ("list", [
            "NIJ Level III — rifle threats, 7.62x51mm NATO ball.",
            "NIJ Level IV — armor-piercing rifle, .30-06 M2 AP.",
        ]),
        ("fig", "assets/ballistic-panel.webp",
                "Transparent armor panel after a witnessed test",
                "Witnessed test, 7.62x51mm at 15 m"),
        ("quote", "A certificate that does not name the witness lab is a marketing document."),
        ("h3", "What to write into the contract"),
        ("takeaways", [
            "Name the standard **and** the level — 'B6' alone is ambiguous.",
            "Require a witnessed test certificate from a named lab.",
        ]),
    ],
}
```

Block grammar — seven types, deliberately small:

| Kind | Shape | Renders |
|---|---|---|
| `p` | `("p", text)` | Paragraph. Supports `**bold**` and `[text](href)`. |
| `h2` | `("h2", text)` | Section heading. |
| `h3` | `("h3", text)` | Sub-heading. |
| `list` | `("list", [items])` | Dash-marker list. Items support inline markup. |
| `quote` | `("quote", text)` | Pull-quote, auto-attributed to the author. |
| `fig` | `("fig", src, alt, caption)` | Figure with corner-bracket frame + caption. |
| `takeaways` | `("takeaways", [items])` | "What to take away" summary box. |

Refusing arbitrary HTML is the point: an agent writing article #27 cannot invent a new visual treatment that breaks the design system.

### 4. Generator — `build_articles.py`

Run from the site root: `python3 _build/build_articles.py`. Overwrites `article-<slug>.html` for every entry that has a body; **reports skipped slugs loudly** so a partial run is visible rather than silent.

Per page it emits:

- **SEO head** — title (`{title} | {Brand}`), description, canonical, `robots`, theme-color, full OG (`article` type, published_time, section, author) and Twitter `summary_large_image`.
- **`<meta name="hpc:publish" content="{date}">`** — the signal the gate reads. Rename the vendor prefix to your project.
- **Article JSON-LD** — headline, description, image, datePublished/Modified, **computed `wordCount`**, keywords, articleSection, `mainEntityOfPage`, author as `Person` (with jobTitle + url + image), publisher as `Organization` (with logo).
- **BreadcrumbList JSON-LD** — Home → News → this article.
- **Related articles** — 3 cards, auto-selected: same feed, `date <= this article's date`, excluding self. Never surfaces a future article as "related". Falls back to an "All articles" card when there is no older sibling.
- Hero with parallax, category chip, pretty date, reading time, author byline.

Core of the script:

```python
#!/usr/bin/env python3
"""Render article-<slug>.html from articles.json + content.py."""
import html, json, os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from content import BODIES

SITE = "https://www.example.com/"

def esc(t):
    return html.escape(t, quote=False)

def inline(t):
    """Minimal inline markup: **bold** and [text](href)."""
    t = esc(t)
    t = re.sub(r"\*\*(.+?)\*\*", r'<strong>\1</strong>', t)
    t = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", r'<a href="\2">\1</a>', t)
    return t

def block(b):
    kind = b[0]
    if kind == "p":
        return '<p data-reveal="up">%s</p>' % inline(b[1])
    if kind in ("h2", "h3"):
        return '<%s data-reveal="up">%s</%s>' % (kind, esc(b[1]), kind)
    if kind == "quote":
        return ('<blockquote data-reveal="up">%s<span class="byline">%s</span></blockquote>'
                % (inline(b[1]), esc(AUTHOR["name"])))
    if kind == "fig":
        _, src, alt, caption = b
        src = src if src.startswith("/") else "/" + src
        return ('<figure data-reveal="up"><img loading="lazy" decoding="async" src="%s" alt="%s">'
                '<figcaption>%s</figcaption></figure>' % (esc(src), esc(alt), esc(caption)))
    if kind in ("list", "takeaways"):
        items = "".join("<li>%s</li>" % inline(i) for i in b[1])
        cls = "takeaways" if kind == "takeaways" else "bullets"
        return '<ul class="%s" data-reveal="up">%s</ul>' % (cls, items)
    raise ValueError("unknown block type: %r" % kind)

def word_count(blocks):
    n = 0
    for b in blocks:
        if b[0] in ("p", "h2", "h3", "quote"):
            n += len(b[1].split())
        elif b[0] in ("list", "takeaways"):
            n += sum(len(i.split()) for i in b[1])
    return n

def render_page(meta, author, blocks, siblings):
    url = SITE + "news/%s" % meta["slug"]
    body = "\n".join(block(b) for b in blocks)

    # Related: older-or-equal siblings only — never leak a scheduled article.
    related = [a for a in siblings
               if a["slug"] != meta["slug"] and a["date"] <= meta["date"]][:3]

    ld = {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": meta["title"],
        "description": meta["dek"],
        "image": SITE + meta["image"],
        "datePublished": meta["date"],
        "dateModified": meta["date"],
        "wordCount": word_count(blocks),
        "keywords": meta["keyword"],
        "articleSection": meta["category"],
        "inLanguage": "en",
        "mainEntityOfPage": {"@type": "WebPage", "@id": url},
        "author": {
            "@type": "Person",
            "name": author["name"],
            "jobTitle": author["role"],
            "image": SITE + author["image"],
            "url": SITE.rstrip("/") + author["url"],
        },
        "publisher": {
            "@type": "Organization",
            "name": "Example Company",
            "url": SITE,
            "logo": {"@type": "ImageObject", "url": SITE + "assets/logo.webp"},
        },
    }
    crumbs = {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "Home", "item": SITE},
            {"@type": "ListItem", "position": 2, "name": "News & Articles", "item": SITE + "news"},
            {"@type": "ListItem", "position": 3, "name": meta["title"], "item": url},
        ],
    }

    months = ["January","February","March","April","May","June","July",
              "August","September","October","November","December"]
    y, m, d = meta["date"].split("-")
    pretty = "%s %d, %s" % (months[int(m) - 1], int(d), y)

    return TEMPLATE.format(
        title_seo=esc("%s | Example" % meta["title"]),
        title=esc(meta["title"]),
        dek_attr=html.escape(meta["dek"], quote=True),
        url=url, date=meta["date"], pretty=pretty,
        category=esc(meta["category"]),
        image=esc(meta["image"]),
        image_src="/" + meta["image"],
        image_alt=html.escape(meta["imageAlt"], quote=True),
        author_name=esc(author["name"]),
        read=meta["readMinutes"],
        ld=json.dumps(ld, indent=2),
        crumbs=json.dumps(crumbs, indent=2),
        body=body,
        related=render_related(related),
    )

def main():
    with open(os.path.join(ROOT, "articles.json")) as fh:
        data = json.load(fh)
    author, articles = data["author"], data["articles"]

    built, skipped = 0, []
    for meta in articles:
        blocks = BODIES.get(meta["slug"])
        if not blocks:
            skipped.append(meta["slug"])
            continue
        out = os.path.join(ROOT, "article-%s.html" % meta["slug"])
        with open(out, "w") as fh:
            fh.write(render_page(meta, author, blocks, articles))
        built += 1

    print("built %d article(s)" % built)
    if skipped:
        print("SKIPPED (no body in content.py): %s" % ", ".join(skipped))

if __name__ == "__main__":
    main()
```

### 5. Publication gate — `article-gate.js`

Loaded by every article page. If the date is in the future it injects `noindex,nofollow` and replaces the body with a scheduled notice.

```js
/* Scheduled-article gate.
 *
 * Every article page carries <meta name="hpc:publish" content="YYYY-MM-DD">.
 * Until that date arrives the page seals itself: the body is replaced with a
 * short scheduled notice and a noindex directive is injected, so a crawler
 * that reaches the URL early does not index an unpublished piece.
 *
 * The gate is client-side by necessity (static hosting). It is a publication
 * schedule, not access control — do not put anything confidential behind it.
 */
(function () {
  var meta = document.querySelector('meta[name="hpc:publish"]');
  if (!meta) return;

  function p(n) { return (n < 10 ? '0' : '') + n; }
  var publish = meta.getAttribute('content') || '';
  var d = new Date();
  var today = d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());

  if (publish <= today) return;   // live — leave the page alone

  var robots = document.querySelector('meta[name="robots"]');
  if (!robots) {
    robots = document.createElement('meta');
    robots.setAttribute('name', 'robots');
    document.head.appendChild(robots);
  }
  robots.setAttribute('content', 'noindex,nofollow');

  var SEAL_MARKER = 'data-sealed';

  function sealedHTML() {
    var months = ['January','February','March','April','May','June','July',
                  'August','September','October','November','December'];
    var parts = publish.split('-');
    var pretty = months[parseInt(parts[1], 10) - 1] + ' ' + parseInt(parts[2], 10) + ', ' + parts[0];
    return '<div class="scheduled-notice">'
      + '<div class="eyebrow">Scheduled</div>'
      + '<h2>This Article Publishes ' + pretty + '</h2>'
      + '<p>It is part of the twice-monthly series. Everything already published is on the news index.</p>'
      + '<a href="/news">Read Published Articles</a></div>';
  }

  function reseal() {
    var host = document.querySelector('[data-article-body]');
    if (host && host.getAttribute(SEAL_MARKER) !== '1') {
      host.innerHTML = sealedHTML();
      host.setAttribute(SEAL_MARKER, '1');
    }
    return !!host;
  }

  // If the page body is client-rendered, the runtime may commit it in more
  // than one pass — a one-shot seal gets clobbered by the next commit. Keep
  // re-asserting until a stable window passes with nothing left to fix.
  var stableTicks = 0, STABLE_TARGET = 10, TICK_MS = 150;

  var observer = new MutationObserver(function () { reseal(); stableTicks = 0; });
  observer.observe(document.documentElement, { childList: true, subtree: true });

  var poll = setInterval(function () {
    var mounted = reseal();
    if (mounted && ++stableTicks >= STABLE_TARGET) {
      clearInterval(poll); observer.disconnect();
    }
  }, TICK_MS);

  // Hard stop so a page that never mounts the section doesn't poll forever.
  setTimeout(function () { clearInterval(poll); observer.disconnect(); }, 15000);
})();
```

If the site is plain static HTML (no client-side rendering), drop the observer/poll and call `reseal()` once on `DOMContentLoaded`.

### 6. Index renderer — `news-index.js`

Fetches the feed, filters to published, renders newest-first into `[data-articles]`. First card is featured (spans the grid, 2-column split).

```js
(function () {
  var mount = document.querySelector('[data-articles]');
  if (!mount) return;

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function todayISO() {
    var d = new Date();
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }
  function longDate(iso) {
    var p = iso.split('-');
    var months = ['January','February','March','April','May','June','July',
                  'August','September','October','November','December'];
    return months[parseInt(p[1], 10) - 1] + ' ' + parseInt(p[2], 10) + ', ' + p[0];
  }

  function card(a, featured) {
    var link = document.createElement('a');
    link.href = '/news/' + a.slug;
    link.className = 'article-card' + (featured ? ' featured' : '');
    link.setAttribute('data-reveal', 'up');       // picked up by the scroll animator
    link.innerHTML =
        '<div class="figure"><img src="' + a.image + '" alt="'
      + (a.imageAlt || a.title) + '" loading="lazy" decoding="async">'
      + '<span class="chip">' + a.category + '</span></div>'
      + '<div class="body">'
      + '<div class="meta">' + longDate(a.date) + '  ·  ' + a.readMinutes + ' MIN READ</div>'
      + '<h3>' + a.title + '</h3><p>' + a.dek + '</p></div>';
    return link;
  }

  function emptyState(message) {
    var box = document.createElement('div');
    box.className = 'articles-empty';
    box.setAttribute('data-reveal', 'up');
    box.innerHTML = '<h3>' + message + '</h3>';
    return box;
  }

  function render(data) {
    var today = todayISO();
    var live = (data.articles || [])
      .filter(function (a) { return a.date <= today; })
      .sort(function (x, y) { return x.date < y.date ? 1 : x.date > y.date ? -1 : 0; });

    mount.innerHTML = '';
    if (!live.length) { mount.appendChild(emptyState('The first articles publish shortly')); return; }
    live.forEach(function (a, i) { mount.appendChild(card(a, i === 0)); });

    var counter = document.querySelector('[data-article-count]');
    if (counter) counter.textContent = live.length + (live.length === 1 ? ' article' : ' articles');
  }

  fetch('/articles.json', { cache: 'no-cache' })
    .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(render)
    .catch(function () {
      // Never leave the section blank if the feed cannot be read.
      mount.innerHTML = '';
      mount.appendChild(emptyState('Articles are temporarily unavailable'));
    });
})();
```

If the site uses a scroll-reveal animator, make sure it watches the DOM with a `MutationObserver` — these cards are injected after first paint and otherwise never animate.

### 7. Sitemap + URLs

**`sitemap.php`** — static pages unconditional, articles only once published. No manual step when a publication date passes.

```php
<?php
header('Content-Type: application/xml; charset=utf-8');
$base  = 'https://www.example.com/';
$today = date('Y-m-d');

$pages = ['/' => ['Home.html','1.0'], 'about' => ['About.html','0.8'], 'news' => ['News.html','0.9']];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($pages as $path => [$file, $priority]) {
    $loc     = $base . ltrim($path, '/');
    $lastmod = is_file(__DIR__ . '/' . $file) ? date('Y-m-d', filemtime(__DIR__ . '/' . $file)) : $today;
    echo "  <url><loc>$loc</loc><lastmod>$lastmod</lastmod><priority>$priority</priority></url>\n";
}

$feed = json_decode(file_get_contents(__DIR__ . '/articles.json'), true);
foreach ($feed['articles'] as $a) {
    if ($a['date'] > $today) continue;          // scheduled — not in the index yet
    echo "  <url><loc>{$base}news/{$a['slug']}</loc><lastmod>{$a['date']}</lastmod>"
       . "<priority>0.7</priority></url>\n";
}
echo '</urlset>';
```

**`.htaccess`** — pretty URLs + permanent redirects from the file names:

```apache
RewriteEngine On

# canonical → file
RewriteRule ^news$                        News.html            [L]
RewriteRule ^news/([a-z0-9-]+)$           article-$1.html      [L]

# legacy file names → canonical (301)
RewriteRule ^News\.html$                  /news                [R=301,L,NC]
RewriteRule ^article-([a-z0-9-]+)\.html$  /news/$1             [R=301,L,NC]
RewriteRule ^articles$                    /news                [R=301,L]

# sitemap.xml is generated
RewriteRule ^sitemap\.xml$                sitemap.php          [L]
```

> **Critical gotcha:** `/news/<slug>` is one directory level deeper than the file that serves it. **Every asset, script and stylesheet reference must be root-relative** (`/assets/…`, `/site.js`) — including any component-loader's internal base path. Relative refs (`assets/…`, `./site.js`) resolve to `/news/assets/…` and 404. This breaks silently on the article pages only, so it survives casual testing.

---

## The editorial operating model

The system above is the machine. This is the process that feeds it — written as instructions for an AI agent producing the calendar.

### 8. Cadence and dates

- **Frequency:** twice monthly — **1st and 3rd Mondays**. Frequent enough to signal an active site, sparse enough that quality never drops to fill a slot.
- **Horizon:** 30 articles ≈ **15 months**, generated in one pass. The whole calendar ships on day one; the gate releases it over time.
- **Why Monday:** B2B/industrial readership. Publishing lands in the working week, not the weekend.
- **All dates are the same weekday** — verify with `datetime.date.fromisoformat(d).weekday() == 0` before shipping; an off-by-one date is invisible in review and obvious in production.
- **Dates are strings, compared as strings.** `"2026-03-01" <= "2026-03-15"` is correct lexicographically for ISO dates. No `Date` parsing, no timezone bugs.

**Agent instruction:** generate the date list first, mechanically, before writing a single word — then assign topics to slots. Writing first and dating afterwards produces clumped or skipped Mondays.

### 9. Topic strategy

Topics come from the **buyer's actual questions**, not from keyword-tool volume. For a considered industrial purchase, the highest-value queries are low-volume and high-intent.

Distribute across a closed set of **4–6 categories** so the index stays legible. The reference build used 5, roughly balanced:

| Category | Count | Answers |
|---|---|---|
| Who Needs It | 7 | "Is this product for someone like me?" |
| Materials & Testing | 6 | "How do I know it actually works?" |
| Procurement | 6 | "How do I buy it without getting burned?" |
| Engineering | 6 | "How is it built, and why that way?" |
| Sustainment | 5 | "What happens after I own it?" |

**Agent instruction — topic sourcing, in priority order:**
1. Questions the sales team answers repeatedly on calls (ask for the top 20).
2. Objections that kill deals ("too expensive", "we'll just retrofit ourselves").
3. Expensive mistakes buyers make when uninformed — the highest-trust content there is.
4. Standards/spec confusion in the category.
5. Only then: keyword tools, to phrase the title, never to pick the subject.

Never write "Top 10 X" or "Ultimate Guide to Y". Every title should make a **specific, falsifiable claim** a competitor would hesitate to publish.

### 10. SEO rules

- **One primary keyword per article, never reused** across the calendar. Duplicates cannot both rank — they cannibalize. Keep a running list; check every new entry against it.
- **Long-tail and question-shaped** — `"do i need an armored vehicle"`, `"how to write armored vehicle specification"`. These match how buyers actually search and how LLM assistants get asked.
- **Keyword goes in:** title (naturally), `dek`, first paragraph, one `h2`. Nowhere else. No density targets.
- **`dek` is the meta description.** Writing it once and using it in three places guarantees they never drift.
- **Structured data is non-negotiable:** `Article` with real `wordCount` + `author` as a `Person` with `jobTitle` and a URL to a real bio page. This is the E-E-A-T signal that separates a founder-written piece from generic content.
- **Internal links:** 2–4 per article to product/service pages, in `[text](/path)` inline markup. The related-articles block handles article-to-article automatically.
- **Scheduled articles never leak:** absent from sitemap, absent from the index, `noindex` on the page itself. Three independent layers, all driven by one date field.

### 11. Content rules

Target **1,150–1,300 words** (reference build: min 1,150, max 1,305, avg 1,197). Long enough to answer completely, short enough to finish. `readMinutes` = words ÷ 170.

Typical block distribution per article — from 30 real articles:

| Block | Per article | Purpose |
|---|---|---|
| `p` | ~16 | The argument. |
| `h2` | ~7 | Scannable structure; one carries the keyword. |
| `h3` | ~1 | Sub-structure where a section needs it. |
| `list` | ~1 | Where prose would become a comma pile. |
| `fig` | ~1 | Visual proof, never decoration. |
| `quote` | 1 | Exactly one — the article's sharpest claim, in the author's voice. |
| `takeaways` | 1 | Always last. 3–5 items. Written so someone who read only this box still leaves with the useful part. |

**Voice:** first-person plural for the company, second person for the reader. Concrete numbers over adjectives. Name the tradeoff, then resolve it. Admit what the product does *not* do — the credibility gained is worth more than the objection avoided.

**Agent instruction — the writing pass:**
1. Write `takeaways` **first**. If you can't state 3–5 concrete takeaways, the topic is not ready.
2. Write the `quote` second — the single sharpest defensible claim.
3. Build the `h2` skeleton, then fill paragraphs.
4. Run the word count; trim to range. **Never pad to hit a number.**
5. Verify every factual claim against a source the company can actually stand behind. In a regulated/technical category, an invented spec figure is a legal problem, not a typo.

### 12. Photos

- **Real work only.** Stock photography destroys the credibility the writing builds. The reference build used the company's own production-floor, testing and delivery photography throughout.
- **Reuse is fine and expected.** 30 articles ran on **19 distinct images** (6 reused). Nobody browses the index looking for image duplicates; forcing 30 unique photos means 11 weak ones.
- **Format:** WebP, ≤200 KB, ≥1600 px wide (hero doubles as OG image at 1200×630 crop).
- **`imageAlt` describes the photograph** — what is happening, where. Not the keyword. Screen-reader users and image search both punish keyword-stuffed alt text.
- **`fig` captions carry information the photo cannot** — the test standard, the distance, the date. A caption that just restates the alt text should be deleted.
- **Assign images after writing**, matching the article's strongest section. Choosing the photo first biases the argument toward whatever you have a picture of.

### 13. Operating the calendar

**Adding an article:**
1. Append the entry to `articles.json` (mind the keyword-uniqueness list).
2. Add the body to `content.py` under the same slug.
3. `python3 _build/build_articles.py`
4. Deploy. It appears by itself on its date.

**Never** change a published article's `slug` or `date`. Both are load-bearing for links and rankings. To correct content, edit `content.py` and rebuild — `dateModified` tracks it automatically.

**Verify before shipping a calendar:** all dates are the intended weekday; no duplicate keywords; no duplicate slugs; every slug has a body; every image path exists on disk; word counts in range.

```bash
python3 - <<'EOF'
import json, collections, datetime, os, sys
sys.path.insert(0, "_build")
from content import BODIES
d = json.load(open("articles.json")); arts = d["articles"]
slugs = [a["slug"] for a in arts]; keys = [a["keyword"] for a in arts]
dupe = lambda xs: [k for k, v in collections.Counter(xs).items() if v > 1]
assert not dupe(slugs), "duplicate slugs: %s" % dupe(slugs)
assert not dupe(keys),  "duplicate keywords: %s" % dupe(keys)
for a in arts:
    assert datetime.date.fromisoformat(a["date"]).weekday() == 0, "not a Monday: " + a["date"]
    assert a["slug"] in BODIES, "no body: " + a["slug"]
    assert os.path.isfile(a["image"]), "missing image: " + a["image"]
print("calendar OK —", len(arts), "articles")
EOF
```

### 14. Known limitation — read this before shipping

**The gate is client-side.** It depends on the visitor's own clock, and the unpublished HTML is fetchable by anyone who guesses the URL. A visitor with a forward-set clock sees the article early; `curl` sees the full body regardless of date.

That is acceptable for an **editorial calendar** and unacceptable for anything confidential, embargoed, or contractually date-bound.

If the host has any server-side execution, add a PHP guard — same `articles.json`, same date field, no client trust:

```php
<?php // top of article-<slug>.html served through PHP
$feed = json_decode(file_get_contents(__DIR__ . '/articles.json'), true);
$slug = basename($_SERVER['REQUEST_URI']);
foreach ($feed['articles'] as $a) {
    if ($a['slug'] === $slug && $a['date'] > date('Y-m-d')) {
        http_response_code(404);
        header('X-Robots-Tag: noindex, nofollow');
        readfile(__DIR__ . '/404.html');
        exit;
    }
}
```

### 15. Porting to a framework

| This system | Next.js / Astro / Eleventy equivalent |
|---|---|
| `articles.json` | Same file, or frontmatter collection |
| `content.py` blocks | MDX, or a JSON block array rendered by a component map |
| `build_articles.py` | Framework's static generation (`getStaticPaths` / content collections) |
| `article-gate.js` | Filter at build time + revalidate on the date (ISR), or a server-side date guard |
| `news-index.js` | Server-rendered list filtered at build/request time |
| `sitemap.php` | Framework sitemap plugin with the same date filter |

The **editorial model in §8–§13 is stack-independent** and is the part worth keeping regardless of implementation.

### Steps to integrate

1. Create `articles.json` with the author block and 2–3 test entries (one past-dated, one future-dated).
2. Create `_build/content.py` with bodies for those slugs.
3. Drop in `build_articles.py`, set `SITE` and the brand strings, adapt `TEMPLATE` to the project's layout and styles.
4. Run it; confirm one page renders and one is skipped-with-warning if its body is missing.
5. Add `article-gate.js` to the article template and `news-index.js` to the index page; add `[data-article-body]` and `[data-articles]` hooks.
6. **Verify the future-dated article is sealed and `noindex`, and absent from the index.**
7. Add `sitemap.php` + the rewrite rules; confirm `/sitemap.xml` excludes the future article.
8. Confirm every asset reference on `/news/<slug>` is root-relative (open devtools on an article page, check for 404s).
9. Run the calendar validator from §13.

---

## Metadata

- **Category:** Content / editorial subsystem
- **Stack:** Static HTML + vanilla JS + Python 3 + PHP (sitemap). No dependencies.
- **Files:** `articles.json`, `_build/content.py`, `_build/build_articles.py`, `article-gate.js`, `news-index.js`, `sitemap.php`, `.htaccess`
- **Proven at:** 30 articles / 15-month calendar / ~1,200 words each, twice-monthly self-publishing
- **Not for:** sites with a CMS or database; embargoed or confidential content (see §14)
