<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

use Erikwang2013\WebmanScout\Command\DeleteIndexCommand;
use Erikwang2013\WebmanScout\Command\FlushCommand;
use Erikwang2013\WebmanScout\Command\ImportCommand;
use Erikwang2013\WebmanScout\Command\IndexCommand;
use Erikwang2013\WebmanScout\Command\DeleteAllIndexesCommand;
use Erikwang2013\WebmanScout\Command\QueueImportCommand;
use Erikwang2013\WebmanScout\Command\SyncIndexSettingsCommand;

return [
    IndexCommand::class,
    ImportCommand::class,
    FlushCommand::class,
    DeleteIndexCommand::class,
    DeleteAllIndexesCommand::class,
    QueueImportCommand::class,
    SyncIndexSettingsCommand::class
];
