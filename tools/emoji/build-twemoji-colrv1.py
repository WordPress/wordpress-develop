#!/usr/bin/env python3
"""Build the Twemoji COLRv1 font from a pinned Twemoji release.

This is a maintainer tool. WordPress sites never run it: they serve the WOFF2
it writes. It exists so that the font can be traced to its source and built
again when Twemoji is updated.

    build-twemoji-colrv1.py v17.0.3 --cache /cache --out src/wp-includes/fonts/twemoji
    build-twemoji-colrv1.py v17.0.3 --cache /cache --out src/wp-includes/fonts/twemoji --verify

See README.md for running it in the pinned Docker image.

Steps:

1. Download the release archive for the pinned commit, and check its SHA-256
   and the SHA-256 of its SVG files against sources.json. With --record, a
   version whose hashes are still null gets them written instead.
2. Normalize the listed upstream sources (SQUARE_VIEWBOX). Add a copy of an
   SVG for each sequence emoji-test.txt lists without one of
   its FE0F (minimally-qualified or unqualified), under that sequence's name,
   so the font draws both spellings. Then build the SVGs into a glyf + COLRv1
   font with nanoemoji.
3. Write the Twemoji attribution into the font's name table.
4. Compress the font to WOFF2.
5. Shape every sequence in Unicode's emoji-test.txt with HarfBuzz and count
   which ones the font draws as a single visible glyph.
6. Write manifest.json, source.txt and LICENSE-GRAPHICS next to the font.

--verify builds again in a temporary directory and fails unless the new font
and manifest are byte-identical to the ones in --out.
"""

import argparse
import hashlib
import io
import json
import os
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
import urllib.request
from collections import Counter
from datetime import datetime
from importlib import metadata
from pathlib import Path

HERE = Path(__file__).resolve().parent
SOURCES = HERE / "sources.json"
FAMILY = "Twemoji"
FONT_FILE = "twemoji-colrv1.woff2"
TOOLS = ("nanoemoji", "picosvg", "fonttools", "uharfbuzz", "brotli", "skia-pathops")
LICENSE_URL = "https://creativecommons.org/licenses/by/4.0/"


def sha256_bytes(data):
    return hashlib.sha256(data).hexdigest()


def sha256_file(path):
    return sha256_bytes(Path(path).read_bytes())


def fail(message):
    print(f"error: {message}", file=sys.stderr)
    sys.exit(1)


def load_sources():
    return json.loads(SOURCES.read_text(encoding="utf-8"))


def save_sources(sources):
    SOURCES.write_text(json.dumps(sources, indent="\t", ensure_ascii=False) + "\n", encoding="utf-8", newline="\n")


def fetch(url, path):
    if not path.exists():
        print(f"Downloading {url}")
        with urllib.request.urlopen(url) as response:
            path.write_bytes(response.read())
    return path.read_bytes()


def check_pin(entry, key, actual, record, label):
    """Compares a hash with sources.json, or records it with --record."""
    pinned = entry.get(key)
    if pinned is None:
        if not record:
            fail(f"{label} is not pinned yet ({actual}); run with --record to pin it")
        entry[key] = actual
        return True
    if pinned != actual:
        fail(f"{label} does not match sources.json: expected {pinned}, got {actual}")
    return False


def extract_svgs(archive, svg_dir):
    """Extracts assets/svg/*.svg and returns {file name: bytes}."""
    svgs = {}
    with tarfile.open(fileobj=io.BytesIO(archive), mode="r:gz") as tar:
        for member in tar.getmembers():
            parts = member.name.split("/")
            if member.isfile() and len(parts) == 4 and parts[1:3] == ["assets", "svg"] and parts[3].endswith(".svg"):
                svgs[parts[3]] = tar.extractfile(member).read()
            elif member.isfile() and len(parts) == 2 and parts[1] == "LICENSE-GRAPHICS":
                svgs["LICENSE-GRAPHICS"] = tar.extractfile(member).read()
    license_text = svgs.pop("LICENSE-GRAPHICS", None)
    if license_text is None:
        fail("the archive has no LICENSE-GRAPHICS")
    if svg_dir.exists():
        shutil.rmtree(svg_dir)
    svg_dir.mkdir(parents=True)
    for name, data in svgs.items():
        (svg_dir / name).write_bytes(data)
    return svgs, license_text


