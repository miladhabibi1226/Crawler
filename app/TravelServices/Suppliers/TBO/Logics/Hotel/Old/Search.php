<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\Old;

use App\Models\SupplierSession;
use App\TravelServices\Logics\Hotel\Search as HotelSearch;
use Carbon\Carbon;
use stdClass;

trait Search
{
    use HotelSearch;
    protected $hotelProfile, $allDistricts;

    public function canBeDone()
    {
        $city = $this->seller->getSupplierCities()->where('city_id', $this->object->query_params['destination_city']['id'])->first();
        if (empty($city)) {
            lugWarning('City is not defined for supplier', ['city' => $this->object->query_params['destination_city']]);
            $this->addError('Invalid City Code');
        }
        return true;
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/TBO/SearchResponse.xml'));
            $xml = new \SimpleXMLElement($this->rawResult['body']);
            $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//s:Body')), true);
            lugwarning('Reading the result from file');
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }

        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//s:Body')), true);
        ($this->supplierSession = SupplierSession::create([
            'session' => $this->rawResult['decoded'][0]['HotelSearchResponse']['SessionId'] ?? $this->callException('empty SessionId'),
            'expire_on' => Carbon::now()->addMinutes($this->sessionLifeTime),
            'seller_id' => $this->seller->id,
            'searcher_identity' => 'Fake_Searcher_Identity',
        ])) ?: $this->callException('can not store in SupplierSession');
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->queryParams + $auth;
        $depends['city_id'] = $this->translateCity();
        // lugInfo("TBO Search Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('TBO.HotelSearch', $depends)->render(),
            'url' => $auth['url'],
            'timeout' => 20,
            'apiMethod' => 'Search'
        ];
        $this->LugTheResponse = false;
        return $this->action();
    }

    protected function extractor()
    {
        $this->destinationCity = $this->queryParams['destination_city'];

        $this->extractSupplierCodes();

        $rawResults = $this->rawResult['decoded'][0]['HotelSearchResponse']['HotelResultList']['HotelResult'] ?? $this->callException('empty HotelResultList');
        //checking currency
        if (!$this->checkCurrency($rawResults)) {
            $this->addError('Currency Mismatch');
            return;
        }

        $this->repelSingleMode($rawResults);
        $skippedByexception = 0;
        foreach ($rawResults as $hotel) {
            $hotelCode = $hotel['HotelInfo']['HotelCode'];
            $this->hotelProfile = $this->hotelCodes[$hotelCode]['common_profile'] ?? null;
            $item = [];
            $item['service'] = $this->hotelModifier($hotel);
            $conditions = $this->getPricingConditions($item['service']);
            if (!$this->checkAccessExceptions($conditions, $this->getAccessExceptions())) {
                $skippedByexception++;
                continue;
            }
            $item['price'] = $this->priceModifier($hotel['MinHotelPrice'], $conditions);
            $item['seller'] = [
                'sellerCode' => $this->seller->abb,
                'session' => $this->fromFile ? 'From_File' : $this->supplierSession->session,
                'hotelCode' => $hotelCode,
                'passengersForm' => $this->getPassengersForm(),
                'resultIndex' => $hotel['ResultIndex'],
                'seller_id' => $this->seller->id,
                'afters' => [
                    'detail' => !empty($this->hotelProfile),
                    'availableRooms' => false,
                ],
                'beforeBooks' => [
                    'availabilityAndPricing' => false
                ]
            ];
            $this->items[] = $item;
        }

        lugInfo('S>>>> TBO Extractor items - ' . $this->seller->alias, ['New hotels' => $this->hotelSuppliers, 'skippedByexception' => $skippedByexception, 'Result count' => count($this->items)]);
    }

    protected function hotelMapper($hotel)
    {
        $tags = explode(',', $hotel['HotelInfo']['TagIds'] ?? '');
        $districts = $this->getDistricts($tags);
        $thumbnail = $hotel['HotelInfo']['HotelPicture'] ?? ($this->hotelProfile['thumbnail'] ?? '');
        lugINfo('$hotel',[$hotel]);
        $newHotel = new stdClass;
        $newHotel->info = [
            'title' => $hotel['HotelInfo']['HotelName'],
            'property_type' => 0, ///> todo: #46
            'star' => $this->star[$hotel['HotelInfo']['Rating'] ?? 'All'] ?? $this->star['All'],
            'tripadvisor_rating'  => $hotel['HotelInfo']['TripAdvisorRating'] ?? 0,
            'districts' => implode(",", array_column($districts, 'title')),
            'thumbnail' => $thumbnail,
            'details' => $hotel['HotelInfo']['HotelDescription'],
            'tripadvisor_review_url' => $hotel['HotelInfo']['TripAdvisorReviewURL'] ?? '#',
            'phone' => $this->hotelProfile['phone'] ?? '-',
            'address' => $this->hotelProfile['address']['address'] ?? []
        ];
        $newHotel->hotelId = $this->getHotelId($hotel['HotelInfo']['HotelCode'], $hotel['HotelInfo']['HotelName']);
        return $newHotel;
    }

    protected function priceMapper($price)
    {
        $newPrice = new stdClass;
        $newPrice->price = [
            'totalPrice' => $price['@attributes']['TotalPrice']
        ];
        return $newPrice;
    }

    protected function getDistricts($tags)
    {
        $supplierDistricts = $this->allDistricts->whereIn('supplier_key', $tags);
        $districts = [];

        foreach ($supplierDistricts as $row) {
            $districts[$row->supplier_key] = $row->district->toArray();
        }
        return $districts;
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'age']
        ];
        return $this->addPassengerFormItem($items);
    }

    protected function extractSupplierCodes()
    {
        ///> find all the hotel Codes from the response in our database
        preg_match_all('/<TagIds>(.*?)<\/TagIds>/', $this->rawResult['body'], $allTags);
        preg_match_all('/<HotelCode>(\w+)<\/HotelCode>/', $this->rawResult['body'], $hotelCodes);
        $tags = explode(',', implode(',', $allTags[1]));
        $this->allDistricts = $this->loadDistricts($tags);
        $this->hotelCodes = $this->loadHotels(array_unique($hotelCodes[1]));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$this->rawResult[body]' => $this->rawResult['body'], 'hotelCodes' => $hotelCodes[1], 'tags' => $allTags[1],  '$this->allDistricts' => $this->allDistricts,  '$this->hotelCodes' => $this->hotelCodes]);
    }

    protected function checkCurrency($rawResults)
    {
        $currency = $rawResults[0]['MinHotelPrice']['@attributes']['Currency'] ?? null;
        if ($currency != $this->seller->currency->abb) {
            lugError('Currency Mismatch', ['sellerCurrency' => $this->seller->currency, 'supplierResponseCurrency' =>  $currency ?? null]);
            return false;
        }
        return true;
    }
}
