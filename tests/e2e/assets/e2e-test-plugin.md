# Plugin upload fixtures

The two ZIP files contain only `e2e-test-plugin/e2e-test-plugin.php`:

```php
<?php
/**
 * Plugin Name: E2E Test Plugin
 * Version: 1.0
 * Update URI: false
 */
```

The second archive uses version `2.0`. Neither plugin executes code. The update URI prevents WordPress.org update matching.

To reproduce both archives from the repository root with Python 3:

```python
from pathlib import Path
from zipfile import ZipFile, ZipInfo

fixtures = Path('tests/e2e/assets')
for version in ['1.0', '2.0']:
    with ZipFile(fixtures / f'e2e-test-plugin-{version}.zip', 'w') as archive:
        entry = ZipInfo(
            'e2e-test-plugin/e2e-test-plugin.php',
            date_time=(2020, 1, 1, 0, 0, 0),
        )
        archive.writestr(
            entry,
            '<?php\n/**\n * Plugin Name: E2E Test Plugin\n'
            f' * Version: {version}\n * Update URI: false\n */\n',
        )
```
