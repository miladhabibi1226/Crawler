<?php

namespace App\Jobs;

use App\Jobs\Job;
use MatinUtils\NodeTracker\Models\Seller;

class SepehranSchedule extends Job
{
    protected $result;

    public function operate()
    {
        $this->findSeller();
        if (!empty($this->result)) {
            foreach ($this->result as $flight) {
                $flights[] = implode("_", [$flight["departure"], $flight["arrival"], $flight["actual_departure_date"]]);
            }
            cacheItem('supplier.routes.sepehran', $flights);
        }
    }

    protected function findSeller()
    {
        foreach (allNodeIds() as $nodeId) {
            switchNode($nodeId);
            $sellers = Seller::whereExists(function ($supplierService) {
                $supplierService->from(commonConnectionDBName() . '.supplier_service')
                    ->whereColumn('id', 'supplier_service_id')
                    ->where('supplier', types("supplierBrand", "Sepehran"));
            })->select('id', 'api_id')->with('api')->get();
            foreach ($sellers as $seller) {
                if ($this->sendRequest($seller)) {
                    return true;
                }
            }
        }
    }

    protected function sendRequest($seller)
    {
        $auth = $seller->api->configs;
        if (!empty($auth['url']) && !empty($auth['username']) && !empty($auth['password'])) {
            $options = [
                CURLOPT_URL =>  $url = $auth['url'] . '/api/inventory/FlightSchedule',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_HTTPHEADER => ['Content-Type:application/json', "user:{$auth['username']}", "password:{$auth['password']}", 'Language:en'],
                CURLOPT_POSTFIELDS => json_encode([
                    "from_date" => date('Y-m-d'),
                    "to_date" =>  date('Y-m-d', strtotime('+1 year'))
                ])
            ];
            if (!empty(env('CURL_INTERFACE'))) {
                $options[CURLOPT_INTERFACE] = env('CURL_INTERFACE');
            }
            $curl = curl_init();
            curl_setopt_array($curl, $options);
            $response = curl_exec($curl);
            $err = curl_error($curl);
            if (!empty($err)) {
                lugWarning("CURL Error: $err", ['url' =>  $url]);
                return false;
            }
            $rawResult = json_decode($response, true);
            return $this->result = ($rawResult['data'] ?? false);
        }
        return false;
    }
}
