# WYSIWYG (2026) — rotating hotlinked image

An eBay listing displays an image whose `src` never changes and whose bytes do.
Every view is recorded, so the image displaying at the moment of sale can be
identified — and the listing then frozen on it permanently.

**This does not run on GitHub Pages.** It lives in the heyneff.github.io repo but
deploys to Bluehost. GitHub Pages has no request logs, cannot vary bytes per
request, and caches via Fastly (`max-age=600`) — all three are fatal here.

- Live: `https://wysiwyg.michaelneff.com/wysiwyg.jpeg`
- Admin: `https://wysiwyg.michaelneff.com/admin/`
- Docroot: `/home3/michaeln/wysiwyg.michaelneff.com` — **must stay outside `public_html`**;
  inside the WordPress tree, Bluehost's Endurance nginx cache replays responses and
  defeats both the rotation and the logging.

## Swapping in the final images

Two different situations. Pick the right one.

### A. Before the exhibition — replace the test set entirely

Retires the provisional images *and* the history they generated, so the real run
starts clean at era 0.

```bash
# 1. wipe (archives, never destroys — everything lands in data/retired-<stamp>/)
ssh -t michaeln@michaelneff.com \
  "cd /home3/michaeln/wysiwyg.michaelneff.com && php tools/reset.php"          # dry run
ssh -t michaeln@michaelneff.com \
  "cd /home3/michaeln/wysiwyg.michaelneff.com && php tools/reset.php --confirm"

# 2. clear the local set and prep the finals
rm server/images/*.jpeg
python3 server/tools/prep-images.py ~/path/to/finals/*.jpg

# 3. push and publish
./server/tools/deploy.sh --images
ssh michaeln@michaelneff.com \
  "cd /home3/michaeln/wysiwyg.michaelneff.com && php tools/publish-era.php 'era 0 — final images'"
```

Between the reset and the publish, `wysiwyg.jpeg` returns **503** — do this before
the listing is live.

### B. During the exhibition — add or change images

Never reset mid-run: retiring `eras.json` destroys the record of what was
displayed when, which is the evidence the piece depends on.

```bash
python3 server/tools/prep-images.py ~/path/to/more/*.jpg
./server/tools/deploy.sh --images
```

Then click **Publish new era** in admin. The old era stays in `eras.json`, so past
timestamps still resolve correctly; only future views use the new set.

### How removal works

`--images` **syncs**: files on the server that aren't in `server/images/` are
deleted, because `tar` alone never removes anything and a stale test image would
otherwise be folded into your next era.

Files a **published era still references** are kept, and the deploy tells you so —
deleting one would make a freeze on it return 503 and its admin preview 404.
Override with `--images --force` only if you're sure that history is disposable.

Prep options: `--normalize --canvas 3:2` letterboxes everything onto one canvas so
the eBay layout stops jumping between aspect ratios; `--max-edge`, `--quality` as needed.

## Why 90 seconds

Rotation period is a per-era setting (`ROTATE_SECONDS` in config.php). With 1342
images:

| Period | Full cycle | 7-day listing |
|---|---|---|
| 600s | 9.3 days | **0.75 cycles — 335 images never shown** |
| 180s | 2.8 days | 2.5 cycles |
| **90s** | **33.5 h** | **5.0 cycles, each image ~5 airings** |
| 60s | 22.4 h | 7.5 cycles |

Two constraints pull against each other.

**Slow enough to be seen, fast enough to be seen changing.** Below ~20s it reads
as a broken image rather than a work. At 600s the rotation is invisible to anyone
who doesn't return the next day — and worse, a week-long listing wouldn't
complete a single pass, so a quarter of the set would never appear at all.

**The checkout gap.** An eBay `<img>` is fetched once per page load and never
updates — no JS runs in a description — so whatever was served at the buyer's
last page load stays frozen on their screen through browsing, checkout and
confirmation. A Buy It Now purchase takes ~2-5 minutes from first look to order
timestamp. At 90s that gap spans ~2 slot boundaries, so **the image on the
buyer's screen and the image the ledger timestamps reliably differ** — and the
freeze records both (see "At sale"). At 600s they'd usually agree, at 300s it
would be an unpredictable coin flip.

