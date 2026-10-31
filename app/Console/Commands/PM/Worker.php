<?php

namespace App\Console\Commands\PM;

use MatinUtils\ProcessManager\Commands\Worker as ProcessManagerWorker;

class Worker extends ProcessManagerWorker
{
    protected function processOver($input)
    {
        if (!parent::processOver($input)) {
            return false;
        }
        app('log-system')->closeSocket();
        // app('matin-cacher')->closeSocket();
        return true;
    }
}
