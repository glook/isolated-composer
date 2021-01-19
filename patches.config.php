<?php

use Glook\IsolatedComposer\helpers\FileHelper;

return [
	/**
	 * This is a list of packages you don't want to prefix.
	 * Matching packages will not be scanned for namespaces, but will still have code rewritten if it contains namespaces from other non-blacklisted packages.
	 */
	'blacklist' => [
		'ext-*',
		'lib-*',
		'php',
		'composer-plugin-api',
		'symfony/polyfill-*',
	],
	/** These functions are called after a package is processed */
	'afterBuild' => [
		function (string $package, string $outputPath, string $namespacePrefix) {
			/**
			 * @var string $package The name of the composer package
			 * @var string $outputPath Temporary path to the package
			 * @var string $namespacePrefix The namespace prefix
			 */
			if ($package === 'php-di/php-di') {
				// Patching php-di annotations
				$annotationPath = '/DI/Annotation';
				$oldPath = $outputPath . 'src' . $annotationPath;
				$newPath = FileHelper::normalizePath($outputPath . 'src/' . $namespacePrefix . $annotationPath);
				FileHelper::createDirectory($newPath);
				FileHelper::copyDirectory($oldPath, $newPath);
			}
		},
	],
];
