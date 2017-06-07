<?php

namespace ricwein\shurl\Console;

use ricwein\shurl\Core\Core;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * provides add command to bin/shurl cli tool
 */
class Add extends Command {

	protected function configure() {

		$this->setName('url:add');
		$this->setDescription('adds new entry.');
		$this->setHelp('This command adds a new URL to the shurl Database.');

		$this->setDefinition([
			new InputArgument('url', InputArgument::OPTIONAL, 'The URL which should be shortened'),
			new InputOption('slug', 's', InputOption::VALUE_OPTIONAL, 'Use a specific slug for URL-Shortening'),
			new InputOption('expires', 't', InputOption::VALUE_OPTIONAL, 'set expiration date for this URL'),
			new InputOption('starts', 'f', InputOption::VALUE_OPTIONAL, 'set start date for this URL'),
			new InputOption('passthrough', null, InputOption::VALUE_NONE, 'make url passthrough only'),
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

		$core = new Core();
		if (null === $slug = $input->getOption('slug')) {
			$info = $core->addUrl($url, null, $input->getOption('starts'), $input->getOption('expires'), $input->getOption('passthrough'));
		} else {
			$info = $core->addUrl($url, ltrim($slug, '= '), $input->getOption('starts'), $input->getOption('expires'), $input->getOption('passthrough'));
		}

		$output->writeln(PHP_EOL . '<info>Your URL has been added!</info>' . PHP_EOL);
		$output->writeln('Original URL:  ' . $info->getOriginal());
		$output->writeln('Slug:          ' . $info->getSlug());
		$output->writeln('Shortened URL: ' . $info->getShortened());
	}
}
