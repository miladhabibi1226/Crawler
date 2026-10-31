<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\New;

use App\Models\SupplierSession;
use App\TravelServices\Logics\Hotel\Search as HotelSearch;
use Carbon\Carbon;
use stdClass;
use MatinUtils\NodeTracker\Models\City;
use MatinUtils\NodeTracker\Models\Hotel;
use Illuminate\Support\Facades\DB;
use App\Jobs\TBOHotelProfiles;
use MatinUtils\NodeTracker\Models\CitySupplier;

trait Search
{
    use HotelSearch;
    protected $hotelProfile, $allDistricts, $resultsWithNoLocalProfiles;

    public function canBeDone() {}

    protected function getContent()
    {
        // $this->fromFile = true;
        if ($this->fromFile) {
            $this->rawResult[0]['body'] = file_get_contents(storage_path('Samples/TBO/New/Istanbul/search.json'));
            foreach ($this->rawResult as $index => $rawResult) {
                $this->rawResult[$index]['decoded'] = json_decode($rawResult['body'], true);
            }
            lugwarning('Reading the result from file');
            return true;
        }
        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }
        foreach ($this->rawResult as $index => $rawResult) {
            $this->rawResult[$index]['decoded'] = json_decode($rawResult['body'], true);
        }
        foreach ($this->rawResult as $index => $rawResult) {
            $error = $this->searchHaveSupplierResponseError($rawResult);
            if (!empty($error)) {
                unset($this->rawResult[$index]);
            }
        }
    }

    public function requestGenerator()
    {
        $chunkHotelCodes = $this->getHotelsFormDB();
        if ($chunkHotelCodes == false) {
            $this->addError('there is no data for this city in database !');
            return false;
        }
        $this->multi = true;
        $auth = $this->seller->api->configs;
        $depends = $this->queryParams + $auth;
        $username = $auth['username'];
        $password = $auth['password'];
        foreach ($chunkHotelCodes as $chunkHotelCode) {
            $hotelIds = implode(',', $chunkHotelCode);
            $this->call[] = [
                'fields' => json_encode($this->getFields($depends, $hotelIds)),
                'url' => $this->cleanUrlSlash($auth['url']) . '/Search',
                'headers' => [
                    'Authorization: Basic ' . base64_encode("$username:$password")
                ],
                'timeout' => 70,
                'apiMethod' => 'Search',
            ];
        }

        return $this->action();
    }

    protected function extractor()
    {
        foreach ($this->rawResult as $index => $rawResult) {
            $this->rawResult[$index]['decoded'] = json_decode($rawResult['body'], true);
        }
        foreach ($this->rawResult  as $thisRawResults) {
            if (!empty($thisRawResults['decoded']['HotelResult'])) {
                $rawResults[] = $thisRawResults['decoded'];
            }
        }
        //check empty items
        if (empty($rawResults)) {
            lugInfo('rawResults : ', [$rawResults]);
            $this->addError('Empty Items');
            return;
        }
        //checking currency
        if (!$this->checkCurrency($rawResults)) {
            $this->addError('Currency Mismatch');
            return;
        }
        $this->destinationCity = $this->queryParams['destination_city'];
        $this->extractSupplierCodes($rawResults);
        $skippedByexception = 0;
        //checking hotel profiles
        foreach ($rawResults as $key => $rawResult) {
            foreach ($rawResult['HotelResult'] as $hotel) {
                $hotelCode = $hotel['HotelCode'];
                if (empty($this->hotelCodes[$hotelCode]['common_profile'])) {
                    $this->resultsWithNoLocalProfiles[] = ['supplierKey' => $hotelCode, 'hotel' => $hotel];
                }
            }
        }
        $this->getNewHotelProfiles();
        foreach ($rawResults as $key => $rawResult) {
            foreach ($rawResult['HotelResult'] as $hotel) {
                $hotelCode = $hotel['HotelCode'];
                $this->hotelProfile = $this->hotelCodes[$hotelCode]['common_profile'] ?? null;
                if (empty($this->hotelProfile)) {
                    lugWarning('empty hotel_profile for this hotel', [$hotel, $this->hotelCodes]);
                    continue;
                }
                $item = [];
                $item['service'] = $this->hotelModifier($hotel);
                $conditions = $this->getPricingConditions($item['service']);
                if (!$this->checkAccessExceptions($conditions, $this->getAccessExceptions())) {
                    $skippedByexception++;
                    continue;
                }
                $totalFares = array_column($hotel['Rooms'], 'TotalFare');
                $minTotalFare = min($totalFares);
                $item['price'] = $this->priceModifier(['totalFare' => $minTotalFare], $conditions);
                $item['seller'] = [
                    'sellerCode' => $this->seller->abb,
                    'hotelCode' => $hotelCode,
                    'passengersForm' => $this->getPassengersForm(),
                    'rooms' => $hotel['Rooms'] ?? null,
                    'seller_id' => $this->seller->id,
                    'afters' => [
                        'availableRooms' => false,
                    ],
                    'beforeBooks' => [
                        'availabilityAndPricing' => false
                    ],
                    'createBooks' => [
                        'getRoomInformation' => false
                    ]
                ];
                $this->items[] = $item;
            }
        }

        lugInfo('S>>>> TBO Extractor items - ' . $this->seller->alias, ['New hotels' => $this->hotelSuppliers, 'skippedByexception' => $skippedByexception, 'Result count' => count($this->items)]);
    }

    protected function getNewHotelProfiles()
    {
        if (!empty($this->resultsWithNoLocalProfiles)) {
            dispatch(new TBOHotelProfiles($this->queryParams, $this->resultsWithNoLocalProfiles,  $this->hotelCodes, $this->seller, $this->dataReference))->onConnection('sync');
            $this->repelSingleMode($this->resultsWithNoLocalProfiles);
            foreach ($this->resultsWithNoLocalProfiles as $key => $resultsWithNoLocalProfile) {
                $this->repelSingleMode($resultsWithNoLocalProfile);
                $newHotels = array_merge($newHotels ?? [], array_values(array_column($resultsWithNoLocalProfile, 'supplierKey')));
            }
            $newHotels = $this->loadHotels($newHotels);
            foreach ($newHotels as $key => $newHotel) {
                $this->hotelCodes[$key] = $newHotel;
            }
        }
    }

    protected function hotelMapper($hotel)
    {
        $thumbnail =  $this->hotelProfile['thumbnail'];
        $newHotel = new stdClass;
        $newHotel->info = [
            'title' => $this->hotelProfile['title'],
            'property_type' => 0, ///> todo: #46
            'star' => $this->hotelProfile['rating'] ?? $this->star['All'],
            'tripadvisor_rating'  => $hotel['HotelInfo']['TripAdvisorRating'] ?? 0,
            'districts' => $hotel['HotelInfo']['Attractions'] ?? '',
            'thumbnail' => $thumbnail,
            'details' => $this->hotelProfile['description'],
            'tripadvisor_review_url' => $hotel['HotelInfo']['TripAdvisorReviewURL'] ?? '#',
            'phone' => $this->hotelProfile['phone'] ?? '-',
            'rooms' => $hotel['Rooms'] ?? null,
            'address' => $this->hotelProfile['address']['address'] ?? []
        ];
        $newHotel->hotelId = $this->getHotelId($hotel['HotelCode'], $this->hotelProfile['title']);
        return $newHotel;
    }


    protected function getHotelsFormDB()
    {
        $supplier_key = CitySupplier::where('city_id', $this->queryParams['destination_city']['id'])
            ->where('data_reference', $this->dataReference)
            ->pluck('supplier_key')
            ->first();
        if (empty($supplier_key)) return false;
        $hotelRecords = DB::connection('siding')->table('TBO_hotels')
            ->where('city_id', $supplier_key)
            ->first()->hotel_ids;
        if (empty($hotelRecords)) return false;
        $hotelIds = json_decode($hotelRecords, true);
        $chunks = array_chunk($hotelIds, 100);
        return $chunks;
    }

    protected function getFields($depends, $hotelCodes)
    {
        foreach ($depends['rooms_detail'] as $rooms_detail) {
            $childrenAges = [];
            if (isset($rooms_detail['child-age'])) {
                foreach ($rooms_detail['child-age'] as $childAge) {
                    $childrenAges[] = $childAge;
                }
            }
            $paxRooms[] = [
                "Adults" => $rooms_detail['adults'],
                "Children" => $rooms_detail['children'],
                "ChildrenAges" => $childrenAges
            ];
        }
        $data = [
            "CheckIn" => $depends['checkin'],
            "CheckOut" => $depends['checkout'],
            "HotelCodes" => $hotelCodes,
            "GuestNationality" => $depends['nationality_country']['abb'],
            "PaxRooms" => $paxRooms,
            "IsDetailedResponse" => true,

        ];
        return $data;
    }

    protected function priceMapper($price)
    {
        $newPrice = new stdClass;
        $newPrice->price = [
            'totalPrice' => $price['totalFare']
        ];
        return $newPrice;
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'age']
        ];
        return $this->addPassengerFormItem($items);
    }

    protected function extractSupplierCodes($rawResults)
    {
        foreach ($this->rawResult as $key => $rawResult) {
            preg_match_all('/"HotelCode"\s*:\s*"(\d+)"/', $rawResult['body'], $response);
            $this->hotelCodes = array_unique(array_merge($this->hotelCodes ?? [], $response[1]));
            $this->forFrequesncy[] = $response[1];
        }
        $this->hotelCodes = $this->loadHotels($this->hotelCodes);
    }

    protected function checkCurrency($rawResults)
    {
        $currency = $rawResults[0]['HotelResult'][0]['Currency'] ?? null;
        if ($currency != $this->seller->currency->abb) {
            lugError('Currency Mismatch', ['sellerCurrency' => $this->seller->currency, 'supplierResponseCurrency' =>  $currency ?? null]);
            return false;
        }
        return true;
    }

    protected function searchHaveSupplierResponseError($rawResult)
    {
        $responseStatus = $rawResult['decoded']['Status'] ?? null;
        if ($responseStatus['Code'] != 200) {
            lugWSResError("TBO New Version response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $rawResult, 'supplier response: ' => $rawResult['body']]);
            $this->supplierConversaion($responseStatus['Description'] ?? $responseStatus);
            return $rawError["Description"] ?? $responseStatus;
        }
        if ($rawResult['http_code'] != 200) {
            lugWSResError("TBO New Version response is invalid ({$this->seller->alias})", ['rawResult' => $rawResult]);
            $this->supplierConversaion('invalid response status: ' . $rawResult['http_code']);
            return 'TBO New Version response is invalid';
        }
        $this->supplierConversaion();
        return false;
    }
}
