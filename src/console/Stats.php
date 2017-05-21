<?php

namespace ricwein\shurl\console;

use ricwein\shurl\config\Config;
use ricwein\shurl\core\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * provides add command to bin/shurl cli tool
 */
class Stats extends Command {

	protected function configure() {

		$this->setName('stats');
		$this->setDescription('generate and show stats.');
		$this->setHelp('This command generates usage-statistics about all known entries.');

		$this->setDefinition([
			new InputOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit results. <comment>0 = unlimited</comment>', 100),
			new InputOption('all', 'a', InputOption::VALUE_NONE, 'also show disabled entries, default is only enabled'),
			new InputOption('shortenedURL', 'u', InputOption::VALUE_NONE, 'adds full shortened URL to output'),
		]);

	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$app = new Application();

		$limit = $input->getOption('limit');

		$query = $app->getDB()->table('redirects');
		$query->orderBy('hits', 'DESC');

		// only select currently enabled entries
		if (!$input->getOption('all')) {
			$query->where('enabled', '=', true);
			$query->where(function ($db) use ($app) {
				$db->where($db->raw('expires > NOW()'));
				$db->orWhereNull('expires');
			});
		}

		if ($limit > 0) {
			$query->limit((int) $limit);
		}

		$entries = [];
		$header  = ['Hits', 'Slug', 'URL', 'created'];

		// add additional header entry
		if ($input->getOption('shortenedURL')) {
			$header[] = 'shortened URL';
		}

		// build table-rows from query result object
		foreach ($query->get() as $entry) {

			$url = [
				'hits' => $entry->hits,
				'slug' => $entry->slug,
				'url'  => $entry->url,
				'date' => $entry->date,
			];

			// also add full shortened URL
			if ($input->getOption('shortenedURL')) {
				$url['shortened'] = rtrim(Config::getInstance()->rootURL, '/') . '/' . $entry->slug;
			}

			$entries[] = $url;
		}

		$table = new Table($output);
		$table->setHeaders($header);
		$table->setRows($entries);
		$table->render();
	}
}
