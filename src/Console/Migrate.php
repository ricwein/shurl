<?php

namespace ricwein\shurl\Console;

use ricwein\shurl\Config\Config;
use ricwein\shurl\Core\Core;
use ricwein\shurl\Redirect\Rewrite;
use ricwein\shurl\Template\Template;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * provides init cli command
 */
class Migrate extends Command {

	protected function configure() {
		$this->setName('migrate');
		$this->setDescription('migrate to current version');
		$this->setHelp('This command migrates the shurl database-structure to current version.');

		$this->setDefinition([
			new InputOption('output', 'o', InputOption::VALUE_NONE, 'print resulting SQL Queries, instead of executing them'),
			new InputOption('rollback', 'b', InputOption::VALUE_NONE, 'rollback last migration'),
			new InputOption('confirm', 'y', InputOption::VALUE_NONE, 'skip confirmation questions'),
			new InputOption('to', null, InputOption::VALUE_OPTIONAL, 'pass a custom version number'),
			new InputOption('from', null, InputOption::VALUE_OPTIONAL, 'pass a custom version number'),
		]);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$config = Config::getInstance();
		$helper = $this->getHelper('question');

		// override config, to allow using the templating-engine for sql files
		$config->views = array_replace_recursive($config->views, [
			'path'      => 'resources/database/templates',
			'extension' => '.sql.twig',
		]);

		// init db-connection
		$pixie = (new Core($config))->getDB();

		if (!$input->getOption('to') || !$input->getOption('from')) {

			// fetch version history from DB
			$query = $pixie->table('version');
			$query->where('type', 'last')->orWhere('type', 'current');
			$query->select(['type', 'version']);
			$versions = array_column($query->get(), 'version', 'type');

		} else {
			$versions = [];
		}

		// preprocess versions
		$versions['org'] = (float) $this->getApplication()->getVersion();

		if (null !== $to = $input->getOption('to')) {
			$versions[$input->getOption('rollback') ? 'last' : 'org'] = $to;
		}
		if (null !== $from = $input->getOption('from')) {
			$versions['current'] = $from;
		}

		$versions = array_map(function ($version) {
			return $version !== null ? (float) $version : null;
		}, $versions);

		$versions['to'] = ($input->getOption('rollback') ? $versions['last'] : $versions['org']);

		// validate version numbers
		if ($versions['to'] === $versions['current']) {
			$output->writeln('migration done: <info>nothing to do</info>');
			return;
		} elseif ($versions['to'] === null) {
			throw new \UnexpectedValueException('invalid version number: to');
		} elseif ($versions['current'] === null) {
			throw new \UnexpectedValueException('invalid version number: current');
		} elseif ($input->getOption('rollback') && $versions['to'] >= $versions['current']) {
			throw new \UnexpectedValueException('invalid versions for rollback');
		} elseif (!$input->getOption('rollback') && $versions['to'] <= $versions['current']) {
			throw new \UnexpectedValueException('invalid versions for migration');
		}

		$question = new ConfirmationQuestion(($input->getOption('rollback') ? 'Rollback' : 'Migrating') . ' from <comment>' . number_format((float) $versions['current'], 1, '.', '') . '</comment> to <comment>' . number_format((float) $versions['to'], 1, '.', '') . '</comment>, continue?' . PHP_EOL . '[Y/n]: ', true, '/^(y|j)/i');
		if (!$input->getOption('confirm') && !$helper->ask($input, $output, $question)) {
			return;
		}

		// parse sql query files
		$queries  = '';
		$bindings = [
			'config'       => $config,
			'rewriteModes' => Rewrite::MODES,
			'version'      => ['current' => $versions['to']],
		];

		$prevVersion = $versions['current'];
		if ($input->getOption('rollback')) {
			for ($version = $versions['current']; $version >= $versions['to']; $version -= 0.1) {
				try {
					$queries .= (new Template(sprintf('migration/down_%s_%s', number_format((float) $prevVersion, 1, '.', ''), number_format((float) $version, 1, '.', '')), $config))->make($bindings);
					$prevVersion = $version;
				} catch (\Exception $e) {
					continue;
				}
			}
		} else {
			for ($version = $versions['current']; $version <= $versions['to']; $version += 0.1) {
				try {
					$queries .= (new Template(sprintf('migration/up_%s_%s', number_format((float) $prevVersion, 1, '.', ''), number_format((float) $version, 1, '.', '')), $config))->make($bindings);
					$prevVersion = $version;
				} catch (\Exception $e) {
					continue;
				}
			}
		}

		if ($input->getOption('output')) {
			$output->writeln($queries);
			exit(0);
		}

		// preprocess queries
		$queries = preg_replace(['/(--.*)/', '/((?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:\/\/.*))/'], '', $queries);
		$queries = str_replace([PHP_EOL, '  '], [' ', ' '], $queries);
		$queries = explode(';', $queries);
		$queries = array_filter(array_map('trim', $queries), function ($query) {
			return !empty($query);
		});

		$progress = new ProgressBar($output, count($queries) + 2);

		// execute queries
		foreach ($queries as $query) {
			$pixie->query($query);
			$progress->advance();
		}

		$pixie->table('version')->where('type', 'last')->update(['version' => $versions['current']]);
		$progress->advance();
		$pixie->table('version')->where('type', 'current')->update(['version' => $versions['to']]);
		$progress->advance();

		$progress->finish();
		$output->writeln(PHP_EOL . 'migration done: <info>updated to ' . $versions['to'] . '</info>');
	}
}
