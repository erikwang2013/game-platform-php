<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Erikwang2013\WebmanScout\Concerns\ResolvesScoutModel;
use Erikwang2013\WebmanScout\Events\ModelsImported;
use Illuminate\Events\Dispatcher;


class ImportCommand extends Command
{
    use ResolvesScoutModel;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected static $defaultName = 'scout:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Import the given model into the search index';

    protected function configure()
    {
        $this->addArgument('model', InputArgument::OPTIONAL, 'Class name of model to bulk import');
        $this->addOption('fresh', '--fresh', InputOption::VALUE_REQUIRED, 'The name of the primary key');
        $this->addOption('chunk', '--chunk', InputOption::VALUE_REQUIRED, 'The name of the primary key');
    }
    /**
     * Execute the console command.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     *
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $class = $this->resolveModelClass((string) $input->getArgument('model'));

        $model = new $class;
         $provider = app(Dispatcher::class);
        $provider->listen(ModelsImported::class, function ($event) use (&$output, $class) {
            $key = $event->models->last()->getScoutKey();
            $output->writeln('<comment>Imported ['.$class.'] models up to ID:</comment> '.$key);
        });
       /*  $events=app(Dispatcher::class);
        $events->listen(ModelsImported::class, function ($event) use (&$output, $class) {
            $key = $event->models->last()->getScoutKey();

            $output->writeln('<comment>Imported [' . $class . '] models up to ID:</comment> ' . $key);
        }); */

        if ($input->getOption('fresh')) {
            $model::removeAllFromSearch();
        }

        $model::makeAllSearchable($input->getOption('chunk'));

        $provider->forget(ModelsImported::class);

        $output->writeln('All [' . $class . '] records have been imported.');
        return Command::SUCCESS;
    }
}
