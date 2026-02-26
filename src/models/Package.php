<?php
/**
 * Created by: Andrey Polyakov (andrey@polyakov.im)
 */

namespace Glook\IsolatedComposer\models;

use Exception;
use Glook\IsolatedComposer\components\BaseBuilder;
use Glook\IsolatedComposer\helpers\FileHelper;
use Glook\IsolatedComposer\helpers\StringHelper;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Package
 * @package Glook\Namespacer\Models
 */
class Package extends BaseBuilder
{
	/** @var string */
	protected $name;

	/** @var string */
	public $version;

	/**
	 * @var Project
	 */
	protected $project;

	/**
	 * @see Package::getNamespaces()
	 * @var array
	 */
	private $_namespaces;

	/**
	 * @see Package::getSourceFiles()
	 * @var array
	 */
	private $_sourceFiles;

	/**
	 * @var bool
	 */
	private $processPackage = true;
	/**
	 * @var array
	 */
	private $_composerConfig;

	public function init(): void
	{
		if ($this->getIsBlacklistedByName($this->name)) {
			$this->disablePackageProcessing();
		}
	}

	/**
	 * Disable finding and replacing namespaces of current package
	 */
	protected function disablePackageProcessing(): void
	{
		$this->processPackage = false;
	}

	/**
	 * @return string
	 */
	protected function getNamespacePrefix(): string
	{
		return $this->project->getNamespacePrefix();
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function getSourceFiles(): array
	{
		if (!$this->_sourceFiles) {
			$path = StringHelper::trailingslashit($this->getOutputPath());
			$this->_sourceFiles = $this->findFilesInPath($path);
		}

		return $this->_sourceFiles;
	}

	/**
	 * @inheritDoc
	 */
	public function getOutputPath(): string
	{
		return StringHelper::trailingslashit(parent::getOutputPath() . $this->getName());
	}

	/**
	 * @return Configuration
	 */
	protected function getConfig(): Configuration
	{
		return $this->project->config;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function getNamespaces(): array
	{
		if (!$this->_namespaces) {
			if ($this->processPackage) {
				$namespaceRegex = '/^\s*namespace\s+([^\s;]+)/m';
				$idiotNamespaceRegex = '/^\s*\<\?php\s+namespace\s+([^\s;]+)/m';
				$namespaceList = [];
				foreach ($this->getSourceFiles() as $file) {
					$namespaceRegexmatches = [];
					preg_match_all($namespaceRegex, file_get_contents($file), $namespaceRegexmatches, PREG_SET_ORDER, 0);
					if (count($namespaceRegexmatches) > 0) {
						foreach ($namespaceRegexmatches as $match) {
							if ($match[1] === "'.__NAMESPACE__.'") {
								continue;
							}

							if (!in_array($match[1], $namespaceList)) {
								$namespaceList[] = $match[1];
							}
						}
					} else {
						preg_match_all($idiotNamespaceRegex, file_get_contents($file), $idiotNamespaceRegexMatches, PREG_SET_ORDER, 0);
						if (count($idiotNamespaceRegexMatches) > 0) {
							foreach ($idiotNamespaceRegexMatches as $match) {
								if ($match[1] === "'.__NAMESPACE__.'") {
									continue;
								}

								if (!in_array($match[1], $namespaceList)) {
									$namespaceList[] = $match[1];
								}
							}
						}
					}
				}
			} else {
				$namespaceList = [];
			}

			$this->_namespaces = $namespaceList;
		}

		return $this->_namespaces;
	}

	/**
	 * @param string $packageName
	 * @return bool
	 */
	protected function getIsBlacklistedByName(string $packageName): bool
	{
		$blacklistedPackages = $this->getConfig()->getBlacklistedPackages();
		$foundPackages = array_filter($blacklistedPackages, function ($packageWildcard) use ($packageName) {
			return StringHelper::matchWildcard($packageWildcard, $packageName);
		});
		return !empty($foundPackages);
	}

	/**
	 * @param string $path
	 * @return array
	 * @throws Exception
	 */
	protected function findFilesInPath(string $path): array
	{
		return FileHelper::findFiles($path, [
			'only' => [
				'*.php',
				'*.inc',
			],
		]);
	}

	/**
	 * Rename namespaces in package source code
	 * @param array $namespaces
	 * @param OutputInterface|null $output
	 * @param ProgressBar|null $progressBar
	 * @throws Exception
	 */
	public function renameSpace(array $namespaces, OutputInterface $output = null, ProgressBar $progressBar = null): void
	{
		$outputPath = $this->getOutputPath();
		$namespacePrefix = $this->getNamespacePrefix();
		$namespacerConfig = $this->getConfig();
		$namespacePrefixString = str_replace("\\", "\\\\", $namespacePrefix);
		$namespacePrefixStringRegex = str_replace("\\", "\\\\", $namespacePrefixString);

		foreach ($this->getSourceFiles() as $file) {
			if ($output !== null && !empty($outputPath)) {
				$relativeFile = str_replace($outputPath, '', $file);
				$output->overwrite("Re-namespacing package {$this->name} ... $relativeFile");
			}

			if ($progressBar !== null) {
				$progressBar->advance();
			}

			$source = file_get_contents($file);

			$currentNamespace = null;
			preg_match('#^\s*namespace\s+([^;]+)#m', $source, $matches);
			if (count($matches) > 1) {
				$currentNamespace = $matches[1];
			} else {
				preg_match('#^\s*\<\?php\s+namespace\s+([^;]+)#m', $source, $matches);
				if (count($matches) > 1) {
					$currentNamespace = $matches[1];
				}
			}

			$source = $namespacerConfig->start($source, $currentNamespace, $namespacePrefix, $this->name, $file);

			$currentNamespaceRegexSafe = str_replace("\\", "\\\\", $currentNamespace);

			if ($this->processPackage) {
				$source = preg_replace("#^\s*namespace\s+$currentNamespaceRegexSafe\s*;#m", "\nnamespace $namespacePrefix$currentNamespace;", $source, -1);
				$source = preg_replace("#^\s*\<\?php\s+namespace\s+$currentNamespaceRegexSafe\s*;#m", "<?php\n\nnamespace $namespacePrefix$currentNamespace;", $source, -1);
			}

			foreach ($namespaces as $namespace) {
				$source = $namespacerConfig->before($source, $namespace, $currentNamespace, $namespacePrefix, $this->name, $file);

				$namespace .= "\\";
				$stringNamespace = str_replace("\\", "\\\\", $namespace);
				$stringNamespaceRegex = str_replace("\\", "\\\\", $stringNamespace);

				$namespaceTrimmed = rtrim($namespace, "\\");
				$stringNamespaceTrimmed = rtrim(str_replace("\\", "\\\\", $namespace), "\\");

				$source = preg_replace("#^\\s*use\\s+$stringNamespace#m", "use $namespacePrefix$namespace", $source, -1);
				$source = preg_replace("#^\\s*use\\s+$stringNamespaceTrimmed;#m", "use $namespacePrefix$namespaceTrimmed;", $source, -1);
				$source = preg_replace("#\\s+$stringNamespaceRegex#m", " $namespacePrefixStringRegex$stringNamespace\\", $source, -1);
				$source = preg_replace("#\\\"$stringNamespaceRegex#m", "\"$namespacePrefixStringRegex$stringNamespace\\", $source, -1);
				$source = preg_replace("#\\'$stringNamespaceRegex#m", "'$namespacePrefixStringRegex$stringNamespace\\", $source, -1);
				$source = preg_replace("#\\s+\\\\\\\\$stringNamespaceRegex#m", " \\\\$namespacePrefixStringRegex$stringNamespace\\", $source, -1);
				$source = preg_replace("#\\\"\\\\\\\\$stringNamespaceRegex#m", "\"\\\\$namespacePrefixStringRegex$stringNamespace\\", $source, -1);
				$source = preg_replace("#\\'\\\\\\\\$stringNamespaceRegex#m", "'\\\\$namespacePrefixStringRegex$stringNamespace\\", $source, -1);
				$source = preg_replace("#\\s+$stringNamespace#m", " $namespacePrefix$namespace", $source, -1);
				$source = preg_replace("#\\\"$stringNamespace#m", "\"$namespacePrefix$namespace", $source, -1);
				$source = preg_replace("#\\'$stringNamespace#m", "'$namespacePrefix$namespace", $source, -1);
				$source = preg_replace("#\\'\\\\$stringNamespace#m", "'\\$namespacePrefix$namespace", $source, -1);
				$source = preg_replace("#\\(\s*\\\\$stringNamespace#m", "(\\$namespacePrefix$namespace", $source, -1);
				$source = preg_replace("#\\s+\\\\$stringNamespace#m", " \\$namespacePrefix$namespace", $source, -1);
				$source = preg_replace("#\\s+$namespacePrefixString(.*)\s*\(#m", ' \\NAMESPACEPLACEHOLDER$1(', $source, -1);
				$source = str_replace('NAMESPACEPLACEHOLDER', $namespacePrefix, $source);
				$source = $namespacerConfig->after($source, $namespace, $currentNamespace, $namespacePrefix, $this->name, $file);
			}

			$source = $namespacerConfig->end($source, $currentNamespace, $namespacePrefix, $this->name, $file);

			file_put_contents($file, $source);
		}
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		$prefix = $this->project->packagePrefix;
		$composerConfig = $this->getComposerConfig();

		return $this->processPackage ? $prefix . '-' . $composerConfig['name'] : $composerConfig['name'];
	}

	/**
	 * @return array
	 */
	protected function getComposerConfig(): array
	{
		if (!$this->_composerConfig) {

			$composerFile = $this->getInputPath() . 'composer.json';

			if (!file_exists($composerFile)) {
				throw new RuntimeException("Missing composer.json for package {$this->name}.");
			}

			$this->_composerConfig = json_decode(file_get_contents($composerFile), true);
		}

		return $this->_composerConfig;

	}

	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function build(): void
	{
		$packages = $this->project->getPackages();
		$prefix = $this->project->packagePrefix;
		$namespacePrefix = $this->getNamespacePrefix();
		$outputPath = $this->getOutputPath();
		$composerConfig = $this->getConfig()->beforeBuild($this->name, $this->getComposerConfig(), $this->getInputPath(), $namespacePrefix);

		$composerConfig['name'] = $this->getName();
		$composerConfig['version'] = $this->version;
		// processing dependencies
		if (isset($composerConfig['require'])) {
			foreach ($composerConfig['require'] as $packageName => $version) {
				if (strpos($packageName, 'ext-') === 0) {
					continue;
				}

				if (strpos($packageName, 'lib-') === 0) {
					continue;
				}

				if ($packageName === 'php') {
					continue;
				}

				if ($packageName === 'composer-plugin-api') {
					unset($composerConfig['require'][$packageName]);
					continue;
				}

				if ($packageName === 'kylekatarnls/update-helper') {
					unset($composerConfig['require'][$packageName]);
					continue;
				}

				if (!isset($packages[$packageName])) {
					throw new Exception("Cannot find related package $packageName for {$this->name}.");
				}
				$package = $packages[$packageName];
				unset($composerConfig['require'][$packageName]);

				$newPackageName = !$this->getIsBlacklistedByName($packageName) ? $prefix . '-' . $packageName : $packageName;

				$composerConfig['require'][$newPackageName] = $package->version;
			}
		}

		unset($composerConfig['extra']['branch-alias']['dev-master']);
		FileHelper::createDirectory($outputPath, 0755, true);
		FileHelper::copyDirectory($this->getInputPath(),$outputPath);
		$this->getConfig()->afterBuild($this->name, $this->getOutputPath(), $namespacePrefix);

		$composerConfig = $this->processAutoload($composerConfig);
		file_put_contents($outputPath . '/composer.json', json_encode($composerConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * @param array $composerConfig
	 * @return array
	 * @throws Exception
	 */
	protected function processAutoload(array $composerConfig): array
	{
		$namespacePrefix = $this->processPackage ? $this->getNamespacePrefix() : '';
		if (isset($composerConfig['autoload'])) {

			if (isset($composerConfig['autoload']['psr-0'])) {
				foreach ($composerConfig['autoload']['psr-0'] as $namespace => $directory) {
					$packagePath = $this->getOutputPath();
					$sourcePath = ($packagePath . $directory);
					$tempPsr0Path = $packagePath . 'tmp';
					FileHelper::createDirectory($tempPsr0Path, 0755, true);
					shell_exec("mv {$sourcePath}* $tempPsr0Path");
					$namespacePath = ltrim(str_replace("\\", '/', $namespacePrefix), '\\');
					$newPsr0Path = StringHelper::trailingslashit(StringHelper::trailingslashit($packagePath . $directory) . $namespacePath);
					FileHelper::createDirectory($newPsr0Path, 0755, true);
					shell_exec("mv {$tempPsr0Path}/* $newPsr0Path");
					@rmdir($tempPsr0Path);
					$composerConfig['autoload']['psr-0'][$namespacePrefix . $namespace] = $directory;
					unset($composerConfig['autoload']['psr-0'][$namespace]);
				}
			}

			if (isset($composerConfig['autoload']['psr-4'])) {
				foreach ($composerConfig['autoload']['psr-4'] as $namespace => $directory) {
					unset($composerConfig['autoload']['psr-4'][$namespace]);
					$composerConfig['autoload']['psr-4'][$namespacePrefix . $namespace] = $directory;
				}
			}
		}
		return $composerConfig;
	}

}
