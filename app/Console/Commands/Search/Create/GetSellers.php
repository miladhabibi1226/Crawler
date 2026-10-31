<?php

namespace App\Console\Commands\Search\Create;

use App\Models\SearchQuery;
use Illuminate\Console\Command;

class GetSellers extends Command
{
    use Helpers;
    protected $signature = 'getSellers {sessionId : Session Id} {nodeId? : NodeId}';
    public function handle()
    {
        $this->handleNode();
        $searchQuery = SearchQuery::whereSessionId($this->argument('sessionId'))->first();
        $this->findSellers($searchQuery);
    }
}
