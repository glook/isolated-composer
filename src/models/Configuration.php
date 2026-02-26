<?php

namespace Glook\IsolatedComposer\models;
/**
 * Class Configuration
 * @package Glook\IsolatedComposer\models
 */
class Configuration
{
    private $config = [];

    /**
     * @var array
     */
    private $_blackListedPackages;

    public function __construct(string $configFile = null)
    {
        if (!empty($configFile) && file_exists($configFile)) {
            $this->config = include $configFile;
        }
    }

    public function beforeBuild(string $package, array $config, string $inputPath, string $namespacePrefix): array
    {
        if (isset($this->config['beforeBuild'])) {
            foreach ($this->config['beforeBuild'] as $func) {
                $config = call_user_func($func, $package, $config, $inputPath, $namespacePrefix);
            }
        }

        return $config;
    }

    public function afterBuild(string $package, string $outputPath, string $namespacePrefix): void
    {
        if (isset($this->config['afterBuild'])) {
            foreach ($this->config['afterBuild'] as $func) {
                $func($package, $outputPath, $namespacePrefix);
            }
        }
    }

    public function start(string $source, ?string $currentNamespace, string $namespacePrefix, string $package, string $file): string
    {
        if (isset($this->config['start'])) {
            foreach ($this->config['start'] as $func) {
                $source = call_user_func($func, $source, $currentNamespace, $namespacePrefix, $package, $file);
            }
        }

        return $source;
    }

    public function before(string $source, string $namespace, ?string $currentNamespace, string $namespacePrefix, string $package, string $file): string
    {
        if (isset($this->config['before'])) {
            foreach ($this->config['before'] as $func) {
                $source = call_user_func($func, $source, $namespace, $currentNamespace, $namespacePrefix, $package, $file);
            }
        }

        return $source;
    }

    public function after(string $source, string $namespace, ?string $currentNamespace, string $namespacePrefix, string $package, string $file): string
    {
        if (isset($this->config['after'])) {
            foreach ($this->config['after'] as $func) {
                $source = call_user_func($func, $source, $namespace, $currentNamespace, $namespacePrefix, $package, $file);
            }
        }

        return $source;
    }

    public function end(string $source, ?string $currentNamespace, string $namespacePrefix, string $package, string $file): string
    {
        if (isset($this->config['end'])) {
            foreach ($this->config['end'] as $func) {
                $source = call_user_func($func, $source, $currentNamespace, $namespacePrefix, $package, $file);
            }
        }

        return $source;
    }

    /**
     * @return string[]
     */
    public function getBlacklistedPackages(): array
    {
        if (!$this->_blackListedPackages) {
            if (isset($this->config['blacklist']) && is_array($this->config['blacklist'])) {
                $this->_blackListedPackages = array_filter($this->config['blacklist'], function ($packageName) {
                    return is_string($packageName);
                });
            } else {
                $this->_blackListedPackages = [];
            }
        }

        return $this->_blackListedPackages;
    }
}
