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
./vendor/bin/isolated-composer  [--composer COMPOSER] [--source SOURCE] [--package PACKAGE] [--namespace NAMESPACE] [--config CONFIG] [--vendor-dir vendor] <dest>
```

### Arguments
| Argument    | Description                                                  |
| ----------- | ------------------------------------------------------------ |
| `composer`  | The path (full or relative) to the composer file containing all the package dependencies you want to renamespace.  You must specify this argument OR the `source` argument. |
| `source`    | The path (full or relative) to a directory containing a composer file and an existing vendor directory.  When using `source` the vendor directory must already exist (`composer update` must already have been run).  You must specify this argument OR the `composer` argument. |
| `package`   | The prefix to append to package names, for example specifying `--package mcloud` will turn `nesbot/carbon` into `mcloud-nesbot/carbon`. Default is `mcloud`. |
| `namespace` | The prefix to append to namespaces, for example specifying `--namespace MediaCloud\Vendor` will transform `namespace Aws;` into `namespace MediaCloud\Vendor\Aws;`. Default is `MediaClound\Vendor`. |
| `config`    | An optional PHP configuration file for inserting filters into the namespacing process. |
| `no-dev`    | Skip installing packages listed in require-dev. The autoloader generation skips the autoload-dev rules. |
| `vendor-dir`  | Name of folder where renamspaced packages will be stored (default: vendor) |
| `quiet` | Do not output any message (not even the command result messages) |
| `<dest>`    | The destination directory.  Namespacer will create a directory named `vendor` inside that directory, removing it first if it already exists. |

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