def svg_tree_sha256(svgs):
    """One hash for the SVG set: each file's name and SHA-256, sorted by name."""
    lines = "".join(f"{name}\0{sha256_bytes(data)}\n" for name, data in sorted(svgs.items()))
    return sha256_bytes(lines.encode("utf-8"))


# Upstream source normalization: files changed before the build, and how.
# A non-square viewBox did not match the advance width of the rest of the
# nanoemoji output: in the first v17.0.3 build, 1f349.svg (viewBox
# "0 0 36 25.22") got an advance of 1713 against 1275 for every other glyph.
# These files are padded to a centered square. Any other file with a
# non-square viewBox stops the build.
SQUARE_VIEWBOX = ("1f349.svg",)


def square_viewbox(data):
    """Pads the SVG's viewBox to a square with the artwork centered."""
    text = data.decode("utf-8")
    match = re.search(r'viewBox="([^"]+)"', text)
    x, y, w, h = (float(v) for v in match.group(1).split())
    side = max(w, h)
    box = [x - (side - w) / 2, y - (side - h) / 2, side, side]
    value = " ".join(f"{v:.4f}".rstrip("0").rstrip(".") for v in box)
    return (text[: match.start(1)] + value + text[match.end(1) :]).encode("utf-8")


def viewbox_is_square(data):
    match = re.search(rb'viewBox="([^"]+)"', data)
    if not match:
        return False
    w, h = (float(v) for v in match.group(1).split()[2:])
    return w == h


def normalize_sources(svg_dir):
    """Applies SQUARE_VIEWBOX and returns what changed, with hashes."""
    changed = []
    for path in sorted(svg_dir.glob("*.svg")):
        data = path.read_bytes()
        if path.name in SQUARE_VIEWBOX:
            new = square_viewbox(data)
            changed.append({
                "file": path.name,
                "rule": "pad viewBox to a centered square",
                "input_sha256": sha256_bytes(data),
                "output_sha256": sha256_bytes(new),
                "unchanged": new == data,
            })
            path.write_bytes(new)
        elif not viewbox_is_square(data):
            fail(f"{path.name} has a non-square viewBox; add it to SQUARE_VIEWBOX or fix the input")
    return changed


def svg_name(codepoints):
    return "-".join(f"{c:x}" for c in codepoints) + ".svg"


def add_unqualified_aliases(svg_dir, emoji_test):
    """Copies SVGs for sequences written without some or all of their FE0F.

    Twemoji names many SVGs by their fully-qualified sequence, FE0F included.
    In Chromium, the GSUB mapping for those sequences did not apply and they
    rendered split (Edge VQA of the first build: 960 fully-qualified sequences,
    every one whose SVG name includes FE0F), so every fully-qualified sequence
    gets an alias with all FE0F removed. The minimally-qualified and unqualified spellings in
    emoji-test.txt get one too. Each alias is a copy of the fully-qualified
    SVG, so nanoemoji maps it to the same artwork. Returns the file names.
    """
    existing = {p.name for p in svg_dir.glob("*.svg")}
    by_stripped = {}
    for codepoints, status in parse_emoji_test(emoji_test):
        if status != "fully-qualified":
            continue
        stripped = tuple(c for c in codepoints if c != 0xFE0F)
        for candidate in (svg_name(codepoints), svg_name(stripped)):
            if candidate in existing:
                by_stripped[stripped] = candidate
                break
    aliases = []

    def add(name, source):
        if name in existing or source is None:
            return
        (svg_dir / name).write_bytes((svg_dir / source).read_bytes())
        existing.add(name)
        aliases.append(name)

    for stripped, source in sorted(by_stripped.items()):
        add(svg_name(stripped), source)
    for codepoints, status in parse_emoji_test(emoji_test):
        if status in ("minimally-qualified", "unqualified"):
            add(svg_name(codepoints), by_stripped.get(tuple(c for c in codepoints if c != 0xFE0F)))
    return sorted(aliases)


