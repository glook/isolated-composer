<?php
/**
 * Created by: Andrey Polyakov (andrey@polyakov.im)
 */

namespace Glook\IsolatedComposer\models;

use Exception;
use Glook\IsolatedComposer\components\BaseBuilder;
use Glook\IsolatedComposer\helpers\FileHelper;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Project
 * @package Glook\IsolatedComposer\models
 */
class Project extends BaseBuilder
{
	/**
	 * @var OutputInterface
	 */
	public $consoleOutput;

	/**
	 * @example --no-dev
	 * @var string
	 */
	protected $composerOptions = '';

	/**
	 * Name of folder where composer packages will be stored
	 * @var string
	 */
	protected $vendorDir;

	/**
	 * @var string
	 */
	public $packagePrefix;

	/**
	 * @see Project::setNamespacePrefix()
	 * @see Project::getNamespacePrefix()
	 * @var string
	 */
	protected $_namespacePrefix;

	/**
	 * @var PackageCollection
	 */
	protected $packageCollection;
	/**
	 * User configuration
	 * @var Configuration
	 */
	public $config;
	/**
	 * @var array
	 */
	protected $composerConfig = [];
	/**
	 * @see Project::setConfigPath()
	 * @see Project::getConfigPath()
	 * @var mixed
	 */
	private $_configPath;

	/**
	 * @param string $value
	 */
	protected function setNamespacePrefix(string $value): void
	{
		$this->_namespacePrefix = rtrim($value, "\\") . "\\";
	}

	/**
	 * @return string
	 */
	public function getNamespacePrefix(): string
	{
		return $this->_namespacePrefix;
	}

	/**
	 * @param $value
	 */
	protected function setConfigPath($value): void
	{
		$this->_configPath = $value;
		$this->config = new Configuration($value);
	}

	/**
	 * @return string|null
	 */
	public function getConfigPath(): ?string
	{
		return $this->_configPath;
	}

	/**
	 * @return Package[]
	 * @throws Exception
	 */
	public function getPackages(): array
	{
		return $this->packageCollection->getPackages();
	}

	/**
	 * @throws Exception
	 */
	public function init(): void
	{
		$inputPath = $this->getInputPath();
		$composerFile = $inputPath . 'composer.json';
		if (!file_exists($composerFile)) {
			throw new Exception('Composer file missing.');
		}

		$this->composerConfig = json_decode(file_get_contents($composerFile), true);
		$this->packageCollection = new PackageCollection([
			'inputPath' => $inputPath,
			'workingDir' => $this->getWorkingDir() . 'library',
			'consoleOutput' => $this->consoleOutput,
			'project' => $this,
		]);
	}

	/**
	 * @return array[]
	 */
	public function getOutputData(): array
	{
		return [
			['Package Prefix', $this->packagePrefix],
			['Namespace Prefix', $this->getNamespacePrefix()],
			['Source', $this->getInputPath()],
			['Destination', $this->getOutputPath()],
			['Config', $this->getConfigPath()],
		];
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function getCollectionOutputData(): array
	{
		return $this->packageCollection->getOutputData();
	}

	public function build(): void
	{
		// build dependencies
		$packageCollection = $this->packageCollection;
		$packageCollection->build();
		$namespaceList = $packageCollection->getNamespaceList();
		// build project source code
		$projectBuilder = $this->getProjectBuilder();
		$projectBuilder->renameSpace($namespaceList);
		$projectBuilder->build();
		// build composer file and execute "composer update"
		$composerBuilder = $this->getComposerBuilder();
		$composerBuilder->build();

		FileHelper::copyDirectory($composerBuilder->getOutputPath(), $this->getOutputPath() . $this->vendorDir);
		FileHelper::copyDirectory($projectBuilder->getOutputPath(), $this->getOutputPath());
	}

	/**
	 * @return ComposerBuilder
	 */
	protected function getComposerBuilder(): ComposerBuilder
	{
		return new ComposerBuilder([
			'inputPath' => $this->getWorkingDir(),
			'workingDir' => $this->getWorkingDir() . 'build',
			'outputPath' => $this->getWorkingDir() . 'build/vendor',
			'composerOptions' => $this->composerOptions,
			'composerConfig' => $this->composerConfig,
			'packagePrefix' => $this->packagePrefix,
			'project' => $this,
			'packageCollection' => $this->packageCollection,
		]);
	}

	/**
	 * @return ProjectCodebase
	 */
	protected function getProjectBuilder(): ProjectCodebase
	{
		return new ProjectCodebase(
			[
				'inputPath' => $this->getInputPath(),
				'workingDir' => $this->getWorkingDir(),
				'name' => $this->composerConfig['name'],
				'version' => $this->composerConfig['version'],
				'project' => $this,
				'composerConfig' => $this->composerConfig,
			]
		);
	}

}
