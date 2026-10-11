# WHATWG Encodings and Labels

This directory contains the listing of encoding labels and their associated encoding name.

 - https://encoding.spec.whatwg.org/#names-and-labels

The non-normative [`encodings.json`](https://encoding.spec.whatwg.org/encodings.json) file comes from the WHATWG server, and
is cached here in the test directory so that it doesn't need to be constantly re-downloaded.

## Updating the optimized lookup class.

The [`class-wp-encodings.php`][1] file contains an optimized lookup map for the names and lables in `encodings.json`.
Run the [`generate-labels-table.php`][2] file to update the auto-generated Core module.

```bash
~$ php tests/phpunit/data/html5-entities/generate-html5-named-character-references.php
OK: Successfully generated optimized lookup class.
```

[1]: ../../../../src/wp-includes/class-wp-encodings.php
[2]: generate-labels-table.php
