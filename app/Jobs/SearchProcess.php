<?php

namespace App\Jobs;

use App\Jobs\Job;
use Carbon\Carbon;

class SearchProcess extends Job
{
    protected $pid, $searchQueryId, $searchStartTime, $timestamp, $nodeId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $pid, int $searchQueryId, $searchStartTime)
    {
        $this->pid = $pid;
        $this->searchQueryId = $searchQueryId;
        $this->searchStartTime = $searchStartTime;
        $this->timestamp = Carbon::now();
        $this->nodeId = node()->id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function operate()
    {
        app('log-system')->setPid($this->pid);
        $items = app('db')->connection('common')->table('jobs')->where('queue', "SP:$this->searchQueryId")->get();
        $searchData = [];
        if (empty($items)) {
            lugWarning('empty search process items');
            return;
        }
        foreach ($items ?? [] as $item) {
            $data = json_decode($item->payload, true);
            $searchData[$data['sellerId']][$data['step']] = $data + ['timestamp' => $item->created_at];
        }
        foreach ($searchData as $sellerId => $sellerData) {
            if (empty($sellerData['start']) || empty($sellerData['end'])) {
                $unsuppoerted[] = $sellerData;
                continue;
            }
            $insertables[] = [
                'search_query_id' => $this->searchQueryId,
                'seller_id' => $sellerId,
                'route' => $sellerData['start']['route'],
                'travel_service' => $sellerData['start']['travel_service'],
                'pid' => $this->pid,
                'created_at' => Carbon::createFromTimestamp($sellerData['start']['timestamp']),
                'status' => $sellerData['end']['status'],
                'result_count' => $sellerData['end']['result_count'],
                'api_process' => $sellerData['end']['api_process'],
                'supplier_process' => !$sellerData['end']['status'] ? null : round(Carbon::createFromTimestamp($sellerData['start']['timestamp'])->diffInMicroseconds(Carbon::createFromTimestamp($sellerData['end']['timestamp'])) / 1000000, 2),
                'search_process' => round($this->timestamp->diffInMicroseconds($this->searchStartTime) / 1000000, 2)
            ];
        }
        if (!empty($unsuppoerted)) {
            lugWarning('some search process items were missing sata', [$unsuppoerted, $items]);
        }
        app('db')->connection('common')->table('jobs')->where('queue', "SP:$this->searchQueryId")->delete();
        switchNode($this->nodeId);
        try {
            app('db')->table("search_process")->insert($insertables ?? []);
        } catch (\Throwable $th) {
            app('log')->info("can not insert search_process in job. ". $th->getMessage());
        } 
    }
}