def build_ttf(svg_dir, build_dir, env):
    config = build_dir / "twemoji.toml"
    srcs = ", ".join(json.dumps(p.as_posix()) for p in sorted(svg_dir.glob("*.svg")))
    config.write_text(
        f'family = "{FAMILY}"\n'
        'output_file = "twemoji-colrv1.ttf"\n'
        'color_format = "glyf_colr_1"\n'
        "clipbox_quantization = 32\n"
        # A static font: nanoemoji wants the tables, and leaving them empty
        # means no variation axes.
        "\n[axis]\n"
        f"\n[master.regular]\nstyle_name = \"Regular\"\nsrcs = [{srcs}]\n"
        "\n[master.regular.position]\n",
        encoding="utf-8",
    )
    subprocess.run(["nanoemoji", f"--build_dir={build_dir}", str(config)], check=True, env=env)
    return build_dir / "twemoji-colrv1.ttf"


def set_names(ttf_path, version):
    from fontTools.ttLib import TTFont

    font = TTFont(ttf_path)
    names = {
        0: "Twemoji graphics: Copyright Twitter, Inc and other contributors, and jdecked and other contributors.",
        5: f"Version {version.lstrip('v')}; Twemoji {version}",
        13: f"Twemoji graphics are licensed under CC-BY 4.0: {LICENSE_URL}",
        14: LICENSE_URL,
    }
    for name_id, value in names.items():
        font["name"].setName(value, name_id, 3, 1, 0x409)
    font.save(ttf_path)


def to_woff2(ttf_path, woff2_path):
    from fontTools.ttLib import woff2

    woff2.compress(str(ttf_path), str(woff2_path))


def parse_emoji_test(text):
    """Yields (codepoints, status) for each sequence in emoji-test.txt."""
    for line in text.splitlines():
        line = line.split("#", 1)[0].strip()
        if not line or ";" not in line:
            continue
        codes, status = (part.strip() for part in line.split(";", 1))
        yield tuple(int(c, 16) for c in codes.split()), status


def kind(codepoints):
    cps = set(codepoints)
    if 0x200D in cps:
        return "zwj"
    if 0xE007F in cps:
        return "tag"
    if len(codepoints) == 2 and all(0x1F1E6 <= c <= 0x1F1FF for c in codepoints):
        return "flag"
    if 0x20E3 in cps:
        return "keycap"
    if any(0x1F3FB <= c <= 0x1F3FF for c in codepoints) and len(codepoints) > 1:
        return "modifier"
    return "single"


def measure(font_path, emoji_test):
    """Counts the sequences the font draws as one glyph, by status and kind.

    `visible` hides default-ignorable characters such as FE0F, as browsers do:
    a sequence counts when one visible glyph is left. `strict` counts only
    sequences shaped to exactly one glyph with nothing hidden.
    """
    import uharfbuzz as hb

    blob = hb.Blob.from_file_path(str(font_path))
    font = hb.Font(hb.Face(blob))

    def shape(codepoints, hide):
        buf = hb.Buffer()
        buf.add_codepoints(list(codepoints))
        buf.guess_segment_properties()
        if hide:
            buf.flags = hb.BufferFlags.REMOVE_DEFAULT_IGNORABLES
        hb.shape(font, buf)
        glyphs = [info.codepoint for info in buf.glyph_infos]
        return len(glyphs) == 1 and glyphs[0] != 0

    totals, visible, strict, missing = Counter(), Counter(), Counter(), []
    for codepoints, status in parse_emoji_test(emoji_test):
        key = f"{status}/{kind(codepoints)}"
        totals[key] += 1
        strict[key] += int(shape(codepoints, False))
        if shape(codepoints, True):
            visible[key] += 1
        else:
            missing.append(f"{status}: " + " ".join(f"{c:04X}" for c in codepoints))

    def rollup(keys):
        return {
            "total": sum(totals[k] for k in keys),
            "visible": sum(visible[k] for k in keys),
            "strict": sum(strict[k] for k in keys),
        }

    statuses = sorted({k.split("/")[0] for k in totals})
    return {
        "by_status": {s: rollup([k for k in totals if k.startswith(s + "/")]) for s in statuses},
        "by_kind": {k: rollup([k]) for k in sorted(totals)},
        "not_visible": missing,
    }


