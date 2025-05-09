# Export Ignore Check

A command-line tool that helps you identify files and directories that should be excluded from your Composer package distribution using `.gitattributes` export-ignore.

## Why?

When you publish a package to Packagist, Composer creates a distribution archive using `git archive`. Files and directories that are not needed in production (like tests, documentation, CI configs) should be excluded using `.gitattributes` export-ignore to:

- Reduce package size
- Speed up installation
- Improve CI/CD pipeline performance
- Keep production packages clean

This tool helps you identify what should be excluded.

## Installation

```bash
composer require savinmikhail/export-ignore-check
```

## Usage

### Check Current Project

To check your current project (recommended during development):

```bash
bin/export-ignore check
```

This will:
1. Create a git archive of your project (simulating Packagist's distribution)
2. Scan for files that should be excluded
3. Show you what to add to your `.gitattributes` file

### Check Any Package

To check any package from Packagist:

```bash
bin/export-ignore check vendor/package
```

For example:
```bash
bin/export-ignore check symfony/console
```

### Output Options

#### JSON Output

Add `--json` flag to get machine-readable output:

```bash
bin/export-ignore check --json
```

#### Custom Config

By default, the tool uses a predefined set of patterns to check. You can provide your own config file:

```bash
bin/export-ignore check --config=/path/to/config.php
# or using short option
bin/export-ignore check -c /path/to/config.php
```

The config file should be a PHP file that returns an array of patterns to check:

```php
<?php

return [
    'tests/',
    '.gitignore',
    // ... your patterns
];
```

## Example Output

```
Directories that should be excluded using export-ignore:
  • `tests/`
  • `docs/`
  • `.github/`

Files that should be excluded using export-ignore:
  • `.gitignore`
  • `.editorconfig`
  • `phpunit.xml.dist`
  • `README.md`

To fix this, add the following lines to your `.gitattributes` file:
  tests/	export-ignore
  docs/	export-ignore
  .github/	export-ignore
  .gitignore	export-ignore
  .editorconfig	export-ignore
  phpunit.xml.dist	export-ignore
  README.md	export-ignore

🌿 Your package size could be reduced by approximately 2.5 MB!
🚀 This improves installation time, reduces archive size, and helps CI/CD pipelines.
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
