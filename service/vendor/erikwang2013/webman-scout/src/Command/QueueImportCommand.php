<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Erikwang2013\WebmanScout\Concerns\ResolvesScoutModel;
use Symfony\Component\Console\Input\InputOption;
use Webman\RedisQueue\Redis as QueueRedis;

class QueueImportCommand extends Command
{
    use ResolvesScoutModel;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected static $defaultName = 'scout:queue-import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Import the given model into the search index via chunked, queued jobs';

    protected function configure()
    {
        $this->addArgument('model', InputArgument::OPTIONAL, 'Class name of model to bulk import');
        $this->addOption('chunk', '--chunk', InputOption::VALUE_REQUIRED, 'The number of records to import at a time (Defaults to configuration value: `scout.chunk.searchable`');
        $this->addOption('min', '--min', InputOption::VALUE_REQUIRED, 'The number of records to import at a time (Defaults to configuration value: `scout.chunk.searchable`');
        $this->addOption('max', '--max', InputOption::VALUE_REQUIRED, 'The number of records to import at a time (Defaults to configuration value: `scout.chunk.searchable`');
    }
    /**
     * Execute the console command.
     *
     * @return void
     *
     * @throws ScoutException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $class = $this->resolveModelClass((string) $input->getArgument('model'));

        $model = new $class;

        $query = $model::makeAllSearchableQuery();

        $min = $input->getOption('min') ?? $query->min($model->getScoutKeyName());
        $max = $input->getOption('max') ?? $query->max($model->getScoutKeyName());

        $chunk = max(1, (int) ($input->getOption('chunk') ?? scout_config('chunk.searchable', 500)));

        if (! $min || ! $max) {
            $output->writeln('No records found for [' . $class . '].');

            return Command::FAILURE;
        }

        if (! is_numeric($min) || ! is_numeric($max)) {
            $output->writeln('The primary key for [' . $class . '] is not numeric.');

            return Command::FAILURE;
        }

        for ($start = $min; $start <= $max; $start += $chunk) {
            $end = min($start + $chunk - 1, $max);

            if (class_exists(QueueRedis::class)) {
                QueueRedis::send('scout_make_range', ['model' => $class, 'start' => $start, 'end' => $start]);
            }


            $output->writeln('<comment>Queued [' . $class . '] models up to ID:</comment> ' . $end);
        }

        $output->writeln('All [' . $class . '] records have been queued for importing.');
        return Command::SUCCESS;
    }
}
