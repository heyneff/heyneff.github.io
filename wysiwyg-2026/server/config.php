<?php
/**
 * WYSIWYG (2026) — configuration.
 * Michael Neff. Deployed to wysiwyg.michaelneff.com (Bluehost), NOT GitHub Pages.
 */
declare(strict_types=1);

// Seconds each image is displayed before the next takes over.
// Applied to NEW eras only; existing eras keep the period they were published with,
// so historical reconstruction stays exact.
const ROTATE_SECONDS = 90;           // 90 seconds — see README "Why 90 seconds"

// Letterbox every image onto one shared canvas so the eBay layout doesn't jump
// between images of differing aspect ratio. Handled at prep time, not runtime.
const NORMALIZE_ASPECT = false;

// The admin password hash lives in data/admin.hash, NOT here — so a deploy can
// overwrite config.php freely without ever clobbering the password, and so no
// string-rewriting of source is needed to set it.
// Set it with: php tools/set-admin-password.php

// Bot UA fragments excluded from *counts* on the admin page.
// They are still logged in full — nothing is discarded, only classified.
const BOT_PATTERNS = ['bot', 'crawler', 'spider', 'slurp', 'facebookexternalhit', 'preview'];

const IMAGES_DIR = __DIR__ . '/images';
const DATA_DIR   = __DIR__ . '/data';
