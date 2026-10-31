<?php

namespace App\TravelServices\Suppliers\GRS\Logics\Hotel;

use App\Jobs\HotelsFrequency;
use App\Jobs\HotelProfiles;
use App\TravelServices\Logics\Hotel\Search as HotelSearch;
use MatinUtils\NodeTracker\Models\CitySupplier;
use MatinUtils\NodeTracker\Models\HotelSupplier;
use stdClass;

trait Search
{
    use HotelSearch;

    protected $latestSession, $resultsWithNoLocalProfile = [], $passengerIsIranian;
    public function canBeDone()
    {
        if (!$this->translateQueryParams()) {
            return;
        }
        return true;
    }

    public function requestGenerator()
    {
        // $this->LugTheResponse = 1;
        $depends = $this->queryParams + $this->seller->api->configs;
        // lugInfo("GRS Search Data ({$this->seller->alias})", [$depends]);
        $params = [
            'city_id' => $depends['destination_city']['supplier_key'],
            'check_in' => $depends['checkin'],
            'check_out' => $depends['checkout'],
            'adults_count' => array_sum(array_column($depends['rooms_detail'], 'adults')),
            'rooms_count' => $depends['rooms_no'],
        ];
        foreach ($depends['rooms_detail'] as $room) {
            $children = array_merge($children ?? [], $room['child-age'] ?? []);
        }
        if (!empty($children)) {
            $params['children'] = $children;
        }
        $this->call = [
            'fields' => '',
            'url' => $this->cleanUrlSlash($depends['url']) . '/v1/suggestion?' . http_build_query($params),
            'method' => 'GET',
            'timeout' => 20
        ];
        return $this->action();
    }

    public function translateQueryParams()
    {
        $this->queryParams['destination_city']['supplier_key'] = CitySupplier::whereCityId($this->queryParams['destination_city']['id'])->whereDataReference(types('supplierDataReference', 'GRS'))->first()->supplier_key ?? [];

        if (empty($this->queryParams['destination_city']['supplier_key'])) {
            $this->addError('City is not defined for the supplier');
            return false;
        }
        return true;
    }

    protected function extractor()
    {
        $suggestions = $this->rawResult['decoded']['value']['suggestions'] ?? [];
        if (empty($suggestions)) {
            return $this->addError('Empty suggestions');
        }

        $this->extractSupplierCodes();
        foreach ($suggestions as $hotel) {
            $hotelCode = $hotel['property_id'];
            if (empty($this->hotelCodes[$hotelCode]['common_profile'])) {
                $this->resultsWithNoLocalProfile[] = ['supplierKey' => $hotelCode, 'hotelId' => $this->getHotelId($hotelCode, $hotel['property_name'])];
            }
        }
        $this->getNewHotelProfiles();
        $this->passengerIsIranian = $this->queryParams['nationality_country']['abb'] == 'IR';
        $skippedByexception = $skippedForChildAge = 0;
        foreach ($suggestions as $hotel) {
            $hotelCode = $hotel['property_id'];
            $this->hotelProfile = $this->hotelCodes[$hotelCode]['common_profile'] ?? null;
            if (empty($this->hotelProfile['extra_data']['children']['max_infant_age'])) {
                $skippedForChildAge++;
                continue;
            }
            if ($pricing = $this->getPricing($hotel['rooms'])) {
                $serviceInfo = $this->hotelModifier($hotel);
                $conditions = $this->getPricingConditions($serviceInfo);
                if (!$this->checkAccessExceptions($conditions, $this->getAccessExceptions())) {
                    $skippedByexception++;
                    continue;
                }
                $this->items[] = [
                    'service' => $serviceInfo,
                    'price' => $this->priceModifier($pricing['minPrice'], $conditions),
                    'seller' => [
                        'hotelCode' => $hotelCode,
                        'sellerCode' => $this->seller->abb,
                        'passengerIsIranian' => $this->passengerIsIranian,
                        'children' => $this->hotelProfile['extra_data']['children'],
                        'modifiedRoomsDetail' => $pricing['requestedRooms'],
                        'searchResult' => ['availableRooms' => $pricing['availableRooms'], 'combinations' => $pricing['combinations']],
                        'passengersForm' => $this->getPassengersForm(),
                        'afters' => [
                            'availableRooms' => false,
                        ]
                    ],
                ];
            }
        }

        if (empty($this->items)) {
            $this->addError('No Insertable Item');
        }
        dispatch(new HotelsFrequency(app('log-system')->getPid(), $this->forFrequesncy, $this->seller->supplierService->data_reference))->onQueue('supplierData');
        lugInfo('S>>>> GRS Hotel search Extractor items - ' . $this->seller->alias, ['supplier raw response items count' => count($suggestions), 'skippedByexception' => $skippedByexception, 'skippedForChildAge' => $skippedForChildAge,  'result with no profile' => count($this->resultsWithNoLocalProfile), 'Inserted count' => count($this->items)]);
    }

    protected function getNewHotelProfiles()
    {
        if (!empty($this->resultsWithNoLocalProfile)) {
            HotelSupplier::upsert($this->hotelSuppliers, ['data_reference', 'supplier_key'], ['hotel_id']);
            $this->hotelSuppliers = [];
            dispatch(new HotelProfiles(app('log-system')->getPid(), 'sync', 'GRS', node()->id, $this->resultsWithNoLocalProfile, $this->seller->supplierService->data_reference, $this->seller->id, $this->dataReference))->onConnection('sync');
            $newHotels = $this->loadHotels(array_column($this->resultsWithNoLocalProfile, 'supplierKey'));
            foreach ($newHotels as $newHotel) {
                $this->hotelCodes[$newHotel['supplier_key']] = $newHotel;
            }
        }
    }

