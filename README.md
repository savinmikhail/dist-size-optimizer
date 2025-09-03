# Dist Size Optimizer
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/savinmikhail/dist-size-optimizer/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/savinmikhail/dist-size-optimizer/?branch=main)
[![Code Coverage](https://scrutinizer-ci.com/g/savinmikhail/dist-size-optimizer/badges/coverage.png?b=main)](https://scrutinizer-ci.com/g/savinmikhail/dist-size-optimizer/?branch=main)
![dist-size status](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fsavinmikhail%2Fdist-size-optimizer%2Fmain%2Fdist-size-status.json)

A command-line tool that helps you optimize your package distribution size by automatically managing `.gitattributes` export-ignore rules.

> **Note**: This package was previously known as `export-ignore-check`. The functionality remains the same, but the name better reflects its purpose of optimizing distribution size.

## Why?

When you publish a package to Packagist, Composer creates a distribution archive using `git archive`. Files and directories that are not needed in production (like tests, documentation, CI configs) should be excluded using `.gitattributes` export-ignore to:

* Reduce package size
* Speed up installation
* Improve CI/CD pipeline performance
* Keep production packages clean

This tool helps you identify what should be excluded and can automatically fix your `.gitattributes` file.

## Installation

1. As composer dependency:

   ```bash
   composer require savinmikhail/dist-size-optimizer
   ```

2. Or as standalone phar package:

   ```bash
   box.phar compile
   ./dist-size-optimizer.phar check
   ```

## Dist Size Badge

Show the status of your distribution size with a badge. The badge is green when the package is optimized and red when it needs optimization:

![dist-size optimized](https://img.shields.io/badge/dist--size-optimized-brightgreen)
![dist-size needs optimization](https://img.shields.io/badge/dist--size-needs%20optimization-red)

### Add badge to your project

1. Copy the [dist-size workflow](.github/workflows/dist-size.yml) into your repository.
2. Add the following snippet to your `README.md`, replacing `<USER>` and `<REPO>` with your repository details:

   ```markdown
   ![dist-size status](https://img.shields.io/endpoint?url=https://raw.githubusercontent.com/<USER>/<REPO>/main/dist-size-status.json)
   ```

The workflow updates `dist-size-status.json`, and the badge reflects your repository's current status.

## Usage

### Check Current Project

To check your current project (recommended during development):

```bash
vendor/bin/dist-size-optimizer check
```

This will:

1. Create a git archive of your project (simulating Packagist's distribution)
2. Scan for files that should be excluded
3. Show you what to add to your `.gitattributes` file
4. Automatically append the suggested patterns to your `.gitattributes` file

> **Note**: When checking your current project, the tool uses `git archive HEAD`, which means it only includes committed changes. Make sure to commit your changes before running the check to get accurate results.

### Check Any Package

To check any package from Packagist:

```bash
vendor/bin/dist-size-optimizer check vendor/package
```

For example:

```bash
vendor/bin/dist-size-optimizer check symfony/console
```

### Clean .gitattributes

If your `.gitattributes` file contains stale `export-ignore` entries copied from other projects, you can remove all non-existent paths with:

```bash
vendor/bin/dist-size-optimizer check --clean
```

This command will:

1. Read your existing `.gitattributes` file
2. Remove all lines with `export-ignore` patterns that no longer match any file or directory in the project
3. Overwrite `.gitattributes` with the cleaned content

### Output Options

#### Dry Run

By default, the tool will automatically append suggested patterns to your `.gitattributes` file. Use `--dry-run` to only see what would be added without making any changes:

```bash
vendor/bin/dist-size-optimizer check --dry-run
```

#### JSON Output

Add `--json` flag to get machine-readable output:

```bash
vendor/bin/dist-size-optimizer check --json
```

#### Custom Config

By default, the tool uses a predefined set of patterns to check. You can provide your own config file:

```bash
vendor/bin/dist-size-optimizer check --config=/path/to/config.php
# or using short option
vendor/bin/dist-size-optimizer check -c /path/to/config.php
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

#### Custom Working Directory

By default, the tool uses a system temporary directory for working directory. You can provide your own working directory path:

```bash
vendor/bin/dist-size-optimizer check --workdir=/path/to/workdir
# or using short option
vendor/bin/dist-size-optimizer check -w /path/to/workdir
```

## Example Output

```
Directories that should be excluded using export-ignore:
  • `/tests/`
  • `/docs/`
  • `/.github/`

Files that should be excluded using export-ignore:
  • `/.gitignore`
  • `/.editorconfig`
  • `/phpunit.xml.dist`
  • `/README.md`

To fix this, add the following lines to your `.gitattributes` file:
  /tests/    export-ignore
  /docs/     export-ignore
  /.github/ export-ignore
  /.gitignore export-ignore
  /.editorconfig export-ignore
  /phpunit.xml.dist export-ignore
  /README.md export-ignore

🌿 Your package size could be reduced by approximately 2.5 MB!
🚀 This improves installation time, reduces archive size, and helps CI/CD pipelines.
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
