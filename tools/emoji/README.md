# Twemoji COLRv1 font

`build-twemoji-colrv1.py` builds `src/wp-includes/fonts/twemoji/twemoji-colrv1.woff2`, Twemoji as one COLRv1 color font, and `twemoji-colrv1-flags.woff2`, a subset of it with only the flags. See [#66144](https://core.trac.wordpress.org/ticket/66144).

This is a maintainer tool, run when Twemoji is updated. Sites never run it: they serve the WOFF2 files as static files. The script and its pins record where the fonts came from, and let anyone build it again and get the same bytes.

Nothing loads the fonts yet. Using them, for the emoji a browser cannot draw, is follow-up work in #66144.

## Build

The Docker image pins the base image by digest and installs every Python package from `requirements.lock`, so the tools are the same on every platform.

```sh
docker build -t twemoji-colrv1-build tools/emoji
docker run --rm -v "$PWD/tools/emoji:/work" -v "$PWD/src/wp-includes/fonts/twemoji:/out" -v "<cache>:/cache" \
	twemoji-colrv1-build v17.0.3 --cache /cache --out /out
```

With `--verify`, the script builds again in a temporary directory and fails unless the result is byte-identical to the files in `--out`:

```sh
docker run --rm -v "$PWD/tools/emoji:/work" -v "$PWD/src/wp-includes/fonts/twemoji:/out" -v "<cache>:/cache" \
	twemoji-colrv1-build v17.0.3 --cache /cache --out /out --verify
```

`<cache>` is any directory for the downloaded Twemoji archive and Unicode's `emoji-test.txt`. A full build takes about 20 minutes.

## What the build does

1. Downloads the Twemoji release archive by commit and checks its SHA-256, and the SHA-256 of its SVG set, against `sources.json`. A changed archive or SVG set stops the build. `--record` pins the hashes of a new version whose entries are still `null`.
2. Normalizes the input, as recorded below.
3. Builds the SVGs into a glyf + COLRv1 font with nanoemoji (`glyf_colr_1`, `clipbox_quantization = 32`), with `SOURCE_DATE_EPOCH` set to the Twemoji commit date.
4. Writes the Twemoji attribution into the font's name table and compresses the font to WOFF2.
5. Cuts the flags subset out of that WOFF2.
6. Shapes every sequence in Unicode's `emoji-test.txt` with HarfBuzz and records the counts, for the full font and, limited to the flag profile, for the subset.
7. Writes `manifest.json` (input, tools, output SHA-256, coverage), `source.txt`, `LICENSE-GRAPHICS` and `aliases.txt` next to the font.

## Flags subset

`twemoji-colrv1-flags.woff2` is cut from the full font with the fontTools subsetter, never built separately, so it has the same artwork, aliases and license. It keeps the code points in `FLAGS_CODEPOINTS` (regional indicators, the white and black flags, tag characters, and ZWJ, VS16, rainbow, transgender symbol and skull and crossbones), and a ligature stays when all of its components do. For v17.0.3 it is 108,888 bytes, about a sixth of the full font. Subsets are declared in `SUBSETS` in the build script, each with its file, the code points it asks for and the sequences its coverage is measured on; another profile, such as every emoji except the flags, would be one more entry.

It covers the flag profile: country flags, subdivision flags such as England, Scotland and Wales, and the rainbow, transgender and pirate flags, in every spelling in `emoji-test.txt`. That is the range the asset supports; which flags are replaced is decided by emoji detection and, later, the renderer. It is for browsers that draw other emoji but not flags, such as Chromium on Windows.

## Normalization

Neither step is part of Twemoji. `manifest.json` and `source.txt` record the rules and hashes.

- **Aliases for spellings without FE0F.** Each fully-qualified sequence also gets its spelling with every FE0F removed, and each minimally-qualified or unqualified spelling in `emoji-test.txt` gets one too: a copy of the fully-qualified SVG under that name (1,052 for v17.0.3, listed in `aliases.txt`). In a build without them, Edge rendered 960 fully-qualified sequences split, every one whose SVG name includes FE0F; with them, all 3,944 render as one glyph.
- **Square viewBox.** Files in `SQUARE_VIEWBOX` get their viewBox padded to a centered square. `1f349.svg` in v17.0.3 has `viewBox="0 0 36 25.22"`, which gave it an advance of 1713 against 1275 for every other glyph. It is reported upstream in [jdecked/twemoji#133](https://github.com/jdecked/twemoji/issues/133), with a fix open in [#102](https://github.com/jdecked/twemoji/pull/102); the entry can be removed once that lands. Any other SVG with a non-square viewBox stops the build.

## Checking a font

Whether a font renders correctly is checked in a browser, not with HarfBuzz alone: HarfBuzz shaped all 3,944 fully-qualified sequences to one glyph in the build without aliases. The check used for v17.0.3 drew each sequence in the font and in the system emoji font, and required one glyph width, the font's colors, and a result that differs from the system font's. In Edge 153 on Windows, all 3,944 fully-qualified, 1,029 minimally-qualified, 243 unqualified and 9 component sequences passed with the full font, and every sequence in the flag profile passed with the flags subset (265 fully-qualified, 2 minimally-qualified, 3 unqualified).

Browser results are recorded in `sources.json` after a build, for each output's SHA-256, and the build copies them into `manifest.json` only for outputs with the same SHA-256.

## License

Twemoji's code is MIT; its graphics are CC-BY 4.0. The font carries the attribution in its name table (copyright, license description and license URL), and `LICENSE-GRAPHICS` and `source.txt` sit next to it.
