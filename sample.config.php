<?php

return [
	/**
	 * This is a list of packages you don't want to prefix.
	 * Matching packages will not be scanned for namespaces, but will still have code rewritten if it contains namespaces from other non-blacklisted packages.
	 * You can also use wildcards to match packages
	 */
	'blacklist'=>[
		'ext-*',
		'lib-*',
		'php',
		'composer-plugin-api',
	],
	/** These functions are called before a package is processed */
	'beforeBuild' => [
		function (string $package, array $config, string $path, string $namespacePrefix) {
			/**
			 * @var string $package The name of the composer package
			 * @var array $config The parsed composer.json config for the package
			 * @var string $path The full path to the package
			 * @var string $namespacePrefix The namespace prefix
			 */

			return $config; // You should always return the $config after manipulating it
		}
	],

	/** These functions are called after a package is processed */
	'afterBuild' => [
		function (string $package, string $outputPath, string $namespacePrefix) {
			/**
			 * @var string $package The name of the composer package
			 * @var string $outputPath Temporary path to the package
			 * @var string $namespacePrefix The namespace prefix
			 */
		},
	],

	/** These functions are called once the source file has been loaded but before all of the namespace changes are processed. */
	'start' => [
		function (string $source, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
			/**
			 * @var string $source The PHP source file contents
			 * @var string|null $currentNamespace The current namespace of the source file (without new prefix)
			 * @var string $namespacePrefix The new namespace prefix
			 * @var string $package The name of the composer package
			 * @var string $file The complete path to the source file
			 */
			return $source; // You should always return the $source after manipulating it
		},
	],

	/** These functions are called once per namespace being processed before the regex's are run. */
	'before' => [
		function (string $source, string $namespace, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
			/**
			 * @var string $source The PHP source file contents
			 * @var string $namespace The namespace currently being processed
			 * @var string|null $currentNamespace The current namespace of the source file (without new prefix)
			 * @var string $namespacePrefix The new namespace prefix
			 * @var string $package The name of the composer package
			 * @var string $file The complete path to the source file
			 */
			return $source; // You should always return the $source after manipulating it
		}
	],

	/** These functions are called once per namespace being processed after the regex's are run. */
	'after' => [
		function (string $source, string $namespace, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
			/**
			 * @var string $source The PHP source file contents
			 * @var string $namespace The namespace currently being processed
			 * @var string|null $currentNamespace The current namespace of the source file (without new prefix)
			 * @var string $namespacePrefix The new namespace prefix
			 * @var string $package The name of the composer package
			 * @var string $file The complete path to the source file
			 */
			return $source; // You should always return the $source after manipulating it
		}
	],

	/** These functions are called before the changed source file is saved, after all the processing has taken place. */
	'end' => [
		function (string $source, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
			/**
			 * @var string $source The PHP source file contents
			 * @var string|null $currentNamespace The current namespace of the source file (without new prefix)
			 * @var string $namespacePrefix The new namespace prefix
			 * @var string $package The name of the composer package
			 * @var string $file The complete path to the source file
			 */
			return $source; // You should always return the $source after manipulating it
		},
	]
];
