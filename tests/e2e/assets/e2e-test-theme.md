# Theme fixture

`e2e-test-theme.zip` contains a minimal classic theme with three files. Its template executes no code. The screenshot reuses the existing PHPUnit image fixture. The update URI prevents WordPress.org update matching.

To reproduce the archive from the repository root with Python 3:

```python
from pathlib import Path
from zipfile import ZipFile, ZipInfo

files = {
    'style.css': '/*\nTheme Name: E2E Test Theme\nVersion: 1.0\nUpdate URI: false\n*/\n',
    'index.php': '<?php\n// E2E theme fixture.\n',
    'screenshot.jpg': Path('tests/phpunit/data/images/test-image.jpg').read_bytes(),
}
with ZipFile('tests/e2e/assets/e2e-test-theme.zip', 'w') as archive:
    for name, content in files.items():
        entry = ZipInfo(
            'e2e-test-theme/' + name,
            date_time=(2020, 1, 1, 0, 0, 0),
        )
        archive.writestr(entry, content)
```
