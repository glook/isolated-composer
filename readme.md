# Isolated-composer
The library based on  [Interfacelab/namespacer](https://github.com/Interfacelab/namespacer), which adds prefixes to the packages used by the composer, making them isolated. 

Isolated-composer does the same, but modifies the application source code by adding the required namespaces. When using Isolated-composer, you will need to run `./Vendor/bin/isolated-composer` with arguments (see below), which will patch your source code and dependencies (for example, when using CI).

## Installation
You can install this globally, but I think you'd be better off using it as the basis of a project via composer.

```bash
composer require glook/isolated-composer
```

## Usage
Once installed:

```bash
./vendor/bin/isolated-composer [options] [--composer COMPOSER] [--source SOURCE] [--package PACKAGE] [--namespace NAMESPACE] [--config CONFIG] [--vendor-dir vendor] <dest>
```

# Usage with docker

You use isolated-composer without installing it as composer dependency. Just use this image  [glook/php-isolated-composer](https://hub.docker.com/r/glook/php-isolated-composer).

```bash
docker run --rm -it -v ${PWD}:/app \
    docker.io/glook/php-isolated-composer:latest --source /app \
    --package sample-prefix \
    --namespace SampleApp\\Vendor\\ \
    --no-dev \
    --vendor-dir vendor \
    /app/output
```

### Arguments

#### Tool options

| Option | Description |
| ------ | ----------- |
| `--composer COMPOSER` | Path to a composer.json to renamespace (mutually exclusive with `--source`). |
| `--source SOURCE` | Path to a directory with an existing vendor dir (mutually exclusive with `--composer`). |
| `--package PACKAGE` | Prefix to add to package names, e.g. `my-prefix`. |
| `--namespace NAMESPACE` | Prefix to add to PHP namespaces, e.g. `MyApp\\Vendor\\`. |
| `--config CONFIG` | Path to an optional PHP config file with filter hooks. |
| `--vendor-dir NAME` | Folder name for renamespaced packages (default: `vendor`). |
| `<dest>` | Destination directory for the output. |

#### Output / interaction (Symfony Console built-ins, forwarded to `composer update`)

| Option | Description |
| ------ | ----------- |
| `-q` / `--quiet` | Do not output any message. |
| `-v\|-vv\|-vvv` / `--verbose` | Increase verbosity of messages (`-vvv` = debug). |
| `-n` / `--no-interaction` | Do not ask any interactive question. |

#### Composer passthrough — boolean flags

| Option | Description |
| ------ | ----------- |
| `--dry-run` | Outputs the operations but will not execute anything (implies `--verbose`). |
| `--dev` | Enables installation of require-dev packages. |
| `--no-dev` | Skip installing packages listed in require-dev. |
| `--no-install` | Skip the install step after updating the lock file. |
| `--no-audit` | Skip the audit step after updating the lock file. |
| `--no-security-blocking` | Audit prints warnings but does not block. |
| `--lock` | Only updates the lock file hash to suppress the out-of-date warning. |
| `--no-autoloader` | Skips autoloader generation. |
| `--no-progress` | Do not output download progress. |
| `--no-plugins` | Disables composer plugins. |
| `--no-scripts` | Skips execution of scripts defined in composer.json. |
| `-w` / `--with-dependencies` | Add dependencies of whitelisted packages to the whitelist. |
| `-W` / `--with-all-dependencies` | Add all dependencies of whitelisted packages to the whitelist. |
| `-o` / `--optimize-autoloader` | Optimize autoloader during autoloader dump. |
| `-a` / `--classmap-authoritative` | Autoload classes from the classmap only. |
| `--apcu-autoloader` | Use APCu to cache found/not-found classes. |
| `--ignore-platform-reqs` | Ignore all platform requirements (php & ext- packages). |
| `--prefer-stable` | Prefer stable versions of dependencies. |
| `--prefer-lowest` | Prefer lowest versions of dependencies. |
| `-m` / `--minimal-changes` | During a partial update, only change versions in require/require-dev. |
| `--patch-only` | Only allow patch-level updates during a partial update. |
| `--interactive` | Interactive interface with autocompletion to select packages to update. |
| `--root-reqs` | Restricts the update to first-degree dependencies. |

#### Composer passthrough — value options

| Option | Description |
| ------ | ----------- |
| `--prefer-install=SOURCE` | Force installation from `source`, `dist`, or `auto`. |
| `--audit-format=FORMAT` | Audit output format: `table`, `plain`, `json`, or `summary`. |
| `--with=CONSTRAINT` | Temporary version constraint, e.g. `foo/bar:1.0.0`. |
| `--apcu-autoloader-prefix=PREFIX` | Custom prefix for the APCu autoloader cache. |
| `--bump-after-update=MODE` | Run bump after update: `dev`, `no-dev`, or `true`. |
| `--ignore-platform-req=PACKAGE` | Ignore a specific platform requirement (repeatable). |

For example, you might run it:

```bash
./vendor/bin/namespacer --composer sample.composer.json --config sample.config.php --package mypackage --namespace MyNamespace\Vendor build/
```

In this example, we're pointing to a `composer.json` file containing the packages we want to re-namespace and to a 
config file that contains filters that will apply more manual patches during the re-namespace process.  The output 
of the processing will be put into the `build/` folder.

### Filtering (Patching in PHP-Scoper parlance)
You can see some example configurations in `vendor/glook/isolated-composer/sample.config.php` and 
`vendor/glook/isolated-composer/patches.config.php` that will demonstrate how to insert your own code into the namespacing
process to catch special cases. 

## Reporting Bugs
If you run into issues, please open a ticket and attach the composer.json you were trying to process with a clear
description of the problem.
