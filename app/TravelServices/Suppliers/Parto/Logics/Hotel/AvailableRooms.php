<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Hotel;

use App\TravelServices\Logics\Hotel\AvailableRooms as HotelAvailableRooms;
use Carbon\Carbon;

trait AvailableRooms
{
    use HotelAvailableRooms;
    protected $partoCombinations;
    protected function canBeDone()
    {
        $this->findOrCreateSupplierSession();
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path("Samples/Parto/HotelAvailableRooms.json"));
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            $this->latestSession = 'fromFile';
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }

        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    protected function requestGenerator()
    {
        lugInfo('AvailableRooms Data', [$this->queryParams, $this->latestSession]);
        $this->LugTheResponse = false;
        $this->call = [
            'fields' => json_encode($this->getFields()),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . '/Rest/Hotel/HotelAvailability',
            'timeout' => 20,
            'apiMethod' => 'Available Rooms'
        ];
        return $this->action();
    }

    protected function roomMapper()
    {
        $results = $this->rawResult['decoded']['PricedItineraries'] ?? $this->callException('empty PricedItineraries');
        $this->roomsPriceList = [];
        $roomIndex = 1;
        foreach ($results as $combination) {
            ///>  Extracting cancelation policy rules
            $policies = [];
            if (!empty($combination['CancellationPolicies'])) {
                foreach ($combination['CancellationPolicies'] as $key => $policy) {
                    $policies[] = [
                        'fromDate' => empty($policy['FromDate']) ? '-' : Carbon::create($policy['FromDate'])->format('Y-m-d'),
                        'toDate' => empty($combination['CancellationPolicies'][$key + 1]['FromDate']) ? $this->queryParams['checkin'] : Carbon::create($combination['CancellationPolicies'][$key + 1]['FromDate'])->format('Y-m-d'),
                        'cancellationCharge' => exchange($policy['Amount'], $this->seller->currency_id, $this->getQueryParams()['marketplace_currency']['id']) ?? '-',
                        'currency' => $this->getQueryParams()['marketplace_currency'],
                    ];
                }
            }

            $partoCombinationRomms = [];
            foreach ($combination['Rooms'] as $room) {
                $roomDetail[] = [
                    'index' => $roomIndex,
                    'name' => $room['Name'] ?? '',
                    'inclusion' => $room['MealType'] ?? '',
                    'amenities' => $combination['Amenities'] ?? [],
                    'cancelationPolicies' => [
                        'lastCancellationDeadline' => $combination['PaymentDeadline'] ?? "",
                        'defaultPolicy' => $combination['PlainTextCancellationPolicy'] ?? "",
                        'policies' => $policies,
                    ],
                ];
                $partoCombinationRomms[] = $roomIndex;
                $roomIndex++;
            }
            $this->partoCombinations[] = ['rooms' => $partoCombinationRomms, 'exchangedPrice' => exchange($combination['NetRate'], $this->seller->currency_id, node()->currency_id), 'NetRate' => $combination['NetRate'], 'FareSourceCode' => $combination['FareSourceCode'], 'Currency' => $combination['Currency']];
        }
        return $roomDetail ?? [];
    }

    protected function roomCombinationsModifier()
    {
        ///> we don't get the price for each room. we get total price of each conmbination
        ///> temporarily, we devide the combination price by the number of the rooms, to make the priceInfo structure like the other suppliers (will remove if i see there is really no need for the room price)

        $roomCombinations = $this->partoCombinations;
        foreach ($roomCombinations as $rawCombinations) {
            $roomsFare = [];
            $priceForEachRoom = $rawCombinations['NetRate'] / count($rawCombinations['rooms']);
            for ($i = 1; $i <= count($rawCombinations['rooms']); $i++) {
                $roomsFare[] = $priceForEachRoom;
            }
            $combinationsInfo[] = [
                'roomIndexes' => $rawCombinations['rooms'] ?? [],
                'roomsFare' => $roomsFare,
                'payable' => array_sum($roomsFare),
                'rawPrice' => ['NetRate' => $rawCombinations['NetRate'], 'FareSourceCode' => $rawCombinations['FareSourceCode'], 'Currency' => $rawCombinations['Currency']],
                'integratorNote' => 'NetRate is the total comination price, It is the only price provided by the supplier. totalFare for each room is NetRate devided by the count of the rooms'
            ];
        }
        return ['roomCombinations' => array_column($combinationsInfo ?? [], 'roomIndexes') ?? [], 'combinationsInfo' => $combinationsInfo ?? []];
    }

    protected function getFields()
    {
        $fields = [
            "SessionId" => $this->latestSession,
            "CheckIn" => $this->queryParams['checkin'],
            "CheckOut" => $this->queryParams['checkout'],
            "NationalityId" => $this->queryParams['nationality_country']['abb'],
            "HotelId" => $this->object->seller_info['rawHotelCode'],
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
}
