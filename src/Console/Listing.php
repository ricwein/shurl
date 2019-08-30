<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\shurl\Console;

use Pixie\QueryBuilder\QueryBuilderHandler;

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
class Listing extends Command
{

    /**
     * @var array
     */
    const DISTINCT_COL = ['user_agent', 'ip', 'referrer'];

    protected function configure()
    {
        $this->setName('url:list');
        $this->setDescription('generate and list stats');
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
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = Config::getInstance();
        $pixie = (new Core($config))->db;

        $query = $pixie->table('redirects');
        $now = new \DateTime();

        $selects = [
            'redirects.*',
            'urls.url',
            $query->raw(sprintf('(redirects.enabled AND (redirects.valid_to > "%s" OR redirects.valid_to IS NULL) AND (redirects.valid_from < "%s" OR redirects.valid_from IS NULL)) as active', $now->format($config->timestampFormat['database']), $now->format($config->timestampFormat['database'])))
        ];

        try {
            $query->join('urls', 'urls.id', '=', 'redirects.url_id', 'LEFT');
            $query->join('visits', 'redirects.id', '=', 'visits.redirect_id', 'LEFT');

            // aggregate visitors
            if (null === $distinct = $input->getOption('distinct')) {
                $selects[] = $query->raw(sprintf('COUNT(%svisits.id) as hits', $config->database['prefix']));
            } elseif (in_array($distinct, static::DISTINCT_COL, true)) {
                $selects[] = $query->raw(sprintf('COUNT(DISTINCT %svisits.%s) + SUM(CASE WHEN %svisits.%s IS NULL THEN 1 ELSE 0 END) as hits', $config->database['prefix'], $distinct, $config->database['prefix'], $distinct));
            } else {
                throw new \UnexpectedValueException(sprintf('"%s" is not a valid distinction column mode, please use one of the following: ' . implode(', ', static::DISTINCT_COL), $distinct));
            }

            $query->select($selects);

            $query->groupBy('redirects.id');
            $query->orderBy(['hits', 'redirects.id'], 'DESC');

            // only select currently enabled entries
            if (!$input->getOption('all')) {
                $query->having('active', '=', true);
            }

            if (0 < $limit = $input->getOption('limit')) {
                $query->limit((int) $limit);
            }

            if (null !== $filter = $input->getOption('filter')) {
                $query->where(function (QueryBuilderHandler $db) use ($filter) {
                    $db->where('redirects.slug', 'LIKE', '%' . $filter . '%');
                    $db->orWhere('urls.url', 'LIKE', '%' . $filter . '%');
                });
            }

            $entries = [];
            $header  = ['Hits', 'Slug', 'URL', 'created', 'valid from', 'valid to', 'mode'];

            // add additional header entry
            if ($input->getOption('all')) {
                $header[] = 'active';
            }
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

                if ($input->getOption('all')) {
                    $url['active'] = ((bool) $entry->active) ? 'true' : 'false';
                }

                // also add full shortened URL
                if ($input->getOption('shortenedURL')) {
                    $url['shortened'] = rtrim($config->rootURL, '/') . '/' . $entry->slug;
                }

                $entries[] = $url;
            }
        } catch (\PDOException $e) {
            throw new \Exception(sprintf("Failed to execute query:\n%s", $query->getQuery()->getSql()), 500, $e);
        }


        $table = new Table($output);
        $table->setHeaders($header);
        $table->setRows($entries);
        $table->render();
    }
}
