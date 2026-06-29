# HTML5 Entities

This directory contains the listing of HTML5 named character references and a script that can be used
to create or update the optimized form for use in the HTML API.

The HTML5 specification asserts:

> This list is static and will not be expanded or changed in the future.
>  - https://html.spec.whatwg.org/#named-character-references

The authoritative [`entities.json`](https://html.spec.whatwg.org/entities.json) file comes from the WHATWG server, and
is cached here in the test directory so that it doesn't need to be constantly re-downloaded.

## Updating the optimized lookup class.

The [`html5-named-character-references.php`][1] file contains an optimized lookup map for the entities in `entities.json`.
Run the [`generate-html5-named-character-references.php`][2] file to update the auto-generated Core module.

```bash
~$ php tests/phpunit/data/html5-entities/generate-html5-named-character-references.php
OK: Successfully generated optimized lookup class.
```

[1]: ../../../../src/wp-includes/html-api/html5-named-character-references.php
[2]: ./generate-html5-named-character-references.php
