<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Hotel;

use App\Jobs\HotelsFrequency;
use App\Jobs\HotelProfiles;
use App\TravelServices\Logics\Hotel\Search as CommonHotelSearch;
use App\TravelServices\Suppliers\Parto\Logics\Search as LogicsSearch;
use MatinUtils\NodeTracker\Models\City;
use stdClass;

trait Search
{
    ///> using PartoSearch for canBedone, getContent and requestGenerator
    use LogicsSearch, CommonHotelSearch;

    protected $forFrequesncy, $fromPartoDatabase, $resultsWithNoLocalProfile = [], $isDomestic;

    protected function extractor()
    {
        $this->extractSupplierCodes();
        $rawResults = $this->rawResult['decoded']['PricedItineraries'] ?? [];
        if (empty($rawResults)) {
            return $this->addError('PricedItineraries empty');
        }
        foreach ($rawResults as $hotel) {
            if (empty($this->hotelCodes[$hotel['HotelId']]['common_profile'])) {
                $toGetFromPartoDB[] = $this->isDomesticHotel() ? ((int) $hotel['HotelId']) + 5000000 : $hotel['HotelId'];
            }
        }
        $this->fromPartoDatabase = app('db')->connection('parto')->table('parto_hotels')->whereIn('id', $toGetFromPartoDB ?? [])->get();

        $skippedByexception = $skippedOffline = 0;
        foreach ($rawResults as $hotel) {
            $rawHoteCode = $hotel['HotelId'];
            if (!$this->checkCurrency($hotel)) {
                $this->addError('Currency Mismatch');
                return;
            }
            if (($hotel['IsReserveOffline'] ?? false) || ($hotel['IsBoarderPrice'] ?? false) || ($hotel['IsBlockout'] ?? false)) {
                $skippedOffline++;
                continue;
            }
            $hotelCode =  $this->isDomesticHotel() ? ((int) $rawHoteCode) + 5000000 : $rawHoteCode; ///> there are 2 sets of hotel data from parto, one for domestic and one for the rest the id of parto domestic hotels in our database is saved with addition of 5000000 to avoid confilict with other parto hotels. 
            $this->hotelProfile = $this->hotelCodes[$hotelCode]['common_profile'] ?? (json_decode(json_encode($this->fromPartoDatabase->where('id', $hotelCode)->first() ?? []), true));

            if (!empty($this->hotelProfile)) {
                $serviceInfo = $this->hotelModifier($hotel);
                $conditions = $this->getPricingConditions($serviceInfo);
                if (!$this->checkAccessExceptions($conditions, $this->getAccessExceptions())) {
                    $skippedByexception++;
                    continue;
                }
                $this->items[] = [
                    'service' => $serviceInfo,
                    'price' => $this->priceModifier(['NetRate' => $hotel['NetRate']], $conditions),
                    'seller' => [
                        'onholdable' => false,
                        'sellerCode' => $this->seller->abb,
                        'passengersForm' => $this->getPassengersForm(),
                        'hotelCode' => $hotelCode,
                        'rawHotelCode' => $rawHoteCode,
                        'FareSourceCode' => $hotel['FareSourceCode'],
                        'isDomesticHotel' => $this->isDomesticHotel(),
                        'afters' => [
                            'availableRooms' => false,
                            'detail' => !empty($this->hotelCodes[$hotelCode]['common_profile'])
                        ],
                        'beforeBooks' => [
                            'AvailabilityAndPricing' => false
                        ]
                    ],
                ];
                if (empty($this->hotelCodes[$hotelCode]['common_profile'])) {
                    $this->resultsWithNoLocalProfile[] = ['supplierKey' => $hotelCode, 'hotelId' => $serviceInfo['attachs']['hotelId']];
                }
            } else {
                $partoHotelNotFound[] = $hotelCode;
            }
        }
        lugInfo("S>>>> Parto Hotel search Extractor items - " . $this->seller->alias, ['skippedOffline' => $skippedOffline, 'skippedByexception' => $skippedByexception, 'resultsWithNoLocalProfile' => $this->resultsWithNoLocalProfile, 'supplier raw response items count' => count($rawResults), 'Inserted items' => count($this->items ?? []), 'partoHotelNotFound' => json_encode($partoHotelNotFound ?? [])]);
        // dispatch(new HotelsFrequency(app('log-system')->getPid(), $this->forFrequesncy, $this->seller->supplierService->data_reference))->onQueue('supplierData');
        dispatch(new HotelProfiles(app('log-system')->getPid(), 'database', 'Parto', node()->id, $this->resultsWithNoLocalProfile, $this->seller->supplierService->data_reference, $this->seller->id, $this->isDomesticHotel()))->onQueue('supplierData');
    }

