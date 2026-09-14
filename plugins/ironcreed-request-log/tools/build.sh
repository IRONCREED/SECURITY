#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
out="${1:-$root/../../../build}"
stage="$(mktemp -d)"; temporary=""
cleanup() { rm -rf "$stage"; [[ -z "$temporary" ]] || rm -f "$temporary"; }
trap cleanup EXIT
mkdir -p "$stage/ironcreed-request-log" "$out"
cp "$root"/{ironcreed-request-log.php,uninstall.php,readme.txt,license.txt,changelog.txt} "$stage/ironcreed-request-log/"
cp -R "$root/includes" "$stage/ironcreed-request-log/"
mkdir -p "$stage/ironcreed-request-log/docs"
cp "$root/docs/HOSTING-UKRAINE-API-CONTRACT.md" "$stage/ironcreed-request-log/docs/"
target="$out/ironcreed-request-log-1.0.0.zip"
temporary="$out/.ironcreed-request-log-1.0.0.$$.tmp.zip"
( cd "$stage" && find ironcreed-request-log -print0 | LC_ALL=C sort -z | xargs -0 touch -t 202608280000 && find ironcreed-request-log -type f -print | LC_ALL=C sort | zip -X -q "$temporary" -@ )
mv -f "$temporary" "$target"
temporary=""
echo "Built $target"
