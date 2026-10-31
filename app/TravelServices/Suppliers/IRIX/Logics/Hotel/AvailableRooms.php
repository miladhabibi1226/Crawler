<?php

namespace App\TravelServices\Suppliers\IRIX\Logics\Hotel;

use App\TravelServices\Logics\Hotel\AvailableRooms as HotelAvailableRooms;
use Carbon\Carbon;
use Exception;

trait AvailableRooms
{
    use HotelAvailableRooms;

    ///> we can't remove getContent to common file, because we don't neeed to decode response,response already is an array
    public function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult[0]['body'] = file_get_contents(storage_path('Samples/IRIX/AvailableRoomsOffers.json'));
            $this->rawResult[0]['http_code'] = 200;
            $this->rawResult[1]['body'] = file_get_contents(storage_path('Samples/IRIX/AvailableRoomsAmenities.json'));
            $this->rawResult[1]['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $this->LugTheResponse = 0;
        $this->LugTheRequest = 0;
        $depends = $this->object->seller_info + $this->seller->api->configs + $this->object->price_info;
        // lugInfo("IRIX Hotel AvailableRooms Data ({$this->seller->alias})", [$depends]);
        $urls = ["hotels/v1/search/results/{$depends['srkToken']}/hotels/{$depends['hotelCode']}/offers?token={$depends['resultsToken']}", "mapping/v1/hotels/{$depends['hotelCode']}/descriptions"];
        $this->multi = true;
        $this->call = [];
        foreach ($urls as $url) {
            $this->call[] = [
                'fields' => "",
                'method' => "GET",
                'url' =>  "{$depends['url']}/api/$url",
                'apiMethod' => 'AvailableRooms'
            ];
        }
        return $this->action();
    }

    protected function roomMapper()
    {
        ///> hotel offers
        lugWarning('rooms response body', [$this->rawResult[0]]);
        $hotelOfferResponse = $this->rawResult[0];
        $hotelAllOffers = json_decode($hotelOfferResponse['body'] ?? [], true);
        if (empty($hotelAllOffers['offers'])) {
            lugError('hotel offers is empty', [$this->rawResult]);
            return $this->addError('hotel offers is empty');
        }
        $hotelOffers = $hotelAllOffers['offers'];
        ///> hotel amenities
        $hotelAmenitiesResponse = $this->rawResult[1];
        $hotelAmenities = json_decode($hotelAmenitiesResponse['body'] ?? [], true);
        $hotelAmenitiesInArray = !empty($hotelAmenities['facilitiesDescription']) ? [$hotelAmenities['facilitiesDescription']] : [];
        $this->roomsPriceList = $roomDetail = [];
        $roomIndex = 1;
        foreach ($hotelOffers as $offer) {
            foreach ($offer['rooms'] as $room) {
                ///> room price - if room price is null, so skip the offer
                if (is_null($room['price'])) {
                    continue 1;
                }
                $amenitiesAndRoomInfo = $hotelAmenitiesInArray;
                $this->roomsPriceList[$roomIndex] = [
                    'payable' => $room['price']['selling']['value'] ?? null,
                    'rawPrice' => $room['price'] ?? []
                ];
                ///> concat room info to amenities
                if (!empty($room['info'])) {
                    array_push($amenitiesAndRoomInfo, $room['info']);
                }
                ///> cancellation informations
                $policies = [];
                $cancellationCharge = ($room['nonRefundable']) ? '100' : '0';
                $policies[] = [
                    'fromDate' => Carbon::now()->format('Y-m-d'),
                    'toDate' => $this->queryParams['checkin'],
                    'cancellationCharge' => $cancellationCharge,
                    'currency' => null
                ];
                $roomDetail[] = [
                    'index' => $roomIndex,
                    'code' => $offer['id'] . ':' . $room['index'],
                    'name' => $room['name'] ?? '',
                    'inclusion' => $room['boardBasis'] ?? '',
                    'amenities' => $amenitiesAndRoomInfo ?? [],
                    'cancelationPolicies' => [
                        'lastCancellationDeadline' => "",
                        'defaultPolicy' => ($room['nonRefundable'] === true) ? "nonRefundable" : "Refundable",
                        'policies' => $policies ?? "",
                    ],
                ];
                $roomIndex++;
            }
        }

        return $roomDetail ?? [];
    }

    protected function extractCombinations()
    {
        $offers = json_decode($this->rawResult[0]['body'], true)['offers'] ?? [];
        $counter = 0;
        $combinations = $packageToken = [];
        foreach ($offers as $offer) {
            $offerId = $offer['id'];
            foreach ($offer['packages'] ?? [] as $pakkey => $package) {
                ///> create combinations
                $b = [];
                $combination = $packageTokenRooms = [];
                $packageRooms  = $package['packageRooms'];
                $firstRoom = array_shift($packageRooms);

                foreach ($firstRoom['roomReferences'] as $roomREferenceKey => $roomReference) {
                    $b[] = [$roomReference];
                }
                foreach ($packageRooms as $prk => $packageRoom) {
                    $c = [];
                    foreach ($b as $k => $bk) {
                        foreach ($packageRoom['roomReferences'] as $prrk => $prr) {
                            $c[] = array_merge($bk, [$prr]);
                        }
                    }
                    $b = $c;
                }

                foreach ($b as $com) {
                    foreach ($this->queryParams['rooms_detail'] as $index => $r) {
                        ///> find room index in roomDetail array
                        $packageToken[$counter] = [
                            'token' => $package['packageToken'],
                            'price' => $package['price']
                        ];
                        $roomIndex = $this->findRoomKey($offerId, $com[$index]['roomCode']);
                        if (!empty($roomIndex)) {
                            $combination[$index] = $roomIndex;
                            $packageTokenRooms[$index] = $com[$index]['roomToken'];
                        } else {
                            lugWarning("Room does not exist", [$offerId, $com[$index]['roomCode'], "{$offerId}:{$com[$index]['roomCode']}"]);
                            continue 2;
                        }
                    }

                    $combinations[$counter] = array_filter($combination);
                    $packageToken[$counter]['roomTokens'] = $packageTokenRooms;
                    $counter++;
                }
            }
        }
        return [array_filter($combinations) ?? [], $packageToken ?? []];
    }

    protected function findRoomKey($hotelOfferId, $roomCode)
    {
        foreach ($this->items['roomDetail'] as $index => $room) {
            if ($room['code'] == "$hotelOfferId:$roomCode") {
                return $room['index'];
            }
        }
    }

    protected function roomCombinationsModifier()
    {
        list($combinations, $packageToken) = $this->extractCombinations();
        if (empty($combinations)) {
            return $this->addError('No available combination');
        }
        foreach ($combinations as $index => $rawCombinations) {
            $rawPrice = $roomsFare = [];
            $combinationPrice = 0;
            foreach ($rawCombinations as $groupIndex => $roomIndex) {
                $roomsFare[] = $this->getRoomPrice($roomIndex, $groupIndex);
                $rawPrice[] = $this->roomsPriceList[$roomIndex]['rawPrice'];
                $combinationPrice += $this->roomsPriceList[$roomIndex]['rawPrice']['selling']['value'];
            }
            $combinationsInfo[] = [
                'roomIndexes' => $rawCombinations,
                'roomsFare' => $roomsFare,
                'payable' => $combinationPrice,
                'rawPrice' => $packageToken[$index] + ['rooms' => $rawPrice]
            ];
        }
        return ['roomCombinations' => $combinations, 'combinationsInfo' => $combinationsInfo];
    }
}