    protected function priceMapper($source)
    {
        $newPrice = new stdClass;
        $newPrice->price = ['totalPrice' => $source['NetRate']];
        return $newPrice;
    }

    protected function hotelMapper($hotel)
    {
        ///> the ptofile is either from our db or from parto's table (external table). most cols have the same name
        $hotelCode =  $this->isDomesticHotel() ? ((int) $hotel['HotelId']) + 5000000 : $hotel['HotelId']; ///> there are 2 sets of hotel data from parto, one for domestic and one for the rest the id of parto domestic hotels in our database is saved with addition of 5000000 to avoid confilict with other parto hotels. 
        $isFromPartoDB = empty($this->hotelCodes[$hotelCode]['common_profile']);
        $titleColumn = $isFromPartoDB ? 'name' : 'title';
        $newHotel = new stdClass;
        $newHotel->info = [
            'title' => $this->hotelProfile[$titleColumn],
            'property_type' => 0,
            'star' => $this->hotelProfile['rating'] ?? '0',
            'tripadvisor_rating'  => $this->hotelProfile['trip_advisor'] ?? 0,
            'districts' => '',
            'thumbnail' => empty($this->hotelProfile['thumbnail']) ? ($this->hotelProfile['images'][0] ?? '') : $this->hotelProfile['thumbnail'],
            'details' => $this->hotelProfile['description'] ?? '',
            'tripadvisor_review_url' => '#',
            'phone' =>  $this->hotelProfile['phone'] ?? '',
            'address' => $this->hotelProfile['address']['address'] ?? $this->hotelProfile['address'] ?? '',
            'extraData' => []
        ];
        $newHotel->hotelId = $this->getHotelId($hotelCode, $this->hotelProfile[$titleColumn]);
        return $newHotel;
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'age']
        ];
        return $this->addPassengerFormItem($items);
    }

    protected function getFields()
    {
        $fields = [
            "SessionId" => $this->latestSession,
            "CheckIn" => $this->queryParams['checkin'],
            "CheckOut" => $this->queryParams['checkout'],
            "NationalityId" => $this->queryParams['nationality_country']['abb'],
            "CityId" => $this->queryParams['destination_city']['supplier_key'],
            "CountryCode" => $this->queryParams['destination_city']['country']['abb']
        ];
        foreach ($this->queryParams['rooms_detail'] as $group) {
            $Occupancies = [
                "AdultCount" => $group['adults'],
                "ChildCount" => $group['children'],
            ];
            foreach ($group['child-age'] ?? [] as $childAge) {
                $Occupancies['ChildAges'][] = $childAge;
            }
            $fields['Occupancies'][] = $Occupancies;
        }
        return $fields;
    }

    public function translateQueryParams($withCityAirports = false)
    {
        ///> Parto has 2 sets of city info. one for Domestic cities one and for the rest (codes have conflict)
        ///> we keep the main data in the supplier_cities table and the domestic data here (hard coded)
        if ($this->isDomesticHotel()) {
            $this->queryParams['destination_city']['supplier_key'] = $this->DomesticCities($this->queryParams['destination_city']['en']);
        } else {
            $this->queryParams['destination_city']['supplier_key'] = City::find($this->queryParams['destination_city']['id'])->citySuppliers($this->queryParams['destination_city']['id'])->where('data_reference', types('supplierDataReference', 'Parto'))->first()->supplier_key ?? null;
        }

        if (empty($this->queryParams['destination_city']['supplier_key'])) {
            lugError('City is not defined for the supplier', [$this->queryParams]);
            return false;
        }
        return true;
    }

    protected function extractSupplierCodes()
    {
        preg_match_all('/"HotelId":(\w+)/', $this->rawResult['body'], $response);
        $hotelCodes = $this->checkHotelDomesticCodes($response[1]);
        $this->hotelCodes = $this->loadHotels(array_unique($hotelCodes));
        $this->forFrequesncy = $hotelCodes;
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['response hotelCodes' => $response[1], '$hotelCodes' => $hotelCodes,  '$this->hotelCodes' => $this->hotelCodes]);
    }

    protected function DomesticCities($key)
    {
        return [
            "Tehran" => 1,
            "Shiraz" => 2,
            "Tabriz" => 3,
            "Jolfa" => 4,
            "Yazd" => 5,
            "Bafgh" => 6,
            "Esfahan" => 7,
            "Mobarakeh (Isfahan)" => 8,
            "Khansar" => 9,
            "Mashhad" => 10,
            "Bandar Abbas" => 11,
            "Minab" => 12,
            "Kish Island" => 13,
            "Araak" => 14,
            "Khomein" => 15,
            "Qom" => 16,
            "Chaloos" => 17,
            "Hamedan" => 18,
            "Maryanaj" => 19,
            "Kermanshah" => 20,
            "Urmia" => 21,
            "Gorgan" => 22,
            "Marivan" => 23,
            "Kerman" => 24,
            "Bushehr" => 25,
            "Anzali" => 26,
            "Lahijan" => 27,
            "Khorramabad" => 28,
            "Naien" => 29,
            "Ramsar" => 30,
            "Now Shahr" => 31,
            "Astara" => 32,
            "Shemshak" => 33,
            "Damavand" => 34,
            "Meygoon" => 35,
            "Lar" => 36,
            "FiroozAbad" => 37,
            "Fasa" => 38,
            "Marvdasht" => 39,
            "Jahrom" => 40,
            "Darab" => 41,
            "Kazeroon" => 42,
            "Abadeh" => 43,
            "Sepidan" => 44,
            "Sarab" => 45,
            "Marand" => 46,
            "Maragheh" => 49,
            "Kaleybar" => 50,
            "Osku" => 51,
            "Bonab" => 52,
            "Ardakan" => 53,
            "Meybod" => 54,
            "Abbarkooh" => 55,
            "Golpayegan" => 56,
            "Kashan" => 57,
            "Natanz" => 58,
            "Daran" => 59,
            "Abyaneh" => 60,
            "Ghamsar" => 61,
            "Khoor va Biabanak" => 62,
            "Shahr reza" => 63,
            "Neyshabur" => 64,
            "Sabzevar" => 65,
            "Gonabad" => 66,
            "Gheshm Island" => 67,
            "Mahallat" => 68,
            "Delijan" => 69,
            "Save" => 70,
            "Khezer shahr" => 71,
            "Mahmood Abad" => 72,
            "Javaher Deh" => 73,
            "Sari" => 74,
            "Tonekabon" => 75,
            "Amol" => 76,
            "Kelardasht" => 77,
            "Babol" => 78,
            "Babolsar" => 79,
            "Nur" => 80,
            "Behshahr" => 81,
            "Salman Shahr" => 82,
            "Fereydoon Kenar" => 83,
            "Abbas Abad" => 84,
            "Savad Kooh" => 85,
            "Kaboodarahang" => 86,
            "Paveh" => 87,
            "Khoy" => 88,
            "Makoo" => 89,
            "Mahabad" => 90,
            "Miandoab" => 91,
            "Azad Shahr" => 92,
            "Aliabad" => 93,
            "Kordkuy" => 94,
            "Minoo Dasht" => 95,
            "Gonbad Kavoos" => 96,
            "Sanandaj" => 97,
            "Baneh" => 98,
            "Shahrud" => 99,
            "Damghan" => 100,
            "Semnan" => 101,
            "Bastam" => 102,
            "Bam" => 103,
            "Mahan" => 104,
            "Rafsanjan" => 105,
            "Sirjan" => 106,
            "Ghale ganj" => 107,
            "Jiroft" => 108,
            "Yasouj" => 109,
            "Gachsaran" => 110,
            "Asalooyeh" => 111,
            "Zahedan" => 112,
            "Chah Bahar" => 113,
            "Roodsar" => 114,
            "Masal" => 115,
            "Rasht" => 116,
            "Fooman" => 117,
            "Chamkhale" => 118,
            "Chaboksar" => 119,
            "Masuleh" => 120,
            "Langarud" => 121,
            "Talesh" => 122,
            "Rezvan Shahr" => 123,
            "Astaneh Ashrafie" => 124,
            "Shahrkord" => 125,
            "Broojen" => 126,
            "Birjand" => 127,
            "Tabas" => 128,
            "Ferdos" => 129,
            "Aligoodarz" => 130,
            "Broojerd" => 131,
            "Zanjan" => 132,
            "Sarein" => 133,
            "Ardabil" => 134,
            "Khalkhal" => 135,
            "Meshqin Shahr" => 136,
            "Dizin" => 137,
            "Kordan" => 138,
            "Karaj" => 139,
            "Gachsar" => 140,
            "Taleghan" => 141,
            "Abadan" => 142,
            "Shooshtar" => 143,
            "Ahvaz" => 144,
            "Dezful" => 145,
            "Bandar Mahshahr" => 146,
            "Izeh" => 147,
            "Andimeshk" => 148,
            "Khoram Shahr" => 149,
            "Behbahan" => 150,
            "Bojnurd" => 151,
            "Shirvan" => 152,
            "Ghazvin" => 153,
            "Ilam" => 154,
            "Varamin" => 155,
            "Estahban" => 156,
            "Sarvestan" => 157,
            "Eqlid" => 158,
            "Taft" => 159,
            "Mehriz" => 160,
            "Aran va Bidgol" => 161,
            "Ardahal" => 162,
            "Zavareh" => 163,
            "Niasar" => 164,
            "Kuhpayeh" => 165,
            "Shahin Shahr" => 166,
            "Torbat-e-Jam" => 167,
            "Sarakhs" => 168,
            "Chenaran" => 169,
            "Bandar Lengeh" => 170,
            "Hormuz Island" => 171,
            "Tafresh" => 172,
            "Neka" => 173,
            "Larijan" => 174,
            "Marzanabad" => 175,
            "Lavij" => 176,
            "Juybar" => 177,
            "Qaemshahr" => 178,
            "Malayer" => 179,
            "Lalejin" => 180,
            "Bisotun" => 181,
            "Piranshahr" => 182,
            "Salmas" => 183,
            "Bandar Gaz" => 184,
            "Khanbebin" => 185,
            "Ramian" => 186,
            "Aliabad-e-Katul" => 187,
            "Galikesh" => 188,
            "Bandar Torkaman" => 189,
            "Abidar" => 190,
            "Saqqez" => 191,
            "Avroman" => 192,
            "Bijar" => 193,
            "Bandar Ganaveh" => 194,
            "Borazjan" => 195,
            "Amlash" => 196,
            "Rudbar" => 197,
            "Someh Sara" => 198,
            "Zibakenar" => 200,
            "Kelachay" => 201,
            "Asalem" => 202,
            "Kiashahr" => 203,
            "Dorud" => 204,
            "Lorestan" => 205,
            "Garmsar" => 206,
            "Mahdi Shahr" => 207,
            "Sisakht" => 208,
            "Iranshahr" => 209,
            "Kuhrang" => 210,
            "Nehbandan" => 211,
            "Takestan" => 212,
            "Alamut" => 213,
            "Abegarm" => 214,
            "Parsian" => 218,
            "Oraman" => 219,
            "AbarKoh" => 220,
            "Bandar Siraf" => 222,
        ][$key] ?? null;
    }

    protected function checkHotelDomesticCodes($hotelCodes)
    {
        ///> Parto has 2 sets of hotel info. one for Domestic hotels one and for the rest (codes have conflict)
        ///> we saved domestic hotels in the database but added 5000000 to each of their ids to solve the conflict
        ///> so here when we wanna look for the codes in the db we need to add 5000000 to each Id
        if ($this->isDomesticHotel()) {
            $hotelCodes = array_map(fn($id) => ((int) $id) + 5000000, $hotelCodes);
        }
        return $hotelCodes;
    }

    protected function checkCurrency($result)
    {
        $resultCurrency = $result['Currency'] ?? null;
        if ($resultCurrency != $this->seller->currency->abb) {
            lugError('Currency Mismatch', ['sellerCurrency' => $this->seller->currency, 'result' => $result['Currency'] ?? null]);
            return false;
        }
        return true;
    }
}
