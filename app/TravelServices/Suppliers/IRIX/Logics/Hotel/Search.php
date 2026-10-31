<?php

namespace App\TravelServices\Suppliers\IRIX\Logics\Hotel;

use App\TravelServices\Logics\Hotel\Search as HotelSearch;
use App\TravelServices\Handler;
use Illuminate\Support\Facades\DB;
use stdClass;

trait Search
{
    use HotelSearch;
    protected $hotelCounts, $cityId, $resultsToken, $srkToken, $hotelProfile, $countOfEmptyHotelInfos = 0;
    protected $hotelExtractInfo = [];

    public function canBedone()
    {
        $tokens = Handler::beforeSearch($this->seller, $this->object, 'StartSearch');
        if (!$tokens) {
            return $this->addError("No Search results");
        }
        $this->resultsToken = $tokens['items']['resultsToken'] ?? false;
        $this->srkToken = $tokens['items']['srkToken'] ?? false;
        $this->hotelCounts = $tokens['items']['hotelCounts'] ?? false;
        $this->cityId = $tokens['items']['cityId'] ?? false;
        if (empty($this->hotelCounts) || empty($this->resultsToken) || empty($this->cityId)) {
            return $this->addError("search tokens are empty");
        }
        $this->authorizeRequest();
    }

    public function requestGenerator()
    {
        $this->LugTheResponse = 0;
        $this->LugTheRequest = 0;
        $depends = $this->queryParams + $this->seller->api->configs;
        lugInfo("IRIX Result Search Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => "",
            'method' => "GET",
            'url' =>  "{$depends['url']}/api/hotels/v1/search/results/{$this->srkToken}?token={$this->resultsToken}&perPage={$this->hotelCounts}",
            'timeout' => 20,
            'apiMethod' => 'SearchResult'
        ];
        return $this->action();
    }

    protected function extractor()
    {

        $rawResults = $this->rawResult['decoded']['hotels'];
        if (empty($rawResults)) return $this->addError('empty result');
        $countOfAllRawResult = count($this->rawResult['decoded']['hotels']);
        $this->extractSupplierCodes();
        $noHotelProfile = [];
        $allPrices = [];
        // Note: for handling hotel profiles we have two approch:
        //     1- hotel profile exists in hotel_profiles table => we handle it in *step 1*
        //     2- hotel profile doesn't exist in hotel_profiles table => in this approch we have two sub approch:
        //         2-1- hotel profile exists in siding/irix_hotels_detail table => we handle it in *step 2*
        //         2-2- hotel profile doesn't exist in siding/irix_hotels_detail table =>  we handle it in *step 3*
        foreach ($rawResults as $key => $hotel) {
            $hotelCode = $hotel['index'];
            if (is_null($hotel['minPrice'])) continue;
            $allPrices[$hotelCode] = $hotel['minPrice'];
            ///> hotel profile exists in db
            $this->hotelProfile = $this->hotelCodes[$hotelCode]['common_profile'] ?? null;
            if (empty($this->hotelProfile)) {
                $noHotelProfile[] = $hotel;
            } else {
                ///> *step 1* - hotel profile exists in hotels_profiles table
                $this->hotelExtractInfo[] = [
                    'hotel' => $this->hotelProfile,
                    'price' => $allPrices[$hotelCode],
                    'hotelCode' => $hotelCode,
                    'hasDetail' => true
                ];
            }
        }
        if (!empty($noHotelProfile)) {
            $this->findHotelProfile($noHotelProfile, $allPrices);
        }

        $this->getHotelExtractorData();
    }

    protected function priceMapper($source)
    {
        $newPrice = new stdClass;
        $newPrice->price = ['totalPrice' => $source['value']];
        return $newPrice;
    }

