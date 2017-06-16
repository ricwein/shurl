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

	/**
	 * @var array
	 */
	const DISTINCT_COL = ['user_agent', 'ip', 'referrer'];

	protected function configure() {

		$this->setName('url:show');
		$this->setDescription('generate and show stats');
		$this->setHelp('This command generates usage-statistics about all known entries.');

		$this->setDefinition([
			new InputOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit results. <comment>0 = unlimited</comment>', 100),
			new InputOption('all', 'a', InputOption::VALUE_NONE, 'also show disabled entries, default is only enabled'),
			new InputOption('shortenedURL', 'u', InputOption::VALUE_NONE, 'adds full shortened URL to output'),
			new InputOption('filter', 'f', InputOption::VALUE_OPTIONAL, 'Search for Slug or URL'),
			new InputOption('distinct', null, InputOption::VALUE_OPTIONAL, 'distinct users, DNT visits always count as unique! <comment>["' . implode('", "', static::DISTINCT_COL) . '"]</comment>'),
		]);

	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$config = Config::getInstance();
		$pixie  = (new Core($config))->db;

		$query = $pixie->table('redirects');

		$query->join('urls', 'urls.id', '=', 'redirects.url_id', 'LEFT');
		$query->join('visits', 'redirects.id', '=', 'visits.redirect_id', 'LEFT');

		// aggregate visitors
		if (null === $distinct = $input->getOption('distinct')) {
			$query->select(['redirects.*', 'urls.url', $query->raw(sprintf('COUNT(%svisits.id) as hits', $config->database['prefix']))]);
		} elseif (!in_array($distinct, static::DISTINCT_COL)) {
			throw new \UnexpectedValueException(sprintf('"%s" is not a valid distinction column mode, please use one of the following: ' . implode(', ', static::DISTINCT_COL), $distinct));
		} else {
			$query->select(['redirects.*', 'urls.url', $query->raw(sprintf('COUNT(DISTINCT %svisits.%s) + SUM(CASE WHEN %svisits.%s IS NULL THEN 1 ELSE 0 END) as hits', $config->database['prefix'], $distinct, $config->database['prefix'], $distinct))]);
		}

		$query->groupBy('redirects.id');
		$query->orderBy(['hits', 'redirects.id'], 'DESC');
		$now = new \DateTime();

		// only select currently enabled entries
		if (!$input->getOption('all')) {
			$query->where('redirects.enabled', '=', true);
			$query->where(function ($db) use ($now, $config) {
				$db->where('redirects.valid_to', '>', $now->format($config->timestampFormat['database']));
				$db->orWhereNull('redirects.valid_to');
			});
			$query->where(function ($db) use ($now, $config) {
				$db->where('redirects.valid_from', '<', $now->format($config->timestampFormat['database']));
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
				$url['shortened'] = rtrim($config->rootURL, '/') . '/' . $entry->slug;
			}

			$entries[] = $url;
		}

		$table = new Table($output);
		$table->setHeaders($header);
		$table->setRows($entries);
		$table->render();
	}
}