def font_tables(ttf_path):
    from fontTools.ttLib import TTFont

    font = TTFont(ttf_path)
    colr = font["COLR"] if "COLR" in font else None
    return {
        "tables": sorted(font.keys()),
        "colr_version": colr.version if colr is not None else None,
        "glyphs": len(font.getGlyphOrder()),
        "cmap_codepoints": len(font.getBestCmap()),
        "units_per_em": font["head"].unitsPerEm,
    }


def build(version, cache, out, record):
    sources = load_sources()
    entry = sources["versions"].get(version)
    if entry is None:
        fail(f"{version} is not in sources.json")
    changed = False

    archive_url = f"https://codeload.github.com/jdecked/twemoji/tar.gz/{entry['commit']}"
    archive = fetch(archive_url, cache / f"twemoji-{entry['commit']}.tar.gz")
    changed |= check_pin(entry, "archive_sha256", sha256_bytes(archive), record, "archive SHA-256")

    work = Path(tempfile.mkdtemp(prefix="twemoji-colrv1-"))
    try:
        svgs, license_text = extract_svgs(archive, work / "svg")
        changed |= check_pin(entry, "svg_tree_sha256", svg_tree_sha256(svgs), record, "SVG tree SHA-256")
        changed |= check_pin(entry, "svg_count", len(svgs), record, "SVG count")

        rgi_path = cache / "emoji-test-17.0.txt"
        rgi = fetch(sources["rgi"]["url"], rgi_path)
        if sha256_bytes(rgi) != sources["rgi"]["sha256"]:
            fail("emoji-test.txt does not match sources.json")
        normalized = normalize_sources(work / "svg")
        aliases = add_unqualified_aliases(work / "svg", rgi.decode("utf-8"))

        # nanoemoji and fontTools stamp the font with this time instead of now.
        epoch = int(datetime.fromisoformat(entry["commit_date"].replace("Z", "+00:00")).timestamp())
        env = dict(os.environ, SOURCE_DATE_EPOCH=str(epoch))
        os.environ["SOURCE_DATE_EPOCH"] = str(epoch)

        build_dir = work / "build"
        build_dir.mkdir()
        ttf = build_ttf(work / "svg", build_dir, env)
        set_names(ttf, version)
        out.mkdir(parents=True, exist_ok=True)
        woff2 = out / FONT_FILE
        to_woff2(ttf, woff2)

        manifest = {
            "font": FONT_FILE,
            "family": FAMILY,
            "color_format": "glyf_colr_1",
            "twemoji": {
                "version": version,
                "repository": sources["repository"],
                "commit": entry["commit"],
                "commit_date": entry["commit_date"],
                "archive_url": archive_url,
                "archive_sha256": entry["archive_sha256"],
                "svg_count": entry["svg_count"],
                "svg_tree_sha256": entry["svg_tree_sha256"],
                "graphics_license": "CC-BY-4.0",
            },
            "source_normalization": normalized,
            "aliases": {
                "stage": "normalization added by this build; not part of the Twemoji input",
                "rule": "Each fully-qualified sequence with FE0F gets an alias without any FE0F (in Chromium, GSUB mappings that include FE0F did not apply and those sequences rendered split), and each minimally-qualified or unqualified sequence in emoji-test.txt without its own SVG gets one; an alias is a copy of the fully-qualified SVG.",
                "count": len(aliases),
                "sha256": sha256_bytes("".join(a + chr(10) for a in aliases).encode("utf-8")),
            },
            "rgi": sources["rgi"],
            "tools": {name: metadata.version(name) for name in TOOLS},
            "source_date_epoch": epoch,
            "output": {
                "woff2_bytes": woff2.stat().st_size,
                "woff2_sha256": sha256_file(woff2),
                "ttf_bytes": ttf.stat().st_size,
                "ttf_sha256": sha256_file(ttf),
            },
            "font_tables": font_tables(ttf),
            "coverage": measure(ttf, rgi.decode("utf-8")),
        }
        (out / "manifest.json").write_text(json.dumps(manifest, indent="\t", ensure_ascii=False) + "\n", encoding="utf-8", newline="\n")
        (out / "LICENSE-GRAPHICS").write_bytes(license_text)
        # The alias list itself, so its SHA-256 in the manifest can be checked.
        (out / "aliases.txt").write_text("".join(a + chr(10) for a in aliases), encoding="utf-8", newline="\n")
        (out / "source.txt").write_text(
            f"Font: {FAMILY} ({FONT_FILE})\n"
            f"Source: {sources['repository']} {version}, commit {entry['commit']}, assets/svg ({entry['svg_count']} files)\n"
            "Upstream source normalization (not part of Twemoji): "
            + "; ".join(f"{n['file']}: {n['rule']} ({n['input_sha256']} -> {n['output_sha256']})" for n in normalized)
            + "\n"
            f"Aliases (not part of Twemoji): {len(aliases)}, each a copy of a fully-qualified SVG under a spelling without FE0F: all FE0F removed (in Chromium, GSUB mappings that include FE0F did not apply), and the minimally-qualified and unqualified spellings in Unicode emoji-test.txt 17.0 (list SHA-256 {manifest['aliases']['sha256']})\n"
            f"Build: nanoemoji {metadata.version('nanoemoji')}, color format glyf_colr_1, then WOFF2 (fontTools {metadata.version('fonttools')})\n"
            "License: graphics CC-BY 4.0 (LICENSE-GRAPHICS); attribution: Twitter, Inc and other contributors, and jdecked and other contributors\n"
            "Purpose: emoji font fallback, built once by maintainers and served as a static file\n",
            encoding="utf-8",
            newline="\n",
        )
    finally:
        shutil.rmtree(work, ignore_errors=True)

    if changed:
        save_sources(sources)
        print(f"Pinned {version} in sources.json")
    return manifest


