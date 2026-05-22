<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Support\Str;
use Erikwang2013\WebmanScout\EngineManager;

class DeleteIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected static $defaultName = 'scout:delete-index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Delete an index';

    protected function configure()
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'The name of the index');
    }
    /**
     * Execute the console command.
     *
     * @param  \Erikwang2013\WebmanScout\EngineManager  $manager
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $name = $input->getArgument('name');
            $manager = app(EngineManager::class);
            $manager->engine()->deleteIndex($name = $this->indexName($name));

            $output->writeln('Index "' . $name . '" deleted.');
            return Command::SUCCESS;
        } catch (Exception $exception) {
            $output->writeln($exception->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Get the fully-qualified index name for the given index.
     *
     * @param  string  $name
     * @return string
     */
    protected function indexName($name)
    {
        if (class_exists($name)) {
            return (new $name)->indexableAs();
        }

        $prefix = scout_config('prefix');

        return ! Str::startsWith($name, $prefix) ? $prefix . $name : $name;
    }
}
