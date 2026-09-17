#!/usr/bin/env python3
"""Validate English/uk parity and reproducibly compile the bundled catalog."""
import argparse
import gettext
import io
import json
import re
import struct
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PATTERN = re.compile(
    r"\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)"
    r"\(\s*'((?:\\.|[^'\\])*)'\s*,\s*'ironcreed-request-log'"
)
PLACEHOLDER = re.compile(r"%(?:\d+\$)?[+-]?(?:\d+)?(?:\.\d+)?[bcdeEfFgGosuxX]")


def catalog():
    messages = {}
    for path in [ROOT / "ironcreed-request-log.php", *sorted((ROOT / "includes").rglob("*.php"))]:
        for match in PATTERN.finditer(path.read_text()):
            message = match[1].replace("\\'", "'").replace("\\\\", "\\")
            messages.setdefault(message, set()).add(str(path.relative_to(ROOT)))
    return messages


def read_po(path):
    result = {}
    key = value = None
    target = ""
    for line in path.read_text().splitlines() + [""]:
        if line.startswith("#,") and "fuzzy" in line:
            raise ValueError("Fuzzy translations must be reviewed.")
        if line.startswith("msgid "):
            key = json.loads(line[6:])
            target = "key"
        elif line.startswith("msgstr "):
            value = json.loads(line[7:])
            target = "value"
        elif line.startswith('"'):
            if target == "key":
                key += json.loads(line)
            else:
                value += json.loads(line)
        elif not line and key is not None:
            if key in result:
                raise ValueError("Duplicate translation: " + key)
            result[key] = value
            key = value = None
    return result


def mo_bytes(messages):
    pairs = sorted((k.encode(), v.encode()) for k, v in messages.items())
    count = len(pairs)
    keys = b"".join(k + b"\0" for k, _ in pairs)
    values = b"".join(v + b"\0" for _, v in pairs)
    offset = 28 + 16 * count
    key_table = []
    value_table = []
    cursor = offset
    for key, _ in pairs:
        key_table.extend((len(key), cursor))
        cursor += len(key) + 1
    cursor = offset + len(keys)
    for _, value in pairs:
        value_table.extend((len(value), cursor))
        cursor += len(value) + 1
    return (
        struct.pack("<7I", 0x950412DE, 0, count, 28, 28 + count * 8, 0, 0)
        + struct.pack("<" + "I" * (count * 4), *(key_table + value_table))
        + keys + values
    )


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--write", action="store_true", help="Regenerate POT/MO after editing the PO.")
    args = parser.parse_args()
    source = catalog()
    translated = read_po(ROOT / "languages/ironcreed-request-log-uk.po")
    if set(source) != set(translated) - {""}:
        raise SystemExit("Catalog drift. Missing: " + repr(set(source) - set(translated))
                         + "; obsolete: " + repr(set(translated) - set(source) - {""}))
    for message in source:
        value = translated[message]
        if not value or sorted(PLACEHOLDER.findall(message)) != sorted(PLACEHOLDER.findall(value)):
            raise SystemExit("Missing translation or mismatched placeholders: " + message)
    pot = 'msgid ""\nmsgstr "Content-Type: text/plain; charset=UTF-8\\n"\n\n'
    for message, paths in sorted(source.items()):
        pot += "#: " + " ".join(sorted(paths)) + "\n"
        pot += "msgid " + json.dumps(message, ensure_ascii=False) + '\nmsgstr ""\n\n'
    compiled = mo_bytes(translated)
    # Verify that a standard gettext reader can actually load the generated file.
    loaded = gettext.GNUTranslations(io.BytesIO(compiled))
    if loaded.gettext("Settings") != "Налаштування":
        raise SystemExit("Compiled catalog did not load correctly.")
    for path, expected in [
        (ROOT / "languages/ironcreed-request-log.pot", pot.encode()),
        (ROOT / "languages/ironcreed-request-log-uk.mo", compiled),
    ]:
        if args.write:
            path.write_bytes(expected)
        elif not path.is_file() or path.read_bytes() != expected:
            raise SystemExit("Stale generated translation: " + str(path))
    print(f"Translations synchronized: {len(source)} English/uk strings; placeholders and MO verified.")


if __name__ == "__main__":
    main()
