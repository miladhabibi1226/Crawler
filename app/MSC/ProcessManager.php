<?php

namespace App\MSC;

use MatinUtils\ProcessManager\ProcessManager as MainProcessManager;

class ProcessManager extends MainProcessManager
{
    protected function handleTaskMessages($clientConnection, $input)
    {
        $tasks = [];
        foreach (app('easy-socket')->seperateMessageGroup($input) as $inputMessage) {
            ///> client sends the number of requested tasks in the begining of the message, we have to check if we cn handle it 
            preg_match('/^(\d+)(.*?)$/', $inputMessage, $output_array);
            if (empty($output_array[1]) || empty($output_array[2])) {
                $clientConnection->writeOnSocket('unavailable');
                try {
                    socket_getpeername($clientConnection->getSocket(), $address, $port);
                    app('log')->info("Invalid input from $address:$port => $inputMessage");
                } catch (\Throwable $th) {
                    app('log')->info("Invalid input => $inputMessage");
                    app('log')->info($th->getMessage());
                }
                continue;
            }
            $numberOfTAsks = $output_array[1];
            $tasksCleaned = app('easy-socket')->cleanData($output_array[2]);
            if (($this->availableWorkers + config('processManager.tasksOverload', 0)) < $numberOfTAsks) {
                app('log')->info("tasksOverload availableWorkers: $this->availableWorkers. numberOfTAsks: $numberOfTAsks. inputMessage: $inputMessage");
                $clientConnection->writeOnSocket('unavailable');
                continue;
            }
            $clientConnection->writeOnSocket('inProgress');
            $clientConnection->responseReceived();
            $tasks = json_decode($tasksCleaned, true);
        }
        return $tasks;
    }
}
