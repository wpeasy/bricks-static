#!/usr/bin/env python3
"""
i18n build helper for Bricks Static.

Two modes:

  extract   Read the .pot file, print a JSON array of unique translatable
            source strings (msgid, plus msgid_plural where present). URLs,
            e-mail/author headers and pure-metadata are skipped so the
            machine-translation pass only sees real UI copy.

  assemble  Read the .pot file + one JSON dict per locale
            (scripts/i18n/<locale>.json mapping source string -> translation,
            and "<source>\\x00<plural>" -> [one, many] for plurals), then write
            languages/<domain>-<locale>.po and compile the matching .mo.

Locales: fr_FR, de_DE, it_IT, es_ES, nl_NL.
"""

import json
import sys
from pathlib import Path

import polib

ROOT = Path(__file__).resolve().parent.parent
JSON_DIR = Path(__file__).resolve().parent / "i18n"

# (pot path, output .po/.mo dir, textdomain)
DOMAINS = [
    (ROOT / "languages" / "bricks-static.pot", ROOT / "languages", "bricks-static"),
]

LOCALES = {
    "fr_FR": "French",
    "de_DE": "German",
    "it_IT": "Italian",
    "es_ES": "Spanish",
    "nl_NL": "Dutch",
}

# Plural-Forms header per locale (all five use the Germanic/Romance n != 1 rule).
PLURAL_FORMS = "nplurals=2; plural=(n != 1);"


def is_translatable(entry) -> bool:
    """Skip header-metadata source strings that should stay as-is."""
    m = entry.msgid
    if not m.strip():
        return False
    if m.startswith("http://") or m.startswith("https://"):
        return False
    if "@" in m and " " in m and "." in m.split("@")[-1]:  # author "Name <email>"
        return False
    if m in ("bricks-static",):
        return False
    return True


def extract() -> None:
    seen = {}
    for pot_path, _out, _domain in DOMAINS:
        pot = polib.pofile(str(pot_path))
        for entry in pot:
            if not is_translatable(entry):
                continue
            key = entry.msgid
            if key in seen:
                continue
            seen[key] = {"msgid": entry.msgid}
            if entry.msgid_plural:
                seen[key]["msgid_plural"] = entry.msgid_plural
    JSON_DIR.mkdir(parents=True, exist_ok=True)
    out = JSON_DIR / "_strings.json"
    out.write_text(json.dumps(list(seen.values()), ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"wrote {len(seen)} strings -> {out}")


def _norm(s: str) -> str:
    """Fold smart punctuation to ASCII so agent keys match source msgids even
    when the model normalised curly quotes / dashes."""
    return (
        s.replace("’", "'").replace("‘", "'")
        .replace("“", '"').replace("”", '"')
        .replace("—", "-").replace("–", "-")
        .replace("…", "...").replace(" ", " ")
        .strip()
    )


def build_lookup(tr: dict) -> tuple[dict, dict, dict, dict]:
    """Return (exact, norm, plural_exact, plural_norm) lookups.

    Recovers two agent quirks: plural keys joined by NUL *or* newline, and
    singular pairs (e.g. "%d item"/"%d items") an agent merged into one
    NUL/newline-joined list key.
    """
    exact, norm = {}, {}
    pl_exact, pl_norm = {}, {}
    for k, v in tr.items():
        if isinstance(v, str):
            exact[k] = v
            norm.setdefault(_norm(k), v)
            continue
        if isinstance(v, list) and len(v) >= 2:
            parts = None
            for sep in ("\x00", "\n"):
                if sep in k:
                    parts = k.split(sep)
                    break
            if parts and len(parts) == 2:
                a, b = parts
                # As a true plural pair...
                pl_exact[(a, b)] = (v[0], v[1])
                pl_norm[(_norm(a), _norm(b))] = (v[0], v[1])
                # ...and as two recovered singulars.
                exact.setdefault(a, v[0])
                exact.setdefault(b, v[1])
                norm.setdefault(_norm(a), v[0])
                norm.setdefault(_norm(b), v[1])
    return exact, norm, pl_exact, pl_norm


def load_locale(locale: str) -> dict:
    path = JSON_DIR / f"{locale}.json"
    if not path.is_file():
        raise SystemExit(f"Missing translations: {path}")
    # strict=False tolerates stray control chars some models emit.
    return json.loads(path.read_text(encoding="utf-8"), strict=False)


def assemble() -> None:
    for locale in LOCALES:
        tr = load_locale(locale)
        exact, norm, pl_exact, pl_norm = build_lookup(tr)
        for pot_path, out_dir, domain in DOMAINS:
            po = polib.pofile(str(pot_path))
            po.metadata["Language"] = locale
            po.metadata["Plural-Forms"] = PLURAL_FORMS
            po.metadata["PO-Revision-Date"] = "2026-06-22 00:00+0000"
            po.metadata["Last-Translator"] = "Claude Code (machine translation)"
            po.metadata["Project-Id-Version"] = domain

            misses = []
            for entry in po:
                if not entry.msgid.strip():
                    continue
                if entry.msgid_plural:
                    forms = (
                        pl_exact.get((entry.msgid, entry.msgid_plural))
                        or pl_norm.get((_norm(entry.msgid), _norm(entry.msgid_plural)))
                    )
                    if forms:
                        entry.msgstr_plural = {0: forms[0], 1: forms[1]}
                    else:
                        misses.append(entry.msgid)
                else:
                    val = exact.get(entry.msgid) or norm.get(_norm(entry.msgid))
                    if isinstance(val, str) and val != "":
                        entry.msgstr = val
                    else:
                        misses.append(entry.msgid)

            po_path = out_dir / f"{domain}-{locale}.po"
            mo_path = out_dir / f"{domain}-{locale}.mo"
            po.save(str(po_path))
            po.save_as_mofile(str(mo_path))
            translated = len(po.translated_entries())
            total = len([e for e in po if e.msgid.strip()])
            note = "" if not misses else "  (untranslated: " + " | ".join(_norm(m)[:30] for m in misses[:4]) + ")"
            print(f"{domain}-{locale}: {translated}/{total} translated -> {po_path.name} + .mo{note}")


if __name__ == "__main__":
    mode = sys.argv[1] if len(sys.argv) > 1 else "extract"
    if mode == "extract":
        extract()
    elif mode == "assemble":
        assemble()
    else:
        raise SystemExit("usage: i18n_build.py [extract|assemble]")
