<?php

namespace ricwein\shurl\Console;

use ricwein\shurl\Config\Config;
use ricwein\shurl\Core\Core;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * provides add command to bin/shurl cli tool
 */
class Show extends Command {

	protected function configure() {

		$this->setName('url:show');
		$this->setDescription('generate and show stats.');
		$this->setHelp('This command generates usage-statistics about all known entries.');

		$this->setDefinition([
			new InputOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit results. <comment>0 = unlimited</comment>', 100),
			new InputOption('all', 'a', InputOption::VALUE_NONE, 'also show disabled entries, default is only enabled'),
			new InputOption('shortenedURL', 'u', InputOption::VALUE_NONE, 'adds full shortened URL to output'),
			new InputOption('filter', 'f', InputOption::VALUE_OPTIONAL, 'Search for Slug or URL'),
		]);

	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$pixie = (new Core())->getDB();

		$query = $pixie->table('redirects');

		$query->join('urls', 'urls.id', '=', 'redirects.url_id', 'LEFT');
		$query->join('visits', 'redirects.id', '=', 'visits.redirect_id', 'LEFT');

		$query->select(['redirects.*', 'urls.url', $query->raw('COUNT(' . Config::getInstance()->database['prefix'] . 'visits.id) as hits')]);
		$query->groupBy('redirects.id');
		$query->orderBy(['hits', 'redirects.id'], 'DESC');

		// only select currently enabled entries
		if (!$input->getOption('all')) {
			$query->where('redirects.enabled', '=', true);
			$query->where(function ($db) {
				$db->where($db->raw(Config::getInstance()->database['prefix'] . 'redirects.valid_to > NOW()'));
				$db->orWhereNull('redirects.valid_to');
			});
			$query->where(function ($db) {
				$db->where($db->raw(Config::getInstance()->database['prefix'] . 'redirects.valid_from < NOW()'));
				$db->orWhereNull('redirects.valid_from');
			});
		}

		if (0 < $limit = $input->getOption('limit')) {
			$query->limit((int) $limit);
		}

		if (null !== $filter = $input->getOption('filter')) {
			$query->where(function ($db) use ($filter) {
				$db->where('redirects.slug', 'LIKE', '%' . $filter . '%');
				$db->orWhere('urls.url', 'LIKE', '%' . $filter . '%');
			});
		}

		$entries = [];
		$header  = ['Hits', 'Slug', 'URL', 'created', 'valid from', 'valid to', 'mode'];

		// add additional header entry
		if ($input->getOption('shortenedURL')) {
			$header[] = 'shortened URL';
		}

		// build table-rows from query result object
		foreach ($query->get() as $entry) {

			$url = [
				'hits'   => $entry->hits,
				'slug'   => '<comment>' . $entry->slug . '</comment>',
				'url'    => $entry->url,
				'create' => $entry->created,
				'from'   => ($entry->valid_from ? $entry->valid_from : '-'),
				'to'     => ($entry->valid_to ? $entry->valid_to : '-'),
				'mode'   => '<info>' . $entry->mode . '</info>',
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
