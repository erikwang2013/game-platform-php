<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Erikwang2013\WebmanScout\Contracts\UpdatesIndexSettings;
use Erikwang2013\WebmanScout\EngineManager;
use Erikwang2013\WebmanScout\Engines\Engine;
use Erikwang2013\WebmanScout\Exceptions\NotSupportedException;


class IndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected static $defaultName = 'scout:index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected static $defaultDescription = 'Create an index';


    protected function configure()
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'The name of the index');
        $this->addOption('key', '--key', InputOption::VALUE_REQUIRED, 'The name of the primary key');
    }
    /**
     * Execute the console command.
     *
     * @param  \Erikwang2013\WebmanScout\EngineManager  $manager
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $engine = app(EngineManager::class)->engine();
        try {
            $options = [];

            if ($input->getOption('key')) {
                $options = ['primaryKey' => $input->getOption('key')];
            }

            if (class_exists($modelName = $input->getArgument('name'))) {
                $model = new $modelName;
            }

            $name = $this->indexName($input->getArgument('name'));

            $this->createIndex($engine, $name, $options);

            if ($engine instanceof UpdatesIndexSettings) {
                $driver = scout_config('driver');

                $class = isset($model) ? get_class($model) : null;

                $settings = scout_config($driver.'.index-settings.'.$name)
                    ?? scout_config($driver.'.index-settings.'.$class)
                    ?? [];

                if (
                    isset($model) &&
                    scout_config('soft_delete', false) &&
                    in_array(SoftDeletes::class, class_uses_recursive($model))
                ) {
                    $settings = $engine->configureSoftDeleteFilter($settings);
                }

                if ($settings) {
                    $engine->updateIndexSettings($name, $settings);
                }
            }

           $output->writeln('Index ["'.$name.'"] created successfully.');
            return Command::SUCCESS;
        } catch (Exception $exception) {
            $output->writeln($exception->getMessage());
           return Command::FAILURE;
        }
    }

    /**
     * Create a search index.
     *
     * @param  \Erikwang2013\WebmanScout\Engines\Engine  $engine
     * @param  string  $name
     * @param  array  $options
     * @return void
     */
    protected function createIndex(Engine $engine, $name, $options): void
    {
        try {
            $engine->createIndex($name, $options);
        } catch (NotSupportedException) {
            return;
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