    protected function hotelMapper($hotel)
    {
        $newHotel = new stdClass;
        $hotelName = $hotel['title'];
        $hotelStar = $hotel['rating'];
        $hotelTripAdvisor = $hotel['trip_advisor'] ??  0;
        $hotelThumbnail = $hotel['thumbnail'] ?? '';
        $hotelDescription = $hotel['description'] ?? '';
        $hotelTripAdvisorUrl = $hotel['trip_advisor_url'] ?? '#';
        $hotelPhone = $hotel['phone'];
        $hotelAddres = (is_array($hotel['address'])) ? $hotel['address']['address'] : json_decode($hotel['address'], true)['address'];
        $newHotel->info = [
            'title' => $hotelName,
            'property_type' => 0,
            'star' => $hotelStar,
            'tripadvisor_rating'  => $hotelTripAdvisor,
            'districts' => '',
            'thumbnail' =>  $hotelThumbnail,
            'details' =>  $hotelDescription ?? '',
            'tripadvisor_review_url' =>  $hotelTripAdvisorUrl,
            'phone' => $hotelPhone ?? '-',
            'address' => $hotelAddres ?? '-'
        ];

        $newHotel->hotelId = $this->getHotelId($hotel['code'], $hotel['title']);
        return $newHotel;
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate']
        ];
        return $this->addPassengerFormItem($items);
    }

    protected function extractSupplierCodes()
    {
        preg_match_all('/"index":"(\w+)/', $this->rawResult['body'], $hotelIndex);
        $this->hotelCodes = $this->loadHotels(array_unique($hotelIndex[1]));
    }

    protected function findHotelProfile($rawResults, $allPrices)
    {
        $multiCurlHotels = $newHotelProfiles = [];
        ///> check siding/irix_hotels_detail for hotels
        $findHotels = DB::connection('siding')->table($this->getsidingTableName())->whereIN('hotel_id', array_column($rawResults, 'index'))->get();
        foreach ($rawResults as $hotel) {
            $findHotel = $findHotels->where('hotel_id', $hotel['index'])->first();
            if (is_null($findHotel)) {
                $multiCurlHotels[] = (array)$hotel;
            } else {
                ///> *step 2* - hotel profile exists in siding/irix_hotels_detail table
                $hotelPrice = $allPrices[$hotel['index']];

                $this->hotelExtractInfo[] = [
                    'hotel' => (array)$findHotel,
                    'price' => $hotelPrice,
                    'hotelCode' => $hotel['index'],
                    'hasDetail' => false
                ];
            }
        }
        if (!empty($multiCurlHotels)) {
            ///> *step 3* - hotel profile doesn't exist in siding/irix_hotels_detail table so we should use multicurl and get hotel detail
            $newHotelProfiles = $this->multiCurlForHotelProfile($multiCurlHotels);
        }
        foreach ($newHotelProfiles as $hotelProfile) {
            $hotel = (array)$hotelProfile;
            $hotelCode = $hotel['hotel_id'];
            $hotelPrice = $allPrices[$hotelCode];

            $this->hotelExtractInfo[] = [
                'hotel' => $hotel,
                'price' => $hotelPrice,
                'hotelCode' => $hotelCode,
                'hasDetail' => false
            ];
        }
    }

    protected function multiCurlForHotelProfile($multiCurlHotels)
    {
        $auth = $this->seller->api->configs;
        $this->LugTheResponse = 0;
        $this->LugTheRequest = 0;
        $this->multi = true;
        $insertable = [];
        ///> multicurl 10 to 10 - for hotels that are not in siding->irix_hotels_detail
        $resultChunk = array_chunk($multiCurlHotels, 10);
        foreach ($resultChunk as $chunkResult) {
            $this->call = [];
            foreach ($chunkResult as $result) {
                $this->call[$result['index']] = [
                    'fields' => "",
                    'method' => "GET",
                    'url' =>  "{$auth['url']}/api/hotels/v1/search/results/$this->srkToken/hotels/{$result['index']}/details?token=$this->resultsToken",
                    'timeout' => 20,
                    'apiMethod' => 'HotelInfo'
                ];
            }
            $this->action();
            foreach ($this->rawResult as $hotelInfo) {
                $hotel = json_decode($hotelInfo['body'], true);
                if (empty($hotel)) {
                    $this->countOfEmptyHotelInfos++;
                    continue;
                }
                $hotelCode = $hotel['id'];
                ///> new hotel profile
                $insertable[] = [
                    'city_id' => $this->cityId,
                    'hotel_id' => $hotelCode,
                    'title' => $hotel['name'],
                    'phone' => $hotel['telephone'],
                    'description' => $hotel['shortDescription'],
                    'thumbnail' => $hotel['mainImage']['url'] ?? null,
                    'address' => json_encode(['lat' => $hotel['geolocation']['latitude'], 'long' => $hotel['geolocation']['longitude'], 'address' => $hotel['address'], 'postal_code' => $hotel['zipCode']]),
                    'rating' => $hotel['stars'],
                ];
            }
        }
        ///> insert new hotels info in siding->irix_hotels_detail
        if (!empty($insertable)) {
            DB::connection('siding')->table($this->getsidingTableName())->insert($insertable);
        }
        return $insertable ?? [];
    }

    protected function getHotelExtractorData()
    {
        $skippedByexception = 0;
        foreach ($this->hotelExtractInfo as $hotelInfo) {
            $hotelInfo['hotel']['code'] = empty($hotelInfo['hotel']['code']) ? $hotelInfo['hotel']['hotel_id'] : $hotelInfo['hotelCode'];
            $serviceInfo = $this->hotelModifier($hotelInfo['hotel']);
            $conditions = $this->getPricingConditions($serviceInfo);
            if (!$this->checkAccessExceptions($conditions, $this->getAccessExceptions())) {
                $skippedByexception++;
                continue;
            }
            $this->items[] = [
                'service' => $serviceInfo,
                'price' => $this->priceModifier($hotelInfo['price'], $conditions),
                'seller' => [
                    'onholdable' => false,
                    'sellerCode' => $this->seller->abb,
                    'session' => $this->fromFile ? 'From_File' : $this->supplierSession->session,
                    'hotelCode' => $hotelInfo['hotelCode'],
                    'srkToken' => $this->srkToken,
                    'resultsToken' => $this->resultsToken,
                    'passengersForm' => $this->getPassengersForm(),
                    'afters' => [
                        'detail' => $hotelInfo['hasDetail'],
                        'availableRooms' => false,
                    ],
                    'beforeBooks' => [
                        'AvailabilityAndPricing' => false
                    ]
                ],
            ];
        }

        lugInfo('S>>>> IRIX Hotel search Extractor items - ' . $this->seller->alias, ['supplier raw response items count' => count($this->hotelExtractInfo), 'Inserted count' => count($this->items), 'Count of empty hotels info' => $this->countOfEmptyHotelInfos, 'skippedByexception' => $skippedByexception]);
    }

    protected function getsidingTableName()
    {
        return "irix_hotels_detail";
    }
}
