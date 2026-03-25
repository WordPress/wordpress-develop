# Code Quality TODO

> This file tracks code scanning and validation tasks

## PHP Syntax Check

When PHP is available, run:

```bash
# Check all our new PHP files for syntax errors
find wp-includes/php-ai-client/src/Providers/AiConnectorPro -name "*.php" -exec php -l {} \;
find wp-includes/ai-connector-pro.php -exec php -l {} \;
```

## WordPress Coding Standards

```bash
# Install WordPress coding standards if needed
composer require wp-coding-standards/wpcs:^3.0

# Run PHPCS on our files
./vendor/bin/phpcs --standard=WordPress wp-includes/ai-connector-pro.php
./vendor/bin/phpcs --standard=WordPress wp-includes/php-ai-client/src/Providers/AiConnectorPro/
```

## Tasks

- [ ] PHP syntax check on ai-connector-pro.php
- [ ] PHP syntax check on AiConnectorPro providers
- [ ] Run PHPCS on all new files
- [ ] Check for deprecated function usage
- [ ] Verify hook priorities are correct
- [ ] Check nonce verification in all form handlers

## Manual Code Review Checklist

- [ ] All inputs sanitized
- [ ] All outputs escaped
- [ ] Nonce verification on forms
- [ ] Capability checks on admin actions
- [ ] Proper error handling
- [ ] PHPDoc blocks complete

---

*Updated: 2026-03-25*