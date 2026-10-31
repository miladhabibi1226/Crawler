<?php

namespace App\Console\Commands\schedule;

use Carbon\Carbon;
use Illuminate\Console\Command;

class MissedLugs extends Command
{
    protected $signature = 'missedLugs';
    protected $description = '';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $pid = 'missedLugs-' . uniqid();
        app('log')->info($pid);
        app('log-system')->setPid($pid);
        $path = storage_path('logs/misseLugs/' . Carbon::yesterday()->format('y-m-d'));
        if (!file_exists($path)) return;
        $files = scandir($path);
        lugInfo("missedLug files", [$files]);
        foreach ($files ?? [] as $file) {
            if (in_array($file, ['.', '..'])) continue;
            $lug = json_decode(file_get_contents("$path/$file"), true);
            $luPid = explode(':', $file);
            app('log-system')->setPid($luPid[0]);
            lug($lug['type'] ?? 'info', $lug['message'] ?? 'cant get missed lug message', $lug['data'] ?? [], $lug['time'] ?? null);
            unlink("$path/$file");
        }
        app('log-system')->setPid($pid);
        rmdir($path);
    }
}
