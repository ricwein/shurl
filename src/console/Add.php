<?php

namespace ricwein\shurl\console;

use ricwein\shurl\config\Config;
use ricwein\shurl\core\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * provides add command to bin/shurl cli tool
 */
class Add extends Command {

	protected function configure() {

		$this->setName('add');
		$this->setDescription('adds new entry.');
		$this->setHelp('This command adds a new URL to the shurl Database.');

		$this->setDefinition([
			new InputArgument('url', InputArgument::OPTIONAL, 'The URL which should be shortened.'),
			new InputArgument('slug', InputArgument::OPTIONAL, 'Use this slug for shortening, if given, else use a random string.'),
		]);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$helper = $this->getHelper('question');

		// fetch url
		$url = $input->getArgument('url');
		if (!$url) {

			// not given per parameter, fetch per question
			$question = new Question('url: ');
			$question->setValidator(function ($url) {

				if (!empty($url) && is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false) {

					// valid url
					return $url;

				} elseif (!empty($url) && is_string($url) && filter_var('https://' . $url, FILTER_VALIDATE_URL) !== false) {

					// valid url, if https is prefixed
					return 'https://' . $url;

				} else {

					// invalid url
					throw new \RuntimeException('Invalid URL');

				}

			});

			$url = $helper->ask($input, $output, $question);

		} elseif (is_string($url) && filter_var($url, FILTER_VALIDATE_URL) === false && filter_var('https://' . $url, FILTER_VALIDATE_URL) !== false) {

			// automatically use https prefix to make url valid
			$url = 'https://' . $url;

		} elseif (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {

			throw new \RuntimeException('Invalid URL');

		}

		$slug = $input->getArgument('slug');
		if (!$slug) {

			// @TODO add better slug generation here
			$slug = md5($url);

		}

		$config = Config::getInstance();
		$app    = new Application($config);
		$info   = $app->addUrl($url, $slug);

		$output->writeln(PHP_EOL . '<info>Your URL has been added!</info>' . PHP_EOL);
		$output->writeln('Original URL:  ' . $info->getOriginal());
		$output->writeln('Slug:          ' . $info->getSlug());
		$output->writeln('Shortened URL: ' . $info->getShortened());
	}
}
