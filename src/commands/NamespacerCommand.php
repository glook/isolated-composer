<?php
/**
 * Created by: Andrey Polyakov (andrey@polyakov.im)
 */

namespace Glook\IsolatedComposer\commands;

use Exception;
use Glook\IsolatedComposer\helpers\FileHelper;
use Glook\IsolatedComposer\helpers\StringHelper;
use Glook\IsolatedComposer\models\Project;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class NamespacerCommand extends Command
{
    protected static $defaultName = 'namespacer';
    /**
     * @var OutputInterface
     */
    private $output;
    /**
     * @var InputInterface
     */
    private $input;
    /**
     * @var string|null
     */
    private $rootDir;
    /**
     * @var string
     */
    private $tmpDir;

    public function __construct(string $name = null, string $rootDir = null)
    {
        parent::__construct($name);
        $this->rootDir = StringHelper::trailingslashit($rootDir);

        $tmpDirPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('', true);
        FileHelper::createDirectory($tmpDirPath, 0755, true);
        $this->tmpDir = StringHelper::trailingslashit($tmpDirPath);
    }

    public function __destruct()
    {
        FileHelper::removeDirectory($this->tmpDir);
    }

    protected function configure()
    {
        $this->setDescription('Renamespaces a composer.json project to make it isolated.');

        $this->addArgument('dest', InputArgument::REQUIRED, 'The path to save the renamespaced libraries to.');

        $this->addOption('composer', null, InputOption::VALUE_REQUIRED, 'The path to the composer.json containing the packages to renamespace.', null);
        $this->addOption('source', null, InputOption::VALUE_REQUIRED, 'The path to the directory containing the composer.json to renamespace.  This directory should already have had `composer update` run in it.', null);

        $this->addOption('package', null, InputOption::VALUE_REQUIRED, 'The prefix to add to packages', null);
        $this->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'The prefix to add to namespaces', null);
        $this->addOption('config', null, InputOption::VALUE_REQUIRED, 'The path to the configuration to use, if required.', null);
        // Boolean (flag) composer options
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Outputs the operations but will not execute anything (implies --verbose).');
        $this->addOption('dev', null, InputOption::VALUE_NONE, 'Enables installation of require-dev packages (enabled by default, only needed if --no-dev was used before).');
        $this->addOption('no-dev', null, InputOption::VALUE_NONE, 'Skip installing packages listed in require-dev. The autoloader generation skips the autoload-dev rules.');
        $this->addOption('no-install', null, InputOption::VALUE_NONE, 'Skip the install step after updating the composer.lock file.');
        $this->addOption('no-audit', null, InputOption::VALUE_NONE, 'Skip the audit step after updating the composer.lock file.');
        $this->addOption('no-security-blocking', null, InputOption::VALUE_NONE, 'Audit print warnings but do not block.');
        $this->addOption('lock', null, InputOption::VALUE_NONE, 'Only updates the lock file hash to suppress warning about the lock file being out of date.');
        $this->addOption('no-autoloader', null, InputOption::VALUE_NONE, 'Skips autoloader generation.');
        $this->addOption('no-progress', null, InputOption::VALUE_NONE, 'Do not output download progress.');
        $this->addOption('with-dependencies', 'w', InputOption::VALUE_NONE, 'Add also dependencies of whitelisted packages to the whitelist.');
        $this->addOption('with-all-dependencies', 'W', InputOption::VALUE_NONE, 'Add all dependencies of whitelisted packages to the whitelist.');
        $this->addOption('optimize-autoloader', 'o', InputOption::VALUE_NONE, 'Optimize autoloader during autoloader dump.');
        $this->addOption('classmap-authoritative', 'a', InputOption::VALUE_NONE, 'Autoload classes from the classmap only. Implicitly enables --optimize-autoloader.');
        $this->addOption('apcu-autoloader', null, InputOption::VALUE_NONE, 'Use APCu to cache found/not-found classes.');
        $this->addOption('ignore-platform-reqs', null, InputOption::VALUE_NONE, 'Ignore all platform requirements (php & ext- packages).');
        $this->addOption('prefer-stable', null, InputOption::VALUE_NONE, 'Prefer stable versions of dependencies.');
        $this->addOption('prefer-lowest', null, InputOption::VALUE_NONE, 'Prefer lowest versions of dependencies.');
        $this->addOption('minimal-changes', 'm', InputOption::VALUE_NONE, 'During a partial update, only change versions of the packages in the require/require-dev list.');
        $this->addOption('patch-only', null, InputOption::VALUE_NONE, 'Only allow patch-level updates during a partial update.');
        $this->addOption('interactive', null, InputOption::VALUE_NONE, 'Interactive interface with autocompletion to select the packages to update.');
        $this->addOption('root-reqs', null, InputOption::VALUE_NONE, 'Restricts the update to your first degree dependencies.');
        $this->addOption('no-plugins', null, InputOption::VALUE_NONE, 'Disables plugins.');
        $this->addOption('no-scripts', null, InputOption::VALUE_NONE, 'Skips execution of scripts defined in composer.json.');

        // Value composer options
        $this->addOption('prefer-install', null, InputOption::VALUE_REQUIRED, 'Forces installation from package sources when possible, including VCS information. (source/dist/auto)');
        $this->addOption('audit-format', null, InputOption::VALUE_REQUIRED, 'Audit output format. Must be "table", "plain", "json", or "summary".');
        $this->addOption('with', null, InputOption::VALUE_REQUIRED, 'Temporary version constraint to add, e.g. foo/bar:1.0.0 or foo/bar=1.0.0.');
        $this->addOption('apcu-autoloader-prefix', null, InputOption::VALUE_REQUIRED, 'Use a custom prefix for the APCu autoloader cache.');
        $this->addOption('bump-after-update', null, InputOption::VALUE_REQUIRED, 'Run bump after update, set to "dev", "no-dev" or true.');
        $this->addOption('ignore-platform-req', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Ignore a specific platform requirement (php & ext- packages).');

        $this->addOption('vendor-dir', null, InputOption::VALUE_REQUIRED, 'Name of folder where renamspaced packages will be stored (default: vendor)', 'vendor');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        try {
            if (!$this->getOption('package') || !$this->getOption('namespace')) {
                throw new Exception('You must specify both the --package and --namespace option.');
            }

            $sourcePath = $this->getSourcePath();
            $outputPath = $this->getOutputPath();
            if ($sourcePath === $outputPath) {
                $this->renderOverwriteSourceQuestion();
            }

            $project = new Project([
                'inputPath' => $sourcePath,
                'outputPath' => $outputPath,
                'workingDir' => $this->tmpDir,
                'packagePrefix' => $this->getOption('package'),
                'namespacePrefix' => $this->getOption('namespace'),
                'configPath' => $this->getConfigPath(),
                'consoleOutput' => $output,
                'composerOptions' => trim($this->getComposerOptions()),
                'vendorDir' => $this->getOption('vendor-dir'),
            ]);

            $this->renderProjectData($project);
            $project->build();

            $output->writeln('');
            $output->writeln('Finished.');
        } catch (Exception $exception) {
            $output->writeln("<error>{$exception->getMessage()}</error>");
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    /**
     * @throws Exception
     */
    protected function renderOverwriteSourceQuestion()
    {
        $questionHelper = new QuestionHelper();

        $questionText = implode("\n", [
            '<error>@@@ Caution @@@</error>',
            'Your source and destination path are same, it means that namespacer will overwrite your source files.',
            'You can avoid this by set different destination path.',
            '<info>This operation cannot be undone.</info>',
            '',
            'Are you sure? [y/N]',
        ]);

        $question = new ConfirmationQuestion($questionText,
            false,
            '/^y/i'
        );
        $answer = $questionHelper->ask($this->input, $this->output, $question);

        if (!$answer) {
            throw new Exception('Aborted by user prompt');
        }
    }

    /**
     * Render project data to cli
     * @param Project $project
     */
    protected function renderProjectData(Project $project): void
    {
        $output = $this->output;
        $output->writeln('');

        $table = new Table($output);
        $table
            ->setHeaderTitle('Settings')
            ->setHeaders(['Setting', 'Value'])
            ->setRows($project->getOutputData())
            ->render();

        $output->writeln('');

        $table = new Table($output);
        $table
            ->setHeaderTitle('Found Packages')
            ->setHeaders(['Package', 'Version'])
            ->setRows($project->getCollectionOutputData())
            ->render();

        $output->writeln('');
    }

    /**
     * Get source path from passed options
     * @return string
     * @throws Exception
     */
    protected function getSourcePath(): string
    {
        if (!$this->getOption('composer') && !$this->getOption('source')) {
            throw new Exception('You must specify either the --composer or --source option.');
        }

        $tmpDir = $this->tmpDir;
        if ($originalComposer = $this->getOption('composer')) {
            if (strpos($originalComposer, '/') !== 0) {
                $originalComposer = $this->rootDir . $originalComposer;
            }

            if (!file_exists($originalComposer)) {
                rmdir($tmpDir);
                throw new Exception("Composer file does not exist at $originalComposer.");
            }

            $sourcePath = StringHelper::trailingslashit($tmpDir . 'project');
            FileHelper::createDirectory($sourcePath, 0755, true);
            copy($originalComposer, $sourcePath . 'composer.json');

            $this->output->writeln('Creating project ... ');
            $this->output->writeln('');

            $composerOptions = trim($this->getComposerOptions());
            shell_exec("cd $sourcePath && composer update {$composerOptions}");
            $this->output->writeln('');
            return $sourcePath;
        }

        $sourcePath = $this->getOption('source');
        if (strpos($sourcePath, '/') !== 0) {
            $sourcePath = StringHelper::trailingslashit($this->rootDir . $sourcePath);
        } else {
            $sourcePath = StringHelper::trailingslashit($sourcePath);
        }

        if (!file_exists($sourcePath)) {
            throw new Exception("Input directory $sourcePath does not exist.");
        }
        return $sourcePath;
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function getOutputPath(): string
    {
        $outputPath = $this->getArgument('dest');
        if (strpos($outputPath, '/') !== 0) {
            $outputPath = StringHelper::trailingslashit($this->rootDir . $outputPath);
        } else {
            $outputPath = StringHelper::trailingslashit($outputPath);
        }

        if (file_exists($outputPath)) {
            $vendorName = $this->getOption('vendor-dir') ?? 'lib';
            // remove previous build
            if (file_exists($outputPath . $vendorName)) {
                FileHelper::removeDirectory($outputPath . $vendorName);
            }
        } else {
            FileHelper::createDirectory($outputPath, 0755, true);
        }
        return $outputPath;
    }

    /**
     * @return string|null
     * @throws Exception
     */
    protected function getConfigPath(): ?string
    {
        $configPath = null;
        if ($configPath = $this->getOption('config')) {
            if (strpos($configPath, '/') !== 0) {
                $configPath = $this->rootDir . $configPath;
            }

            if (!file_exists($configPath)) {
                throw new Exception("Config file $configPath does not exist.");
            }
        }
        return $configPath;
    }

    private function getComposerOptions(): string
    {
        $booleanOptions = [
            'dry-run', 'dev', 'no-dev', 'no-install', 'no-audit',
            'no-security-blocking', 'lock', 'no-autoloader', 'no-progress',
            'with-dependencies', 'with-all-dependencies', 'optimize-autoloader',
            'classmap-authoritative', 'apcu-autoloader', 'ignore-platform-reqs',
            'prefer-stable', 'prefer-lowest', 'minimal-changes',
            'patch-only', 'interactive', 'root-reqs',
            'no-plugins', 'no-scripts',
        ];

        $valueOptions = [
            'prefer-install', 'audit-format', 'with',
            'apcu-autoloader-prefix', 'bump-after-update',
        ];

        $parts = [];

        foreach ($booleanOptions as $option) {
            if ($this->input->getOption($option)) {
                $parts[] = '--' . $option;
            }
        }

        foreach ($valueOptions as $option) {
            $value = $this->input->getOption($option);
            if (!empty($value)) {
                $parts[] = '--' . $option . '=' . escapeshellarg($value);
            }
        }

        foreach ((array)$this->input->getOption('ignore-platform-req') as $req) {
            if (!empty($req)) {
                $parts[] = '--ignore-platform-req=' . escapeshellarg($req);
            }
        }

        // Symfony built-in: --quiet / -q
        if ($this->output->isQuiet()) {
            $parts[] = '--quiet';
        }

        // Symfony built-in: --verbose / -v / -vv / -vvv
        if ($this->output->isDebug()) {
            $parts[] = '-vvv';
        } elseif ($this->output->isVeryVerbose()) {
            $parts[] = '-vv';
        } elseif ($this->output->isVerbose()) {
            $parts[] = '--verbose';
        }

        // Symfony built-in: --no-interaction / -n
        if (!$this->input->isInteractive()) {
            $parts[] = '--no-interaction';
        }

        return implode(' ', $parts);
    }

    /**
     * @param string $name
     * @return string|null
     */
    protected function getOption(string $name): ?string
    {
        $value = $this->input->getOption($name);
        return !empty($value) ? $value : null;
    }

    /**
     * @param string $name
     * @return string|null
     */
    protected function getArgument(string $name): ?string
    {
        $value = $this->input->getArgument($name);
        return !empty($value) ? $value : null;
    }

}
