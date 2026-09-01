#!/usr/bin/env bash
# WYSIWYG — deploy to Bluehost.
#
# The artwork lives in a GitHub Pages repo but does NOT run there: GitHub Pages
# has no request logs and caches via Fastly, which would defeat both the rotation
# and the tracking. This pushes to Bluehost over SSH instead.
#
#   ./tools/deploy.sh              # code + images
#   ./tools/deploy.sh --code       # code only
#   ./tools/deploy.sh --images     # images: SYNC — remote extras are removed
#   ./tools/deploy.sh --images --force   # ...even if a published era references them
#
# Everything travels as ONE tar stream over ONE ssh connection: Bluehost throttles
# rapid repeat connections (cPhulk) and an scp-per-file loop reliably trips it.
#
# Image sync deletes remote files absent locally, because tar alone never removes
# anything — a deleted test image would otherwise linger server-side and be folded
# into the next published era.
set -euo pipefail

HOST="michaeln@michaelneff.com"
PORT=22
DEST="/home3/michaeln/wysiwyg.michaelneff.com"
SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

MODE="all"; FORCE=0; FORCE_FLAG=""
for a in "$@"; do
  case "$a" in
    --code|--images) MODE="$a" ;;
    --force) FORCE=1; FORCE_FLAG="--force" ;;
    *) echo "unknown option: $a" >&2; exit 2 ;;
  esac
done

STAGE="$(mktemp -d)"; trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/admin" "$STAGE/tools" "$STAGE/data" "$STAGE/images"

DO_CODE=0; DO_IMAGES=0
[[ "$MODE" == "all" || "$MODE" == "--code"   ]] && DO_CODE=1
[[ "$MODE" == "all" || "$MODE" == "--images" ]] && DO_IMAGES=1

if (( DO_CODE )); then
  cp "$SRC/serve.php" "$SRC/lib.php" "$SRC/config.php"   "$STAGE/"
  cp "$SRC/index.html"                                   "$STAGE/index.html"
  cp "$SRC/htaccess"                                     "$STAGE/.htaccess"
  cp "$SRC/htaccess-data"                                "$STAGE/data/.htaccess"
  # filename -> midjourney|placeholder, used to compute interspersed order
  [ -f "$SRC/image-types.json" ] && cp "$SRC/image-types.json" "$STAGE/data/image-types.json"
  cp "$SRC/admin/index.php" "$SRC/admin/auth.php" "$SRC/admin/preview.php" "$STAGE/admin/"
  cp "$SRC/admin/htaccess"                               "$STAGE/admin/.htaccess"
  cp "$SRC/tools/set-admin-password.php" "$SRC/tools/publish-era.php" "$SRC/tools/reset.php" "$SRC/tools/sync-images.php" "$STAGE/tools/"
fi

MANIFEST=""
IMG_COUNT=0
if (( DO_IMAGES )); then
  IMG_COUNT=$(find "$SRC/images" -maxdepth 1 -type f ! -name '.*' | wc -l | tr -d ' ')
  if (( IMG_COUNT == 0 )); then
    echo "!! server/images/ is empty. Refusing to sync — that would delete every image." >&2
    echo "   Run tools/prep-images.py first; it writes there." >&2
    exit 1
  fi
  MANIFEST=$(cd "$SRC/images" && find . -maxdepth 1 -type f ! -name '.*' -exec basename {} \; | sort)
  echo "→ $IMG_COUNT image(s), $(du -sh "$SRC/images" | cut -f1) to sync"
fi

# Images go by rsync, not in the tar: 1342 files / ~200 MB as one tar stream is
# slow, unresumable, and re-sends every byte each time. rsync is incremental and
# still uses a single SSH connection, so it does not trip Bluehost's cPhulk
# throttle the way an scp-per-file loop did. Deletion is deliberately left to
# sync-images.php, which refuses to remove a file a published era still needs.
if (( DO_IMAGES )); then
  if command -v rsync >/dev/null 2>&1; then
    echo "→ rsync images"
    # --info=progress2 is rsync 3.1+; macOS ships openrsync / rsync 2.6.9, which
    # rejects it outright. Stick to flags both understand.
    rsync -rtz --exclude='.*' -e "ssh -p $PORT" \
      "$SRC/images/" "$HOST:$DEST/images/"
  else
    echo "→ rsync unavailable locally; falling back to tar (slower)"
    cp "$SRC"/images/* "$STAGE/images/" 2>/dev/null || true
  fi
fi

echo "→ uploading $(find "$STAGE" -type f | wc -l | tr -d ' ') files in one stream"

COPYFILE_DISABLE=1 tar -C "$STAGE" --no-xattrs -czf - . \
| ssh -p "$PORT" "$HOST" "
  set -e
  D='$DEST'
  mkdir -p \"\$D\"
  tar -C \"\$D\" -xzf -
  find \"\$D\" -name '._*' -delete 2>/dev/null || true

  if [ '$DO_IMAGES' = '1' ]; then
    cat > \"\$D/data/.manifest\" <<'__MANIFEST__'
$MANIFEST
__MANIFEST__
    php \"\$D/tools/sync-images.php\" \"\$D/data/.manifest\" $FORCE_FLAG
    rm -f \"\$D/data/.manifest\"
  fi

  chmod 755 \"\$D\" \"\$D/admin\" \"\$D/images\" \"\$D/tools\"
  chmod 733 \"\$D/data\" 2>/dev/null || chmod 777 \"\$D/data\"
  find \"\$D\" -maxdepth 2 -type f -name '*.php' -exec chmod 644 {} \;
  find \"\$D/images\" -type f -exec chmod 644 {} \; 2>/dev/null || true
  [ -f \"\$D/data/admin.hash\" ] && chmod 600 \"\$D/data/admin.hash\"
  php -l \"\$D/serve.php\" >/dev/null && php -l \"\$D/lib.php\" >/dev/null \
    && php -l \"\$D/admin/index.php\" >/dev/null && echo '  syntax ok'
  echo \"  images on server: \$(ls -1 \"\$D/images\" 2>/dev/null | wc -l | tr -d ' ')\"
"

echo "✓ https://wysiwyg.michaelneff.com/wysiwyg.jpeg"
echo "  admin: https://wysiwyg.michaelneff.com/admin/"
(( DO_IMAGES )) && echo "  → now click 'Publish new era' in admin to make the new set live"
