<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    public function boot()
    {
        Relation::morphMap([
            'flight' => 'App\Models\Airline',
            'hotel' => 'App\Models\Hotel'
        ]);
        $this->queryLog();
    }

    protected function queryLog()
    {
        if (env('QUERY_LOGS')) {
            app('db')->listen(function ($query) {
                preg_match_all('/(\w+).*?(from|into)? `(.*?)`/', $query->sql, $output_array);
                try {
                    $path = explode('/', base_path());
                    lugInfo("QUERY LOGS: {$output_array[1][0]} - {$output_array[3][0]}", [
                        'table' => $output_array[3][0],
                        'action' => $output_array[1][0],
                        'query' => $query->sql,
                        'duration' => $query->time,
                        'source' => end($path)
                    ]);
                } catch (\Throwable $th) {
                    app('log')->info("error in getting query log. " . $th->getMessage());
                    app('log')->info($query->sql);
                }
                // 
                // app('files')->append(
                //    '/var/www/Local/query.log',
                //     '[' . date('Y-m-d H:i:s') . ']' . PHP_EOL . $query->sql . PHP_EOL
                // );
            });
        }
    }
}
