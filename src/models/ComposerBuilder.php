<?php
/**
 * Created by: Andrey Polyakov (andrey@polyakov.im)
 */

namespace Glook\IsolatedComposer\models;

use Glook\IsolatedComposer\components\BaseBuilder;

class ComposerBuilder extends BaseBuilder
{
	/**
	 * @example --no-dev
	 * @var string
	 */
	protected $composerOptions = '';
	/**
	 * @var string
	 */
	protected $packagePrefix;
	/**
	 * @var array
	 */
	protected $composerConfig = [];

	/**
	 * @var Project
	 */
	protected $project;

	protected $packagePath;

	/**
	 * @var PackageCollection
	 */
	protected $packageCollection;

	public function init(): void
	{
		$this->packagePath = $this->getPackagePath();
	}

	protected function getPackagePath()
	{
		$collectionPath = $this->packageCollection->getOutputPath();
		$inputPath = $this->getInputPath();
		return str_replace($inputPath, '', $collectionPath);
	}

	/**
	 * @return Package[]
	 * @throws \Exception
	 */
	protected function getPackages(): array
	{
		return $this->project->getPackages();
	}

	/**
	 * @inheritDoc
	 */
	public function build(): void
	{
		$packagePrefix = $this->packagePrefix;
		$composerFile = $this->getWorkingDir() . 'composer.json';
		$packages = $this->getPackages();

		foreach ($packages as $package) {
			$this->addRepository($package);
		}

		if (isset($this->composerConfig['require'])) {
			foreach ($this->composerConfig['require'] as $packageName => $version) {
				if (strpos($packageName, $packagePrefix . '-') === 0) {
					continue;
				}

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
					unset($this->composerConfig['require'][$packageName]);
					continue;
				}

				if (!isset($packages[$packageName])) {
					continue;
				}

				$package = $packages[$packageName];
				unset($this->composerConfig['require'][$packageName]);
				$this->composerConfig['require'][$package->getName()] = $package->version;
			}
		}

		file_put_contents($composerFile, json_encode($this->composerConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		shell_exec("cd {$this->getWorkingDir()} && composer update {$this->composerOptions}");
		shell_exec("rm -rf {$this->getWorkingDir()}vendor/bin");
	}

	/**
	 * @param Package $package
	 */
	protected function addRepository(Package $package): void
	{
		$this->composerConfig['repositories'][] = [
			'type' => 'path',
			'url' => '../' . $this->packagePath . $package->getName(),
			'options' => [
				'symlink' => false,
			],
		];
	}
}
