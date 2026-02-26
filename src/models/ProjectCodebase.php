<?php
/**
 * Created by: Andrey Polyakov (andrey@polyakov.im)
 */

namespace Glook\IsolatedComposer\models;

use Exception;
use Glook\IsolatedComposer\helpers\FileHelper;
use Glook\IsolatedComposer\helpers\StringHelper;

/**
 * Class ProjectCodebase
 * @package Glook\IsolatedComposer\models
 */
class ProjectCodebase extends Package
{
    /**
     * @var array
     */
    protected $composerConfig;

    /**
     * @var string[]
     */
    private $_sourceFiles;

    /**
     * @var string[]
     */
    private $_autoloadedResources;

    public function init(): void
    {
        $this->disablePackageProcessing();
    }

    /**
     * @inheritDoc
     */
    public function build(): void
    {
        // just do nothing
    }

    /**
     * @inheritDoc
     */
    public function getSourceFiles(): array
    {
        if (!$this->_sourceFiles) {
            $this->copyAutoloadedResources();
        }

        return $this->_sourceFiles;
    }

    /**
     * @param array $data
     */
    protected function addSourceFiles(array $data): void
    {
        $this->_sourceFiles = array_merge($this->_sourceFiles ?? [], $data);
    }

    /**
     * Copy autoloaded recources from input path
     * @throws Exception
     * @see ProjectCodebase::getAutoloadedResources()
     */
    protected function copyAutoloadedResources(): void
    {
        $inputFolder = $this->getInputPath();
        $outputFolder = $this->getOutputPath();
        foreach ($this->getAutoloadedResources() as $resourcePath) {
            $oldPath = $inputFolder . $resourcePath;
            $newPath = $outputFolder . $resourcePath;
            $isFolder = !pathinfo($newPath, PATHINFO_EXTENSION);

            if ($isFolder) {
                FileHelper::copyDirectory($oldPath, $newPath);
                $this->addSourceFiles($this->findFilesInPath($newPath));
            } else {
                $dir = dirname($newPath);
                if (!is_dir($dir)) {
                    FileHelper::createDirectory($dir, 0755, true);
                }
                copy($oldPath, $newPath);
                $this->addSourceFiles([$newPath]);
            }
        }
    }

    /**
     * Find project files from autoload composer section
     * @return string[]
     */
    protected function getAutoloadedResources(): array
    {
        if (!$this->_autoloadedResources && $config = $this->composerConfig) {
            $resourceList = [];
            if (isset($config['autoload'])) {
                $resourceList = array_map(static function ($section) {
                    if (is_array($section)) {
                        return array_map(static function ($path) {
                            return StringHelper::untrailingslashit($path);
                        }, array_values($section));
                    }
                    return [];
                }, array_values($config['autoload']));

                if (!empty($resourceList)) {
                    $resourceList = array_merge(...$resourceList);
                }
            }
            $this->_autoloadedResources = $resourceList;
        }

        return $this->_autoloadedResources;
    }

    /**
     * @inheritDoc
     */
    public function getOutputPath(): string
    {
        return StringHelper::trailingslashit($this->getWorkingDir() . 'codebase');
    }

}