## Image preparation, and why it matters here

Every request is served `no-store`, so each view re-downloads the entire file.
The source set runs ~1.5 MB median, which is 2-5 seconds of blank space in the
listing on mobile — that reads as broken, not deliberate.

The set is two kinds of picture needing different codecs:

* **flat placeholders** (broken-image icons, "photo unavailable") — mostly one
  colour with thin text. JPEG rings around that; quantised PNG is sharper *and*
  smaller.
* **photographic generations** — continuous tone, where PNG is enormous and JPEG
  is near-lossless at q85.

`prep-images.py` encodes **both ways per image and keeps the smaller**. Guessing
by source extension gets it backwards for most of this set.

Originals live in `wysiwyg-2026/images-source/` and are never modified. Prepped
output goes to `server/images/`. **Neither is in git** — 2 GB of source would
break the repo (GitHub blocks files >100 MB).

Output is renumbered `0001.jpeg`, `0002.png`… because **rotation order is
filename sort order**, and the originals carry spaces and 100-character prompts.
To change the sequence, rename the prepped files and publish a new era.

## Eras — why they exist

Which image shows is derived from the clock, so any past moment is reconstructable.
Naive `floor(ts/period) % count` breaks the instant the set changes: a 16th image
silently rewrites what every past timestamp resolves to, destroying the record you
need at sale.

So the schedule is **versioned**. Each published set opens an era recording its
start, rotation period, and exact ordered file list. Resolving a timestamp finds
its era first. Old eras are immutable — history stays true, the set stays free to grow.

`data/eras.json` is the whole schedule. Anyone holding it can independently verify
what was displayed at any instant; the claim does not rest on the logs.

## At sale

1. Take the order timestamp from Seller Hub.
2. Admin → **What was showing at…** → paste it.
3. Click **Freeze on this**.

The freeze takes a *timestamp*, not "now", because the piece won't sell while
you're at the keyboard. Paste the sale time hours later and it still lands on the
image that was actually displaying.

## Measured eBay behaviour (2026-08-20, 55 requests)

- eBay does **not** cache or proxy description images. Every load reaches the server.
- All surfaces render them, **including the iOS native app**.
- The iOS app sends **no Referer** (~30% of hits) — never classify traffic by referer alone.
- Safari IPs are **masked by iCloud Private Relay**; `X-Forwarded-For` is null.
  IP correlation is unreliable — identify the sale image by timestamp, not by IP.
- Googlebot-Image crawls within ~12 minutes.
- eBay **gallery** photos must be uploaded to eBay and cannot be hotlinked. Only
  *description* images can, so the listing always carries one fixed photo too.

## Files

| | |
|---|---|
| `serve.php` | the endpoint; resolves, logs, serves with cache-defeating headers |
| `lib.php` | era model, `resolve_at()`, logging |
| `config.php` | rotation period, admin hash — **not overwritten by deploys** |
| `admin/` | console: status, publish, freeze, logs, lookup, CSV |
| `tools/prep-images.py` | image prep |
| `tools/deploy.sh` | code by tar, images by rsync — both over a single ssh connection (Bluehost throttles repeat connects) |
| `tools/publish-era.php` | CLI equivalent of the publish button |
| `tools/set-admin-password.php` | run on the server; prompts without echo |
| `data/` | `eras.json`, `state.json`, `views-YYYY-MM-DD.jsonl` — web-inaccessible |

## eBay description snippet

```html
<img src="https://wysiwyg.michaelneff.com/wysiwyg.jpeg" alt=""
     style="max-width:100%;height:auto;display:block;margin:0 auto;">
```

HTTPS is mandatory (eBay blocks mixed content) and JavaScript is stripped, so the
rotation must be server-side — which it is.
