<?php

namespace App\Console\Commands\schedule;

use App\Models\SearchQuery;
use Carbon\Carbon;
use Illuminate\Console\Command;
use MatinUtils\NodeTracker\Models\AirPort;

class AirportFrequency extends Command
{
    protected $signature = 'airport-frequency';
    protected $description = 'Calculates the number of searches that was done on airports and adds to airport\'s frequency';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        app('log-system')->setPid('airportFrq-' . uniqid());

        $airports = [];
        foreach (allNodeIds() as $node) {
            switchNode($node);
            $searchQueries = SearchQuery::whereType(types('travelService', 'flight'))
                ->whereDate('created_at', '>', Carbon::yesterday())->select('query_params', 'requested_by_node')->get();

            foreach ($searchQueries as $searchQuery) {
                if ($searchQuery->requested_by_node) {
                    ///> to avoid counting an airport multiple times for the same search (api-out)
                    continue;
                }
                $queryParams = $searchQuery->query_params;
                $origin = $queryParams['origin']['airport']['abb'] ?? null;
                if (!empty($origin)) {
                    $airports[$origin] = ($airports[$origin] ?? 0) + 1;
                }

                $destination = $queryParams['destination']['airport']['abb'] ?? null;
                if (!empty($destination)) {
                    $airports[$destination] = ($airports[$destination] ?? 0) + 1;
                }
            }
        }
        lugInfo('Extracted airports', [$airports]);

        foreach ($airports as $abb => $number) {
            $ap = AirPort::whereAbb($abb)->first();
            $ap->frequency = $ap->frequency + $number;
            $ap->update();
        }
        $this->info('Done.');
        app('log-system')->send();
    }
}
