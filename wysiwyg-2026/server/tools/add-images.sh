#!/usr/bin/env bash
# WYSIWYG — add new images to the rotation, end to end.
#
#   1. drop files into wysiwyg-2026/incoming/
#   2. ./tools/add-images.sh
#
#   ./tools/add-images.sh --type midjourney   # default is placeholder
#   ./tools/add-images.sh --dry-run
#   ./tools/add-images.sh ~/some/other/folder
#
# Does the whole sequence: prepare, number, file the originals, upload, publish.
#
# Why a script rather than steps: the pieces are individually easy to get wrong.
# Numbers must never be reused (a published era records filenames, so reusing one
# would make that era point at a different picture). Originals must not be served
# — they are ~1.5MB each and every view re-downloads. And a new era must be
# published with an explicit interspersed order, or the placeholders clump at the
# end of the cycle.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVER="$(dirname "$HERE")"
W="$(dirname "$SERVER")"
INCOMING="$W/incoming"
SOURCE="$W/images-source"
PREPPED="$SERVER/images"
TYPES="$SERVER/image-types.json"

TYPE="placeholder"; DRY=0; SRCDIR=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    --type) TYPE="$2"; shift 2 ;;
    --type=*) TYPE="${1#*=}"; shift ;;
    --dry-run) DRY=1; shift ;;
    -*) echo "unknown option: $1" >&2; exit 2 ;;
    *) SRCDIR="$1"; shift ;;
  esac
done
[[ "$TYPE" == "placeholder" || "$TYPE" == "midjourney" ]] || { echo "--type must be placeholder or midjourney" >&2; exit 2; }
[[ -n "$SRCDIR" ]] || SRCDIR="$INCOMING"

mkdir -p "$INCOMING" "$SOURCE"

# Collect candidates from the drop folder.
# read-loops rather than mapfile: macOS ships bash 3.2, which has no mapfile.
collect() {
  find "$1" -maxdepth 1 -type f ! -name '.*' ! -name '*.txt' \
    \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.gif' \
       -o -iname '*.webp' -o -iname '*.bmp' -o -iname '*.tif' -o -iname '*.tiff' \
       -o -iname '*.svg' \) | sort
}
NEW=()
while IFS= read -r f; do [ -n "$f" ] && NEW+=("$f"); done < <(collect "$SRCDIR")

# Also rescue anything dropped into the prepared-output folder by mistake —
# anything not matching the NNNN.ext pattern was never prepared.
STRAYS=()
while IFS= read -r f; do [ -n "$f" ] && STRAYS+=("$f"); done < <(
  find "$PREPPED" -maxdepth 1 -type f ! -name '.*' 2>/dev/null \
  | grep -vE '/[0-9]{4}\.(jpeg|png)$' || true)
if [ "${#STRAYS[@]:-0}" -gt 0 ]; then
  echo "!! ${#STRAYS[@]} unprepared file(s) found in server/images/ — that folder holds"
  echo "   prepared output only. Moving them into the drop folder and processing them."
  for f in "${STRAYS[@]}"; do mv "$f" "$SRCDIR/"; done
  NEW=()
  while IFS= read -r f; do [ -n "$f" ] && NEW+=("$f"); done < <(collect "$SRCDIR")
fi

if [ "${#NEW[@]:-0}" -eq 0 ]; then
  echo "Nothing to add. Put images in: $INCOMING"
  exit 0
fi

# Next free number. Never reuse: published eras record filenames.
LAST=$(find "$PREPPED" -maxdepth 1 -name '[0-9][0-9][0-9][0-9].*' -exec basename {} \; 2>/dev/null \
       | cut -c1-4 | sort -n | tail -1)
LAST=${LAST:-0}
START=$((10#$LAST + 1))

echo "→ ${#NEW[@]} new image(s), type=$TYPE, numbering from $(printf '%04d' "$START")"
(( DRY )) && { printf '   %s\n' "${NEW[@]##*/}"; echo "Dry run."; exit 0; }

python3 "$HERE/prep-images.py" --start-index "$START" "$SRCDIR"

# Record the type of each new file; filenames are opaque numbers by design, so
# interspersed_order() cannot infer it.
python3 - "$TYPES" "$PREPPED" "$START" "$TYPE" <<'PY'
import json, os, sys, glob
types_path, prepped, start, kind = sys.argv[1], sys.argv[2], int(sys.argv[3]), sys.argv[4]
types = json.load(open(types_path)) if os.path.exists(types_path) else {}
n = 0
for p in sorted(glob.glob(os.path.join(prepped, "[0-9][0-9][0-9][0-9].*"))):
    b = os.path.basename(p)
    if int(b[:4]) >= start:
        types[b] = kind; n += 1
json.dump(types, open(types_path, "w"), indent=0, sort_keys=True)
print(f"   tagged {n} new file(s) as {kind}")
PY

# Originals are archived, never served.
for f in "${NEW[@]}"; do mv "$f" "$SOURCE/"; done
echo "   originals filed in images-source/"

"$HERE/deploy.sh" --images
"$HERE/deploy.sh" --code >/dev/null 2>&1 || true

ssh -p 22 michaeln@michaelneff.com \
  "cd /home3/michaeln/wysiwyg.michaelneff.com && php tools/publish-era.php 'added ${#NEW[@]} ${TYPE}(s)'"

echo
echo "✓ https://wysiwyg.michaelneff.com/wysiwyg.jpeg"
