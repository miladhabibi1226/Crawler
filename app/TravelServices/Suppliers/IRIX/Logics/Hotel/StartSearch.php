<?php

namespace App\TravelServices\Suppliers\IRIX\Logics\Hotel;

use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\CitySupplier;
use MatinUtils\NodeTracker\Models\CountrySupplier;

trait StartSearch
{
    protected $IRIXSupplierConnection;

    protected $cityId;
    public function canBedone()
    {
        $this->authorizeRequest();
        if (!$this->translateQueryParams()) {
            ///> error message sets in translateQueryParams()
            return;
        }
    }

    public function requestGenerator()
    {
        $this->LugTheResponse = 0;
        $this->LugTheRequest = 0;
        $depends = $this->queryParams + $this->seller->api->configs;
        lugInfo("IRIX Search Start Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'method' => "POST",
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/hotels/v1/search/start',
            'timeout' => 10,
            'apiMethod' => 'SearchStart'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        if (empty($this->rawResult['decoded']['tokens'])) {
            lugError('start search empty result', [$this->rawResult]);
            return $this->addError('start search empty result');
        }
        $progressKey = $this->rawResult['decoded']['tokens']['progress'];
        $this->items = ['srkToken' => $this->rawResult['decoded']['srk'], 'progressToken' => $this->rawResult['decoded']['tokens']['progress'], 'resultsToken' => $this->rawResult['decoded']['tokens']['results']];
        $startTime = Carbon::now();
        $waitingTime = $tries = 0;
        do {
            $tries++;

            $this->LugTheRequest = ($tries == 1);
            $this->LugTheResponse = ($tries == 1);

            if ($this->checkProgressRequestGenerator($progressKey) === false) {
                $this->addError("Supplier connection error");
                return false;
            }
            $this->manageSupplierConnectionData();

            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            $error = $this->haveSupplierResponseError();
            if (!empty($error)) {
                $this->supplierConversaion(($rawError['message'] ?? 'Look At Response') . ' - ' . ($rawError['details'] ?? ''));
                $this->addError($error);
                return false;
            }
            if ($this->rawResult['decoded']['status'] == 'COMPLETED') break; //> putting this here to avoid unnessesary sleeping

            sleep(3);
            $waitingTime = Carbon::now()->diffInSeconds($startTime);
        } while ($waitingTime <= 70);
        ///> check progress
        if ($this->rawResult['decoded']['status'] != 'COMPLETED') {
            lugWarning('IRIX Search Progress was not 100%', ['status' => $this->rawResult['decoded']['status'], 'progress' => $this->rawResult['decoded']['progress']]);
            return $this->addError('Search Progress was not 100%');
        }
        if ($this->rawResult['decoded']['countOffers'] < 1) {
            lugWarning('IRIX Search Progress countOffers is less than 1 ', ['countOffers' => $this->rawResult['decoded']['countOffers']]);
            return $this->addError('Search Progress countOffers is less than 1');
        }
        $this->items['hotelCounts'] = $this->rawResult['decoded']['countHotels'];
        $this->items['cityId'] = $this->cityId;
        lugInfo('S>>>> IRIX Hotel search start Extractor items - ' . $this->seller->alias, ['items' => $this->items]);
    }

    protected function getFields($depends)
    {
        $roomDetail = [];
        $this->cityId = $depends['destination_city']['supplier_key'];
        foreach ($depends['rooms_detail'] as $room) {
            $childrenAges = [];
            foreach ($room['child-age'] ?? [] as $childAge) {
                array_push($childrenAges, $childAge);
            }
            $roomDetail[] = [
                "adults" => $room['adults'],
                "childrenAges" => $childrenAges ?? []
            ];
        }
        $fields = [
            "destination" => [
                "city" => [
                    "id" => $depends['destination_city']['supplier_key']
                ]
            ],
            "checkIn" => $depends['checkin'],
            "checkOut" => $depends['checkout'],
            "occupancy" => [
                "leaderNationality" => $depends['nationality_country']['supplier_key'],
                "rooms" => $roomDetail
            ],
            "language" => "en_GB",
            "sellingChannel" => "B2B",
            "timeout" => 50,
            "availableOnly" => true
        ];
        return $fields;
    }

    protected function translateQueryParams()
    {
        $this->queryParams['destination_city']['supplier_key'] = CitySupplier::whereCityId($this->queryParams['destination_city']['id'])->whereDataReference($this->dataReference)->first()->supplier_key ?? [];
        $this->queryParams['nationality_country']['supplier_key'] = CountrySupplier::whereCountryId($this->queryParams['nationality_country']['id'])->whereDataReference($this->dataReference)->first()->supplier_key ?? [];
        if (empty($this->queryParams['destination_city']['supplier_key'])) {
            $this->addError('City is not defined for the supplier');
            return false;
        }
        if (empty($this->queryParams['nationality_country']['supplier_key'])) {
            $this->addError('Country is not defined for the supplier');
            return false;
        }
        return true;
    }

    protected function checkProgressRequestGenerator($progressKey)
    {
        $this->LugTheResponse = 0;
        $this->LugTheRequest = 0;
        $auth = $this->seller->api->configs;
        // lugInfo("IRIX Search progress key ({$this->seller->alias})", [$progressKey]);
        $this->call = [
            'fields' => "",
            'method' => "GET",
            'url' =>  $auth['url'] . '/api/hotels/v1/search/progress?token=' . $progressKey,
            'timeout' => 10,
            'apiMethod' => 'SearchProgress'
        ];
        return $this->action();
    }

    protected function manageSupplierConnectionData()
    {
        $this->IRIXSupplierConnection = [
            'supplier_service_id' => $this->supplierConnection['supplier_service_id'],
            'pid' => $this->supplierConnection['pid'],
            'method' => $this->supplierConnection['method'],
            'start_time' => $this->IRIXSupplierConnection['start_time'] ?? $this->supplierConnection['start_time'],
            'total_time' => ($this->IRIXSupplierConnection['total_time'] ?? 0) + $this->supplierConnection['total_time']
        ];
        $this->supplierConnection = $this->IRIXSupplierConnection;
    }

    protected function done()
    {
        return ['status' => true, 'items' => $this->items];
    }
}
