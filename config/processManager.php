<?php

return [
    'numOfProcess' => env('PRCS_MNGR_PROCESS_NUMBER', 20),
    'clientPort' => env('PRCS_MNGR_SOCKET_CLIENT_PORT', 'client'),
    'workerPort' => env('PRCS_MNGR_SOCKET_WORKER_PORT', 'worker'),
    'tasksOverload' => env('PRCS_MNGR_TASKS_OVERLOAD', 0),
    'maxWorkerRam' => env('PRCS_MNGR_MAX_WOKER_RAM', 200),
];
