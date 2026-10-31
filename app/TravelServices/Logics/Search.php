<?php

namespace App\TravelServices\Logics;

use App\Jobs\SearchProcess;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use stdClass;

trait Search
{
    use PassengerInfoCreator;
    protected $queryParams, $searchValidity = null, $defaultSearchValidity = null, $fromSavedResponse = false, $accessListExceptions;
    protected $supplierSearchValidity = 'default', $minimumDefaultSearchValidity = 12; ///> minimumDefaultSearchValidity is only for the default value. sone suppliers may allow less than this, and That will be applied

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->LugTheResponse = false;
        $this->prepareTravelServiceData();
        $this->defaultSearchValidity = $this->defaultSearchValidity();
        $this->fromSavedResponse = $this->readFromSavedResponse();
    }

    protected function prepareTravelServiceData() {}

    protected function defaultSearchValidity()
    {
        ///> right now only flight has it
    }

    abstract protected function getContent();

    abstract protected function extractor();

    protected function getQueryParams()
    {
        return $this->queryParams;
    }

    protected function searchValidityDeadline()
    {
        $supplierSearchValidity = $this->supplierSearchValidity == 'default' ? $this->defaultSearchValidity : $this->supplierSearchValidity;
        return Carbon::now()->addMinutes($supplierSearchValidity < $this->defaultSearchValidity ? $supplierSearchValidity : $this->defaultSearchValidity);
    }

    protected function done()
    {
        $this->supplierSearchCompeleted();
        return  ['status' => true];
    }

    protected function failed($error = null)
    {
        $this->supplierSearchCompeleted();
        return  ['status' => false];
    }

    protected function supplierSearchCompeleted()
    {
        $this->updateSearchProcessTable();
        $this->reduceRemainingLicence();
        if ($this->areAllSuppliersCompeleted()) {
            $this->searchCompeleted();
        }
    }

    protected function reduceRemainingLicence()
    {
        removeCache("searchProgress.{$this->object->session_id}.sellers.{$this->seller->id}");
    }

    protected function areAllSuppliersCompeleted()
    {
        $sessionId = $this->object->session_id;
        $cached = getCachedItem("searchProgress.$sessionId");
        $remainLicence =  count($cached['sellers'] ?? [1]); ///> empty($cached['status']) means the cacher system is not available, or was not available when search started
        return $remainLicence == 0;
    }

    protected function searchCompeleted()
    {
        if (nodeConfigs('flightSearchDisplayMode') == 'lowestPrice') {
            $this->removeDuplicates();
        }
        dispatch(new SearchProcess(app('log-system')->getPid(), $this->object->id, $this->object->created_at))->onQueue('SearchProcess');
    }

    protected function updateSearchProcessTable()
    {
        if ($this->object->searcher_identity != 'landingPage') { ///> haven't inserted the record if it's for landingPage
            $time = Carbon::now()->getTimestamp();
            app('db')->connection('common')->table('jobs')->insert([
                'queue' => "SP:{$this->object->id}",
                'attempts' => 0,
                'available_at' => $time,
                'created_at' => $time,
                'payload' => json_encode([
                    'step' => 'end',
                    'sellerId' => $this->seller->id,
                    'result_count' => count($this->items ?? []),
                    'status' => $this->processStatus,
                    'api_process' => empty($this->supplierConnection['total_time']) ? null : round($this->supplierConnection['total_time'], 2)
                ])
            ]);
        }
    }

    protected function calculatePriceforEachItem($items)
    {
        if (empty($items)) return $items;
        $priceCalculatorata = [
            'items' => array_column($items, 'price_info'),
            'searchQueryId' => $this->object->id,
            'queryParams' => $this->queryParams,
            'b2bBuyerGroupId' => $this->object->buyer_group_id,
            'b2cBuyerGroupId' => nodeConfigs('default_buyer_group_id'),
            'seller' => $this->sellerForPriceCalculator(),
            'nodeCurrency' => node()->currency,
            'exchanges' => $this->getExchanges(),
            'buyerGroupSellers' =>  [
                $this->object->buyer_group_id => $this->getBuyerGroupSeller($this->object->buyer_group_id, true, false), ///> for b2b buyer group the bgs can not be empty
                node()->website_config['default_buyer_group_id'] => $this->getBuyerGroupSeller(node()->website_config['default_buyer_group_id'], true)
            ]
        ];
        $itemsPriceInfo = dataFromMSS('priceCalculator/supplier/' . $this->travelService, $priceCalculatorata);
        // lugWarning(__FUNCTION__ , [$itemsPriceInfo]);
        if (empty($itemsPriceInfo)) {
            $this->addError('Pricing Info Unavailable');
            return [];
        }
        return $this->addPriceInfoToBufferData($items, $itemsPriceInfo);
    }

    protected function sellerForPriceCalculator()
    {
        $nodeId = node()->id;
        $this->seller->currency; ///> to load the currency object in seller. wil use cache when the currencies tables get moved to the cache
        $this->seller->exceptions = cachedDatabase("{$nodeId}_pricing_exceptions", [['entity_id', '=', $this->seller->id], ['entity_type', '=', $this->seller->getMorphClass()]]);
        return $this->seller;
    }

    protected function addPriceInfoToBufferData($bufferData, $pluginResponse) ///> re-written in local flights
    {
        if (empty($pluginResponse)) {
            return [];
        }
        foreach ($pluginResponse as $key => $priceInfo) {
            $bufferData[$key]['price_info'] = json_encode($priceInfo);
            $bufferData[$key]['total_b2b'] = $priceInfo['marketplacePrice']['payable'];
            $bufferData[$key]['total_b2c'] = $priceInfo['marketplacePrice']['b2c'];
        }
        lugInfo("Price calculations for reference ==> {$this->seller->alias}", [$priceInfo]);
        return $bufferData;
    }

    protected function readFromSavedResponse()
    {
        $date = Carbon::now()->format('y-m-d');
        if (!$this->keepResponses() || !Schema::connection('supplierCache')->hasTable("{$date}_{$this->supplierName}")) return false;
        $time = env('SAVE_SUPPLIER_RESPONSE', 300);
        $supplierCacheKey = $this->supplierCacheKey();
        $fromDB = app('db')->connection('supplierCache')->table("{$date}_{$this->supplierName}")->where('key', $supplierCacheKey)->where('node_id', node()->id)
            ->where('seller_id', $this->seller->id)->where('created_at', '>', Carbon::now()->subSeconds($time))->first();
        if (empty($fromDB)) return false;
        $this->items = json_decode($fromDB->value, true);
        $fromDB->value = '1'; ///> for log
        lugInfo("Reading Saved Response --> $this->supplierName - {$this->seller->alias}", ['supplierCacheKey' => $supplierCacheKey, 'fromDB' => $fromDB, 'time condition' => Carbon::now()->subSeconds($time)]);
        return true;
    }

    protected function saveItemsToFile()
    {
        if (!$this->fromSavedResponse && $this->keepResponses()) {
            ///> this result is from supplier's direct response. we are going to save it into file.
            ///> first, we'll mark the items in their seller info for future use
            foreach ($this->items as $key => $item) {
                $this->items[$key]['sellerInfo']['fromSavedResponse'] = true;
            }
            if ($this->createCacheTable()) {
                try {
                    $date = Carbon::now()->format('y-m-d');
                    app('db')->connection('supplierCache')->table("{$date}_{$this->supplierName}")->insert([
                        'node_id' => node()->id,
                        'seller_id' => $this->seller->id,
                        'key' => $this->supplierCacheKey(),
                        'pid' => app('log-system')->getPid(),
                        'created_at' => Carbon::now(),
                        'value' => json_encode($this->items),
                    ]);
                } catch (\Throwable $th) {
                    lugError(" failed. " . $th->getMessage(), ['exceptionLine' => $th->getFile() . ' line: ' . $th->getLine()]);
                }
            }
        }
    }

    protected function supplierCacheKey()
    {
        ///> if you change this make sure to change the key in expireSupplierCache() as well.
        ///> can't use this function there bc of the modifiedPassengersTypeCount which is different in search step compared to other steps like target search
        $supplierVersion = $this->seller->api->configs['version'] ?? 'NAN';
        $text = '';
        foreach ($this->queryParams['legs'] as $leg) {
            $text .= "{$leg['origin']['buffer']['id']}_{$leg['destination']['buffer']['id']}_{$leg['departure']}_";
        }
        $cab = $this->queryParams['cabin'][0];
        return "{$supplierVersion}_$text{$cab}_{$this->modifiedPassengersTypeCount['adults']}_{$this->modifiedPassengersTypeCount['children']}_{$this->modifiedPassengersTypeCount['infants']}";
    }

    protected function createCacheTable()
    {
        $date = Carbon::now()->format('y-m-d');
        try {
            $tableName = "{$date}_{$this->supplierName}";
            if (!Schema::connection('supplierCache')->hasTable($tableName)) {
                Schema::connection('supplierCache')->create($tableName, function (Blueprint $table) {
                    $table->engine = 'InnoDB';
                    $table->bigIncrements('id');
                    $table->unsignedInteger('node_id');
                    $table->unsignedInteger('seller_id');
                    $table->string('key');
                    $table->string('pid');
                    $table->longText('value');
                    $table->string('created_at');

                    $table->index(['node_id', 'seller_id', 'key', 'created_at']);
                });
            }
            return true;
        } catch (\Throwable $th) {
            lugError("createCacheTable failed. " . $th->getMessage(), ['exceptionLine' => $th->getFile() . ' line: ' . $th->getLine()]);
            return false;
        }
    }

    protected function keepResponses()
    {
        //> $this->cacheAllowed depends on each supplier.
        return ($this->action == 'search') && $this->cacheAllowed && ($this->travelService == 'flight') && (env('SAVE_SUPPLIER_RESPONSE', 300) > 1);
    }

    protected function saveRawResponse($ext = 'json')
    {
        if (($this->action == 'search') && env('SAVE_RAW_RESPONSE', true)) {
            try {
                $text = '';
                foreach ($this->queryParams['legs'] as $leg) {
                    $text .= "{$leg['origin']['buffer']['id']}_{$leg['destination']['buffer']['id']}_{$leg['departure']}_";
                }
                $date = Carbon::now()->format('y-m-d');
                $hour = Carbon::now()->format('H');
                $seller = node()->id . "/" . $this->seller->id;
                $cab = $this->queryParams['cabin'][0];
                $index = "{$hour}_$text{$cab}_{$this->modifiedPassengersTypeCount['adults']}_{$this->modifiedPassengersTypeCount['children']}_{$this->modifiedPassengersTypeCount['infants']}";
                $filepath =  "home/crawler/rawResponses/$date/{$this->supplierName}/$seller/$index"; ///> save in home/crawler/rawResponses/ TS1801
                if (!file_exists($filepath)) {
                    mkdir($filepath, 0777, true);
                    $rawResults = $this->rawResult;
                    if (!$this->multi) $this->repelSingleMode($rawResults);
                    foreach ($rawResults as $key => $rawResult) {
                        $filename = str_replace('/', '_', $key);
                        file_put_contents("$filepath/res$filename.$ext", $rawResult['body']);
                    }
                }
            } catch (\Throwable $th) {
                lugError($th->getMessage(), ['exceptionLine' => $th->getFile() . ' line: ' . $th->getLine()]);
            }
        }
    }

    protected function modifyPassengerCount()
    {
        ///> this applys only to flight search actions for suppliers other than local and quantum, that have availableSeats.
        return ($this->action == 'search') && ($this->travelService == 'flight') && $this->supplierSupportsPassengerCountModification();
    }

    protected function getPricingConditions($serviceInfo) ///> local package doesn't use this
    {
        return $this->{"{$this->travelService}PricingConditions"}($serviceInfo) + [
            'luxotaCodes' => $serviceInfo['luxotaCode']
        ];
    }

    protected function getAccessExceptions()
    {
        if ($this->supplierName == 'local' && !in_array($this->travelService, ['flight', 'hotel'])) {
            ///> for now we don';t have it for local items except for flight and hotel
            return [];
        }
        if (empty($this->accessListExceptions)) {
            $accessListExceptions = $this->seller->accessExceptions()->get();
            $this->accessListExceptions = count($accessListExceptions) ? $accessListExceptions : 'NAN';
        }
        return $this->accessListExceptions == 'NAN' ? [] : $this->accessListExceptions;
    }

    protected function checkSystemExceptions($pricingConditions)
    {
        ///> these are the exceptions applied by system requirement or PO's request
        ///> for example openreturn flights in kplus are only available for iraqi Airways airline so we need to skip other airlines
        ///> using a stdClass to create a structure like an AcessException so we can use the compareConditions function that we already have
        $exception = new stdClass();
        $exception->conditions = $this->getSystemExceptions();
        return $this->compareConditions($pricingConditions, $exception);
    }

    protected function checkAccessExceptions($pricingConditions, $exceptions)
    {
        foreach ($exceptions ?? [] as  $exception) {
            if ($this->compareConditions($pricingConditions, $exception)) {
                return !$this->seller->is_allow_all;
            }
        }
        return $this->seller->is_allow_all;
    }

    protected function compareConditions($resultConditions, $exception)
    {
        foreach ($exception->conditions as $item => $exceptionCondition) {
            switch ($item) {
                case 'luxotaCodes':
                    if (!str_contains($exceptionCondition, $resultConditions[$item])) {
                        return false;
                    }
                    break;
                case 'from':
                    if (($resultConditions['departureDate'] ?? null) < $exceptionCondition || (!empty($resultConditions['returnDate']) && $resultConditions['returnDate'] < $exceptionCondition)) {
                        return false;
                    }
                    break;
                case 'to':
                    if (($resultConditions['departureDate'] ?? null) > $exceptionCondition || (!empty($resultConditions['returnDate']) && $resultConditions['returnDate'] > $exceptionCondition)) {
                        return false;
                    }
                    break;
                default:
                    if (($resultConditions[$item] ?? null) != $exceptionCondition) {
                        return false;
                    }
                    break;
            }
        }
        return true;
    }

    protected function getSystemExceptions()
    {
        ///> if there are requirements for any supplier they will re-qrite this
        ///> right now kplus and CloudFares have it
        return [];
    }

    protected function removeDuplicates()
    {
        ///> right now only flight has it. so it's over-written in the flight search trait
    }
}
