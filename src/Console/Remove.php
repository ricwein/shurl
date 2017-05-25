<?php

namespace ricwein\shurl\Console;

use ricwein\shurl\config\Config;
use ricwein\shurl\core\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * provides add command to bin/shurl cli tool
 */
class Remove extends Command {

	protected function configure() {

		$this->setName('url:remove');
		$this->setDescription('disables entry.');
		$this->setHelp('This command allows to disable a selected URL.');

		$this->setDefinition([
			new InputArgument('search', InputArgument::OPTIONAL, 'Search for Slug or URL which should be disabled'),
		]);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$helper = $this->getHelper('question');

		// fetch search filter
		$search = $input->getArgument('search');
		if (!$search) {

			// not given per parameter, ask per question
			$question = new Question('search for entry: ');
			$search   = $helper->ask($input, $output, $question);

		}

		$app = new Application();

		$query = $app->getDB()->table('redirects');
		$query->join('urls', 'urls.id', '=', 'redirects.url_id', 'LEFT');

		$query->where('redirects.enabled', '=', true);
		$query->where(function ($db) {
			$db->where($db->raw(Config::getInstance()->database['prefix'] . 'redirects.expires > NOW()'));
			$db->orWhereNull('redirects.expires');
		});
		$query->where(function ($db) use ($search) {
			$db->where('slug', 'LIKE', '%' . $search . '%');
			$db->orWhere('url', 'LIKE', '%' . $search . '%');
		});

		$query->select(['redirects.id', 'redirects.slug', 'redirects.expires', 'urls.url']);
		$selection = $query->get();

		$helper   = $this->getHelper('question');
		$question = new ChoiceQuestion(
			'The search returned the following results, please select:',
			array_merge(['<comment>cancel</comment>'], array_map(function ($entry) {
				return '<info>' . $entry->slug . '</info> => ' . $entry->url;
			}, $selection)),
			0
		);
		$question->setMultiselect(false);
		$selected = $helper->ask($input, $output, $question);

		if ($selected === '<comment>cancel</comment>') {
			return;
		}

		// workaround, since Symfonys ChoiceQuestion only retuns selected values from array, but not the keys
		// let's create usefull values from fance GUI string
		list($selectedSlug, $selectedURL) = explode('=>', $selected, 2);

		// search for database entry from user selection
		foreach ($selection as $entry) {

			if (strpos($selectedSlug, $entry->slug) !== false && trim($selectedURL) === $entry->url) {

				$question = new ConfirmationQuestion('Delete the following Entry: <comment>' . $entry->slug . '</comment> => ' . $selectedURL . ' ?' . PHP_EOL . '[Y/n]: ', true, '/^(y|j)/i');
				if ($helper->ask($input, $output, $question)) {
					$query = $app->getDB()->table('redirects');
					$query->where('slug', '=', $entry->slug);
					$query->where('id', '=', $entry->id);
					$query->update(['enabled' => 0]);
				}

			}
		}

		exit(0);
	}
}