    protected function planPricesReAcceptable($rate_plan)
    {
        $pricesAreAcceptable = true;
        foreach ($rate_plan['prices'] as $priceKey => $priceInfo) {
            $maxInventory = $priceInfo['inventory'] <= ($maxInventory ?? $priceInfo['inventory']) ? $priceInfo['inventory'] : ($maxInventory ?? $priceInfo['inventory']);
            if ($priceInfo['reservation_state'] != "online") {
                $pricesAreAcceptable = false;
            }
        }
        return $pricesAreAcceptable ? $maxInventory : false;
    }

    protected function extractAvailableRooms($resultRooms)
    {
        $rooms = [];
        $index = 1;
        $planNationality = $this->passengerIsIranian ? ['domestic', 'both'] : ['foreign', 'both'];
        $roomsFromProfile = collect($this->hotelProfile['rooms']);
        foreach ($resultRooms as $resultRoom) {
            $roomFromProfile = $roomsFromProfile->where('id', $resultRoom['room_type_id'])->first();
            if (!empty($roomFromProfile) && !$roomFromProfile['out_of_service']) {
                foreach ($resultRoom['rate_plans'] as $planKey => $rate_plan) {
                    if (in_array($rate_plan['nationality'], $planNationality) && ($inventory = $this->planPricesReAcceptable($rate_plan)) && !$this->ratePlanIsForSixHours($rate_plan)) {
                        $rooms[$index] = [
                            'index' => $index,
                            'room_type_id' => $resultRoom['room_type_id'],
                            'rate_plan_id' => $rate_plan['id'],
                            'meal_type_included' => $rate_plan['meal_type_included'],
                            'room_type_capacity' => $resultRoom['room_type_capacity'],
                            'room_type_extra_capacity' => $resultRoom['room_type_extra_capacity'],
                            'totalCapacity' => $resultRoom['room_type_capacity'] + $resultRoom['room_type_extra_capacity'],
                            'inventory' => $inventory,
                            'accept_child' => $roomFromProfile['accept_child'],
                            'nationality' => $rate_plan['nationality'],
                            'cancelable' => $rate_plan['cancelable'],
                            'rawPricing' => $rate_plan['prices']
                        ];
                        $index++;
                    }
                }
            }
        }
        return $rooms;
    }

    protected function ratePlanIsForSixHours($rate_plan)
    {
        ///> some rate plans are only 6 hours stay. we will skip them by client order. task number: TS1258 and TS2576
        //TS2576:
        $checkProfileRatePlan = false;
        $profileRatePlans = json_decode($this->hotelProfile['extra_data']['rate_plans'] ?? '[]', true);
        if (!empty($profileRatePlans[$rate_plan['id']]['max_stay'])) {
            $checkProfileRatePlan = (int) $profileRatePlans[$rate_plan['id']]['max_stay'] === 1;
        }
        // TS1258 :
        $maxStays = array_column($rate_plan['prices'], 'max_stay');
        $maxStayIs1 = count(array_unique($maxStays)) == 1 && $maxStays[0] === 1;
        return $rate_plan['board_type'] === 'room_only' && $maxStayIs1 && $checkProfileRatePlan;
    }

    protected function priceMapper($source)
    {
        $newPrice = new stdClass;
        $newPrice->price = ['totalPrice' => $source['totalPrice']];
        return $newPrice;
    }

    protected function hotelMapper($hotel)
    {
        $rules = '';
        foreach ($this->hotelProfile['extra_data']['rules'] ?? [] as $rule) {
            $rule = $this->applyLang($rule);
            $rules .= "<p>$rule</p>";
        }
        $newHotel = new stdClass;
        $lang = strtolower($this->queryParams['current_lang']);
        $newHotel->info = [
            'title' => $hotel["property_name_$lang"] ?? $hotel['property_name'],
            'property_type' => 0,
            'star' => $this->hotelProfile['rating'] ?? 0,
            'tripadvisor_rating'  => 0,
            'districts' => '',
            'thumbnail' => $this->hotelProfile['thumbnail'],
            'details' => $this->applyLang($this->hotelProfile['description']),
            'tripadvisor_review_url' => '#',
            'phone' =>  '-',
            'address' => $this->applyLang($this->hotelProfile['address']['address'] ?? []),
            'extraData' => [
                'CheckIn' =>  $this->hotelProfile['extra_data']['CheckIn'],
                'CheckOut' =>  $this->hotelProfile['extra_data']['CheckOut'],
                'instructions' => $rules,
            ]
        ];
        $newHotel->hotelId = $this->getHotelId($hotel['property_id'], $hotel['property_name']);
        return $newHotel;
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'nationality']
        ];
        $data = $this->addPassengerFormItem($items);

        if ($this->passengerIsIranian) {
            $data = $this->addPassengerFormItem(['all' => ['nationalId']], $data);
        } else {
            $data = $this->addPassengerFormItem(['all' => ['passport_number']], $data);
        }
        return $data;
    }

    protected function extractSupplierCodes()
    {
        preg_match_all('/"property_id":(\w+)/', $this->rawResult['body'], $response);
        $this->hotelCodes = $this->loadHotels(array_unique($response[1]));
        $this->forFrequesncy = $response[1];
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['response hotelCodes' => $response[1], '$this->hotelCodes' => $this->hotelCodes]);
    }
}
