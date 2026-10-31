<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\Hotel;

use App\TravelServices\Logics\Hotel\Search as HotelSearch;
use stdClass;

trait Search
{
    use HotelSearch;

    protected $latestSession;

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/DummyName/HotelSearch.json'));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Request generator unsuccessful");
                return false;
            }
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        // $this->LugTheResponse = 1;
        $auth = $this->seller->api->configs;
        $depends = [];
        lugInfo("DummyName Search Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' =>  $auth['url'],
            'headers' => [],
            'timeout' => 20
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $this->extractSupplierCodes();
        $rawResults = $this->rawResult['decoded'] ?? $this->callException('empty result');
        foreach ($rawResults as $hotel) {
            $hotelCode = $hotel['HotelCode'];
            $this->hotelProfile = $this->hotelCodes[$hotelCode]['common_profile'] ?? null;
            $conditions = [
                'city_id' => $this->queryParams['destination_city']['id'],
                'hotel_id' => $this->hotelCodes[$hotelCode]['hotel_id']
            ];
            $this->items[] = [
                'service' => $this->hotelModifier($hotel),
                'price' => $this->priceModifier($hotel, $conditions),
                'seller' => [
                    'lockable' => false,
                    'session' => $this->fromFile ? 'From_File' : $this->supplierSession->session,
                    'hotelCode' => $hotelCode,
                    'resultIndex' => $hotel['ResultIndex'],
                    'seller_id' => $this->seller->id,
                    'passengersForm' => $this->getPassengersForm(),
                    'afters' => [
                        'availableRooms' => false,
                    ],
                    'beforeBooks' => [
                        'availability' => false
                    ]
                ],
            ];
        }

        lugInfo('S>>>> DummyName Hotel search Extractor items - ' . $this->seller->alias, ['supplier raw response items count' => count($rawResults), 'Inserted count' => count($this->items), 'First 3 Items' => array_slice($this->items ?? [], 0, 3)]);
    }

    protected function priceMapper($source)
    {
        $newPrice = new stdClass;
        $newPrice->originalPrice = [
            'totalPrice' => $source['TotalPrice'],
        ];
        $newPrice->price = [
            'totalPrice' => exchange($source['TotalPrice'], $this->seller->currency_id, node()->currency_id)
        ];
        return $newPrice;
    }

    protected function hotelMapper($hotel)
    {
        $newHotel = new stdClass;
        $newHotel->info = [
            'title' => $hotel['HotelName'],
            'property_type' => 0,
            'star' => $this->star[$hotel['Rating'] ?? 'All'] ?? $this->star['All'],
            'tripadvisor_rating'  => $hotel['TripAdvisorRating'] ?? 0,
            'districts' => '',
            'thumbnail' => '',
            'details' => $hotel['HotelDescription'],
            'tripadvisor_review_url' => $hotel['TripAdvisorReviewURL'] ?? '#',
            'phone' =>  '-',
            'address' => '-'
        ];
        $newHotel->hotelId = $this->getHotelId($hotel['HotelCode'], $hotel['HotelName']);
        return $newHotel;
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'nationality', 'nationalId', 'passport_number', 'passport_expiry', 'passport_country']
        ];
        return $this->addPassengerFormItem($items);
    }

    protected function getFields($depends)
    {
        $fields = [];
        return $fields;
    }
}
