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
		$this->rootDir = trailingslashit($rootDir);

		$tmpDirPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('', true);
		FileHelper::createDirectory($tmpDirPath, 0755, true);
		$this->tmpDir = trailingslashit($tmpDirPath);
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
		$this->addOption('no-dev', null, InputOption::VALUE_NONE, 'Skip installing packages listed in require-dev. The autoloader generation skips the autoload-dev rules.');
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
				'composerOptions' => implode(' ', [
					StringHelper::toBoolean($this->getOption('quiet')) ? '--quiet' : '',
					StringHelper::toBoolean($this->getOption('no-dev')) ? '--no-dev' : '',
				]),
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

			$sourcePath = trailingslashit($tmpDir . 'project');
			FileHelper::createDirectory($sourcePath, 0755, true);
			copy($originalComposer, $sourcePath . 'composer.json');

			$this->output->writeln('Creating project ... ');
			$this->output->writeln('');

			$composerOptions = implode(' ', [
				StringHelper::toBoolean($this->getOption('quiet')) ? '--quiet' : '',
				StringHelper::toBoolean($this->getOption('no-dev')) ? '--no-dev' : '',
			]);
			shell_exec("cd $sourcePath && composer update {$composerOptions}");
			$this->output->writeln('');
			return $sourcePath;
		}

		$sourcePath = $this->getOption('source');
		if (strpos($sourcePath, '/') !== 0) {
			$sourcePath = trailingslashit($this->rootDir . $sourcePath);
		} else {
			$sourcePath = trailingslashit($sourcePath);
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
			$outputPath = trailingslashit($this->rootDir . $outputPath);
		} else {
			$outputPath = trailingslashit($outputPath);
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
