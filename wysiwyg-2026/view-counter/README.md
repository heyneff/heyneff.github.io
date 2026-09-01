# eBay-style view counter

An old-school image-based hit counter (classic 7-segment LED look) that works
inside an eBay listing description, where JavaScript and iframes get
stripped but `<img>` tags survive.

Every time the image loads, `counter.php` increments a persistent count and
renders a fresh PNG of the current total — no JS involved.

## Files

- `counter.php` — the whole thing. Increments a count, draws it as 7-segment
  LED digits with GD, outputs PNG.
- `counts/` — where per-listing counts are stored as plain text files. Must
  be writable by PHP.

## Deploy to Bluehost

1. In cPanel, open **File Manager**, go to `public_html` (or a subfolder,
   e.g. `public_html/counter`), and upload `counter.php` and the `counts/`
   folder there. (FTP works too if you prefer that.)
2. Right-click the `counts` folder → **Permissions** → set to `755` (or
   `775` if 755 doesn't let PHP write — Bluehost's PHP normally runs as your
   own user, so 755 is usually enough).
3. In cPanel → **MultiPHP Manager**, make sure the domain is set to **PHP
   8.0 or newer** (the GD polygon calls use the PHP 8 signature). GD is
   enabled by default on Bluehost.
4. Test it by visiting `https://yourdomain.com/counter/counter.php?id=test`
   directly in a browser — you should see a green-on-black digit image, and
   it should go up by one on every reload.

## Use it in an eBay listing

In the listing description editor, switch to the HTML/source view (not the
default rich-text mode — look for a "Show HTML" or `<>` icon) and drop in:

```html
<img src="https://yourdomain.com/counter/counter.php?id=my-listing-name" alt="views" width="97" height="32">
```

Give each listing a different `id` (letters, numbers, `-`, `_` only) so
they don't share one count. `alt` and explicit `width`/`height` keep layout
stable while the image loads.

## Tuning

Open `counter.php` and edit the constants at the top:

- `$DIGITS` — zero-padded width (default 6, e.g. `000123`). Grows
  automatically if the real count exceeds it.
- `$START_OFFSET` — added on top of the real count. Set this to make the
  counter look like it's been running a while (classic move), e.g. `2000`.

## Known limitations (inherent to this approach, not fixable)

- **Counts image loads, not unique visitors.** A page refresh bumps the
  number, same as the originals did — this is authentic to the era but not
  a rigorous analytics tool.
- **eBay may cache or proxy images** served into listings through its own
  CDN. That can mean a burst of first-time loads all incrementing at once
  (cache miss across regions) or, less commonly, a cached response being
  reused briefly rather than hitting your server. There isn't a way around
  this from the listing side — the `Cache-Control: no-cache` headers ask
  politely but eBay's edge network doesn't have to honor them.
- Storage is flat files with locking, fine for one seller's traffic levels;
  not built for high concurrency.
