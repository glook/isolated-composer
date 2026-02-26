<?php
/**
 * Created by: Andrey Polyakov (andrey@polyakov.im)
 */

namespace Glook\IsolatedComposer\models;

use Exception;
use Glook\IsolatedComposer\components\BaseBuilder;
use Glook\IsolatedComposer\helpers\FileHelper;
use Glook\IsolatedComposer\helpers\StringHelper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

class PackageCollection extends BaseBuilder
{
    public const OUTPUT_DIR_NAME = 'library/';

    /**
     * @var Project
     */
    protected $project;
    /**
     * @var OutputInterface
     */
    public $consoleOutput;

    /**
     * @var array
     */
    protected $_lockConfig;

    /**
     * @var array
     */
    protected $_tableData;

    /**
     * @var array
     */
    protected $_packages;

    /**
     * @var array
     */
    private $_namespaces;

    /**
     * @var mixed
     */
    private $_sourceFiles;

    public function init(): void
    {
        $lockConfig = $this->getLockConfig();
        if (!isset($lockConfig['packages'])) {
            throw new Exception('No installed packages.');
        }
        FileHelper::createDirectory($this->getOutputPath(), 0755, true);
    }

    /**
     * @inheritDoc
     */
    public function getOutputPath(): string
    {
        return $this->getWorkingDir();
    }

    protected function getLockConfig(): array
    {
        if (!$this->_lockConfig) {
            $inputPath = $this->getInputPath();
            $composerLock = $inputPath . 'composer.lock';
            if (file_exists($composerLock)) {
                $this->_lockConfig = json_decode(file_get_contents($composerLock), true);
            } else {
                throw new Exception('Composer lock file missing.');
            }
        }
        return $this->_lockConfig;
    }

    /**
     * @return Package[]
     * @throws Exception
     */
    public function getPackages(): array
    {
        if (!$this->_packages) {
            $lockConfig = $this->getLockConfig();
            if (!isset($lockConfig['packages'])) {
                throw new Exception('No installed packages.');
            }
            $inputPath = $this->getInputPath();
            $packageList = [];

            foreach ($lockConfig['packages'] as $package) {
                $packageName = $package['name'];
                $packageList[$packageName] = new Package([
                    'inputPath' => StringHelper::trailingslashit($inputPath . 'vendor/' . $packageName),
                    'outputPath' => $this->getOutputPath(),
                    'name' => $packageName,
                    'version' => $package['version'],
                    'project' => $this->project,
                ]);

            }
            $this->_packages = $packageList;
        }
        return $this->_packages;
    }

    /**
     * Return list of names and versions from required packages
     * @return array
     * @throws Exception
     */
    public function getOutputData(): array
    {
        if (!$this->_tableData) {
            $lockConfig = $this->getLockConfig();
            if (!isset($lockConfig['packages'])) {
                throw new Exception('No installed packages.');
            }
            $tableData = [];
            foreach ($lockConfig['packages'] as $package) {
                $tableData[] = [$package['name'], $package['version']];
            }
            $this->_tableData = $tableData;
        }
        return $this->_tableData;
    }

    /**
     * @return string[]
     * @throws Exception
     */
    public function getNamespaceList(): array
    {
        if (!$this->_namespaces) {
            $output = $this->consoleOutput;
            /** @var ConsoleSectionOutput $packageSection */
            $packageSection = $output->section();
            $packageSection->writeln('Processing packages ...');
            /** @var ConsoleSectionOutput $packageProgressSection */
            $packageProgressSection = $output->section();
            $packageProgress = new ProgressBar($packageProgressSection, count($this->getPackages()));
            $packageProgress->setBarWidth(50);
            $packageProgress->start();

            $namespaceList = [];

            foreach ($this->getPackages() as $packageName => $package) {
                $packageSection->overwrite("Processing package $packageName ...");
                $packageProgress->advance();
                $package->build();
                $namespaceList[] = $package->getNamespaces();
            }
            if (!empty($namespaceList)) {
                $namespaceList = array_merge(...$namespaceList);
            }

            $packageSection->overwrite('Finished processing packages.');
            $packageProgress->finish();

            $packageProgressSection->clear();
            $this->_namespaces = $namespaceList;
            $output->writeln('');
            $output->writeln('Found ' . count($namespaceList) . " namespaces in {$this->getSourceFilesCount()} source files.");
        }

        return $this->_namespaces;
    }

    /**
     * @return string[]
     * @throws Exception
     */
    public function getSourceFiles(): array
    {
        if (!$this->_sourceFiles) {
            $sourceFiles = [];

            foreach ($this->getPackages() as $package) {
                $sourceFiles[] = $package->getSourceFiles();
            }

            if (!empty($sourceFiles)) {
                $sourceFiles = array_merge(...$sourceFiles);
            }

            $this->_sourceFiles = $sourceFiles;

        }
        return $this->_sourceFiles;
    }

    /**
     * @return int
     * @throws Exception
     */
    protected function getSourceFilesCount(): int
    {
        return count($this->getSourceFiles());
    }

    protected function renamespacePackages()
    {

    }

    /**
     * @throws Exception
     */
    public function build(): void
    {
        $namespaceList = $this->getNamespaceList();
        $output = $this->consoleOutput;

        $packageSection = $output->section();
        $packageSection->writeln('Re-namespacing packages ...');

        $packageProgressSection = $output->section();
        $packageProgress = new ProgressBar($packageProgressSection, $this->getSourceFilesCount());
        $packageProgress->setFormat('very_verbose');
        $packageProgress->setBarWidth(50);
        $packageProgress->start();

        foreach ($this->getPackages() as $packageName => $package) {
            $packageSection->overwrite("Re-namespacing package $packageName ...");
            $packageProgress->advance();
            $package->renameSpace($namespaceList, $packageSection, $packageProgress);
        }

        $packageSection->overwrite('Finished re-namespacing packages.');
        $packageProgress->finish();

        $packageProgressSection->clear();

        $output->writeln('');
    }
}

