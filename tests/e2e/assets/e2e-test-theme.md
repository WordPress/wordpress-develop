# Theme fixtures

`e2e-test-theme.zip` contains a minimal classic theme with three files. Its template executes no code. The screenshot reuses the existing PHPUnit image fixture. The update URI prevents WordPress.org update matching.

`e2e-test-theme-2.0.zip` contains the same theme with version `2.0`, for replacing an installed theme with an uploaded version.

To reproduce both archives from the repository root with Python 3:

```python
from pathlib import Path
from zipfile import ZipFile, ZipInfo

archives = {
    '1.0': 'tests/e2e/assets/e2e-test-theme.zip',
    '2.0': 'tests/e2e/assets/e2e-test-theme-2.0.zip',
}
for version, path in archives.items():
    files = {
        'style.css': f'/*\nTheme Name: E2E Test Theme\nVersion: {version}\nUpdate URI: false\n*/\n',
        'index.php': '<?php\n// E2E theme fixture.\n',
        'screenshot.jpg': Path('tests/phpunit/data/images/test-image.jpg').read_bytes(),
    }
    with ZipFile(path, 'w') as archive:
        for name, content in files.items():
            entry = ZipInfo(
                'e2e-test-theme/' + name,
                date_time=(2020, 1, 1, 0, 0, 0),
            )
            archive.writestr(entry, content)
```