def main():
    parser = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    parser.add_argument("version", help="Twemoji release tag in sources.json, such as v17.0.3")
    parser.add_argument("--cache", type=Path, required=True, help="Directory for the downloaded archive and emoji-test.txt")
    parser.add_argument("--out", type=Path, required=True, help="Directory for the font and its manifest")
    parser.add_argument("--record", action="store_true", help="Pin hashes that are still null in sources.json")
    parser.add_argument("--verify", action="store_true", help="Build again and compare with --out")
    args = parser.parse_args()
    args.cache.mkdir(parents=True, exist_ok=True)

    if not args.verify:
        manifest = build(args.version, args.cache, args.out, args.record)
        for status, counts in manifest["coverage"]["by_status"].items():
            print(f"{status}: visible {counts['visible']}/{counts['total']}, strict {counts['strict']}/{counts['total']}")
        print(f"{FONT_FILE}: {manifest['output']['woff2_bytes']} bytes, {manifest['aliases']['count']} aliases")
        return

    with tempfile.TemporaryDirectory() as tmp:
        fresh = Path(tmp)
        build(args.version, args.cache, fresh, record=False)
        problems = [
            name for name in (FONT_FILE, "manifest.json", "source.txt", "LICENSE-GRAPHICS", "aliases.txt")
            if not (args.out / name).exists() or (args.out / name).read_bytes() != (fresh / name).read_bytes()
        ]
    if problems:
        fail("rebuild differs from --out: " + ", ".join(problems))
    print(f"Verified: a fresh build of {args.version} is byte-identical to {args.out}")


if __name__ == "__main__":
    main()
