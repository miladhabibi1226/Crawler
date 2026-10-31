<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\Old;

use App\TravelServices\Logics\Hotel\AvailableRooms as HotelAvailableRooms;

trait AvailableRooms
{
    use HotelAvailableRooms;
    protected function canBeDone()
    {
        if ($this->checkSession($this->object->seller_info['session']) === false) {
            $this->addError('Session Expired.');
        }
        return true;
    }

    protected function getContent()
    {
        if ($this->fromFile) {
            $xml = new \SimpleXMLElement(file_get_contents(storage_path('Samples/TBO/AvailableRooms.xml')));
            $this->rawResult['body'] = json_decode(json_encode($xml->xpath('//s:Body')), true);
            lugInfo('Reading the result from file');
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
        $this->rawResult['body'] = json_decode(json_encode($xml->xpath('//s:Body')), true);
    }

    protected function requestGenerator()
    {
        $this->LugTheResponse = false;
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth;
        lugInfo('Blade Data', [$depends]);
        $this->call = [
            'fields' => view('TBO.AvailableRooms', $depends)->render(),
            'url' => $auth['url'],
            'apiMethod' => 'Available Rooms'
        ];
        return $this->action();
    }

    protected function roomMapper()
    {
        $hotelRooms = $this->rawResult['body'][0]['HotelRoomAvailabilityResponse']['HotelRooms']['HotelRoom'] ?? $this->callException('empty HotelRooms');
        $this->repelSingleMode($hotelRooms);
        $this->roomsPriceList = [];
        foreach ($hotelRooms as $room) {
            $this->roomsPriceList[$room['RoomIndex']] = [
                'payable' => $room['RoomRate']['@attributes']['TotalFare'],
                'rawPrice' => $room['RoomRate']
            ];

            ///>  Extracting cancelation policy rules
            $policies = [];
            $this->repelSingleMode($room['CancelPolicies']['CancelPolicy']);
            foreach ($room['CancelPolicies']['CancelPolicy'] as $policy) {
                $policies[] = [
                    'fromDate' => $policy['@attributes']['FromDate'] ?? '-',
                    'toDate' => $policy['@attributes']['ToDate'] ?? '-',
                    'cancellationCharge' => $policy['@attributes']['ChargeType'] == 'Percentage' ? $policy['@attributes']['CancellationCharge'] ?? '-' : exchange($policy['@attributes']['CancellationCharge'], $this->seller->currency_id, $this->getQueryParams()['marketplace_currency']['id']),
                    'currency' => $policy['@attributes']['ChargeType'] == 'Percentage' ? null : $this->getQueryParams()['marketplace_currency'],
                ];
            }

            ///> extract room detail info
            $roomDetail[] = [
                'index' => $room['RoomIndex'] ?? null,
                'name' => $room['RoomTypeName'] ?? '',
                'inclusion' => $room['Inclusion'] ?? '',
                'typeCode' => $room['RoomTypeCode'] ?? '',
                'planCode' => $room['RatePlanCode'] ?? '',
                'amenities' => is_array($room['Amenities'] ?? []) ? $room['Amenities'] : explode('|', $room['Amenities']),
                'promtion' => $room['RoomPromtion'] ?? '',
                'inclusion' => empty($room['Inclusion']) ? '' : (string) $room['Inclusion'],
                'suppliments' => $room['Supplements'] ?? null,
                'cancelationPolicies' => [
                    'lastCancellationDeadline' => $room['CancelPolicies']['LastCancellationDeadline'] ?? "",
                    'defaultPolicy' => $room['CancelPolicies']['DefaultPolicy'] ?? "",
                    'policies' => $policies,
                ],
            ];
        }
        return $roomDetail ?? [];
    }

    protected function extractCombinations()
    {
        $roomCombinations = $this->rawResult['body'][0]['HotelRoomAvailabilityResponse']['OptionsForBooking']['RoomCombination'] ?? $this->callException('No Room Combination provided');
        $this->repelSingleMode($roomCombinations);
        foreach ($roomCombinations as $key => $rawCombinations) {
            $combinations[$key] = (array) $rawCombinations['RoomIndex'];
        }
        return $combinations ?? [];
    }
}
