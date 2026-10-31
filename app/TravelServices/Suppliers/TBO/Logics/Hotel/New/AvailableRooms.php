<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\New;

use App\TravelServices\Logics\Hotel\AvailableRooms as HotelAvailableRooms;
use Carbon\Carbon;

trait AvailableRooms
{
    use HotelAvailableRooms;
    protected function canBeDone() {}

    protected function getContent() {}

    protected function roomMapper()
    {
        $counter = 1;
        $hotelRooms = $this->object['seller_info']['rooms'];
        if(empty($hotelRooms)){
            $this->addError('empty hotelRooms');
            lugWarning('seller info',[$this->object['seller_info']]);
            return false ;
        }
        $this->repelSingleMode($hotelRooms);
        $this->roomsPriceList = [];
        foreach ($hotelRooms as $key => $room) {
            $currentCombination = [];
            foreach ($room['CancelPolicies'] as $policy) {
                preg_match('/\d{2}-\d{2}-\d{4}/', $policy['FromDate'], $matches);
                $formattedDate = Carbon::createFromFormat('d-m-Y', $matches[0])->format('Y-m-d');
                $policies[] = [
                    'fromDate' =>  $formattedDate ?? '-',
                    'toDate' => $this->object['service_info']['query_params']['checkout'] ?? '-',
                    'cancellationCharge' => $policy['ChargeType'] == 'Percentage' ? $policy['CancellationCharge'] ?? '-' : exchange($policy['CancellationCharge'], $this->seller->currency_id, $this->getQueryParams()['marketplace_currency']['id']),
                    'currency' => $policy['ChargeType'] == 'Percentage' ? null : $this->getQueryParams()['marketplace_currency'],
                ];
            }
            foreach ($room['Name'] as $nameIndex => $name) {
                $currentCombination[] = (string)$counter;
                $roomDetail[] = [
                    'index' => $counter ?? null,
                    'name' => $name ?? '',
                    'bookingCode' => $room['BookingCode'],
                    'inclusion' => $room['Inclusion'] ?? '',
                    'typeCode' => $room['MealType'] ?? '',
                    'planCode' => $room['RatePlanCode'] ?? '',
                    'amenities' => [],
                    'promtion' => $room['RoomPromotion'] ?? '',
                    'suppliments' => $room['Supplements'] ?? null,
                    'cancelationPolicies' => [
                        'lastCancellationDeadline' => $room['CancelPolicies']['LastCancellationDeadline'] ?? "",
                        'defaultPolicy' => $room['CancelPolicies']['DefaultPolicy'] ?? "",
                        'policies' => $policies,
                    ],
                ];
                $this->roomsPriceList[$counter] = [
                    'payable' => $room['TotalFare'] / count($room['Name']),
                    'rawPrice' => $room['TotalFare'] / count($room['Name'])
                ];
                $counter++;
            }
            $policies = [];
            $combinations[] = $currentCombination;
        }
        return $roomDetail ?? [];
    }

    protected function extractCombinations()
    {
        $counter = 1;
        $combinations = [];
        $hotelRooms = $this->object['seller_info']['rooms'] ?? $this->callException('empty HotelRooms');
        foreach ($hotelRooms as $key => $room) {
            $currentCombination = [];
            foreach ($room['Name'] as $nameIndex => $name) {
                $currentCombination[] = (string)$counter;
                $counter++;
            }
            $combinations[] = ['combination' => $currentCombination, 'BookingCode' => $room['BookingCode']];
        }
        return $combinations;
    }

    protected function roomCombinationsModifier()
    {
        $roomCombinations = $this->extractCombinations();
        if (empty($roomCombinations)) {
            return $this->addError('No available combination');
        }
        foreach ($roomCombinations as $rawCombinations) {
            $rawPrice = $roomsFare = [];
            foreach ($rawCombinations['combination'] as $groupIndex => $roomIndex) {
                $roomsFare[] = $this->getRoomPrice($roomIndex, $groupIndex);
                $rawPrice[] = $this->roomsPriceList[$roomIndex]['rawPrice'];
            }
            $combinationsInfo[] = [
                'roomIndexes' => $rawCombinations['combination'],
                'roomsFare' => $roomsFare,
                'payable' => array_sum($roomsFare),
                'rawPrice' => ['NetRate' => $rawPrice, 'bookingcode' => $rawCombinations['BookingCode']]
            ];
        }
        return ['roomCombinations' => array_column($combinationsInfo ?? [], 'roomIndexes') ?? [], 'combinationsInfo' => $combinationsInfo ?? []];
    }
}
