<?php
/**
 * Created by: Andrey Polyakov (andrey@polyakov.im)
 */

namespace Glook\IsolatedComposer\components;

use Exception;
use Glook\IsolatedComposer\helpers\FileHelper;
use Glook\IsolatedComposer\helpers\StringHelper;

abstract class BaseBuilder extends BaseObject
{
    /**
     * @see BaseBuilder::setWorkingDir()
     * @see BaseBuilder::getWorkingDir()
     * @var string
     */
    protected $_workingDir;

    /**
     * @see BaseBuilder::setInputPath()
     * @see BaseBuilder::getInputPath()
     * @var string
     */
    protected $_inputPath;

    /**
     * @see BaseBuilder::setOutputPath()
     * @see BaseBuilder::getOutputPath()
     * @var string
     */
    protected $_outputPath;

    /**
     * @return string
     */
    public function getInputPath(): string
    {
        return $this->_inputPath;
    }

    /**
     * @param string $value
     */
    protected function setInputPath(string $value): void
    {
        $this->_inputPath = StringHelper::trailingslashit($value);
    }

    /**
     * @return string
     */
    public function getWorkingDir(): string
    {
        return $this->_workingDir;
    }

    /**
     * @param string $value
     * @throws Exception
     */
    protected function setWorkingDir(string $value): void
    {
        if (!is_dir($value)) {
            FileHelper::createDirectory($value, 0755, true);
        }
        $this->_workingDir = StringHelper::trailingslashit($value);
    }

    /**
     * @return string
     */
    public function getOutputPath(): string
    {
        return $this->_outputPath;
    }

    /**
     * @param string $value
     * @throws Exception
     */
    protected function setOutputPath(string $value): void
    {
        if (!is_dir($value)) {
            FileHelper::createDirectory($value, 0755, true);
        }
        $this->_outputPath = StringHelper::trailingslashit($value);
    }

    /**
     *
     */
    abstract public function build(): void;
}
