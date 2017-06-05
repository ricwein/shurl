<?php

namespace ricwein\shurl\Console;

use ricwein\shurl\Config\Config;
use ricwein\shurl\Core\Application;
use ricwein\shurl\Core\Network;
use ricwein\shurl\Template\Template;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * provides init cli command
 */
class Init extends Command {

	protected function configure() {
		$this->setName('init');
		$this->setDescription('init new shurl instance.');
		$this->setHelp('This command bootstraps the shurl database-structure.');

		$this->setDefinition([
			new InputOption('force', 'f', InputOption::VALUE_NONE, 'fore init, override existing tables!'),
			new InputOption('dropforce', 'd', InputOption::VALUE_NONE, 'force init database, drops database first!'),
		]);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * sql-comment regex:
	 * @link http://blog.ostermiller.org/find-comment
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$config = Config::getInstance();

		// override config, to allow using the templating-engine for sql files
		$config->template = array_replace_recursive($config->template, [
			'path'      => 'resources/database/templates',
			'extension' => '.sql.twig',
		]);

		// init db-connection
		$app   = new Application($config);
		$pixie = $app->getDB();

		// parse sql query files
		$queries = '';
		if ($input->getOption('dropforce')) {
			$queries .= (new Template('drop.database', $config, new Network()))->make($config->database);
		} elseif ($input->getOption('force')) {
			$queries .= (new Template('drop.tables', $config, new Network()))->make($config->database);
		}
		$queries .= (new Template('create', $config, new Network()))->make($config->database);

		// preprocess queries
		$queries = preg_replace(['/(--.*)/', '/((?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:\/\/.*))/'], '', $queries);
		$queries = str_replace([PHP_EOL, '  '], [' ', ' '], $queries);
		$queries = explode(';', $queries);
		$queries = array_filter(array_map('trim', $queries), function ($query) {
			return !empty($query);
		});

		$progress = new ProgressBar($output, count($queries));

		// execute queries
		foreach ($queries as $query) {
			$pixie->query($query);
			$progress->advance();
		}

		$progress->finish();
		$output->writeln(PHP_EOL . '<info>done</info>');
	}
}
