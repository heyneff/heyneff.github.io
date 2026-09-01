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

## Adding images

Drop files into `wysiwyg-2026/incoming/`, then from `server/`:

```bash
./tools/add-images.sh
```

That is the whole thing. It prepares them (adaptive JPEG/PNG, resized), assigns the
next free numbers, tags their type, files the originals in `images-source/`, uploads,
and publishes a new era with the placeholders spread evenly through the set.

```bash
./tools/add-images.sh --dry-run              # show the plan, change nothing
./tools/add-images.sh --type midjourney      # default is placeholder
./tools/add-images.sh ~/some/other/folder    # read from elsewhere
```

**Do not put images in `server/images/`.** That folder holds prepared output only;
anything dropped there skips preparation and would be served at full size. The script
detects strays there, says so, and processes them properly rather than failing.

Nothing on the live listing changes until the script finishes.

### Removing images

`./tools/deploy.sh --images` syncs — server files absent locally are deleted. Files a
**published era still references** are kept and reported, because deleting one would
make a freeze on it return 503 and its admin preview 404. `--images --force` overrides.

### Starting over (before the exhibition only)

```bash
ssh -t michaeln@michaelneff.com \
  "cd /home3/michaeln/wysiwyg.michaelneff.com && php tools/reset.php"    # dry run
```

Add `--confirm` to proceed. Nothing is destroyed — images, eras, state and logs move to
`data/retired-<stamp>/`. **Never mid-exhibition:** retiring `eras.json` destroys the
record of what was displayed when, which is the evidence the work depends on.

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
