<?php

namespace App\TravelServices\Suppliers\Local\Logics\Package;

use App\TravelServices\CachedBuffer;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\PackageAvailability;

trait ItemValidityHelper
{
    protected function getRawResult()
    {
        $passengersCount = $this->packagePassengerCountSum($this->object->service_info['passengersCount'] ?? []);
        $this->rawResult = [
            'availability' => $availability  = PackageAvailability::find($this->object->seller_info['sellectedAvailability']['availabilityId']),
            'sellectedAvailability' => $this->object->price_info['sellectedAvailability'],
            'passengersCount' => $this->object->service_info['passengersCount'] ?? [],
            'passengersCountSum' => $passengersCount['total'],
            'infantsCount' => $passengersCount['infants'],
            'flightAllocations' => $this->getFlightAllocationsForPackage($availability?->flights ?? [])
        ];
    }

    protected function checkAvailabilityAndPricing()
    {
        // Checking the remaining capacity
        $remainCapacity = $this->rawResult['availability']->remaining_capacity;
        if (($this->rawResult['passengersCountSum'] - $this->rawResult['infantsCount']) > $remainCapacity) {
            lugError('The remain capacity for the availability is not enough for the number of the passengers.', ['infantsCount' => $this->rawResult['infantsCount'], 'passengersCountSum' => $this->rawResult['passengersCountSum'], 'remainCapacity' => $remainCapacity]);
            $this->addError('Remain Capacity is not enough.');
            if ($this->objectIsBooking) {
                $this->addErrorToBook(trans('messages.capacity'));
            }
            if ($this->objectIsFromCachedItem) {
                (new CachedBuffer('package', $this->rawResult['availability']->package_id, $this->seller))->clearCachedBuffer();
            }
        }

        $this->checkFlightAllocationCapacity();

        // checking room capacity and price
        $availabilityRooms = $this->rawResult['availability']->rooms;
        $bookedRooms = $this->rawResult['sellectedAvailability']['sellectedRooms'];
        $availablePassengersCount = $this->availablePackagePassengerCount($this->rawResult['availability']->rooms);
        foreach ($availabilityRooms as $key => $roomInfo) {
            $roomType = $roomInfo['name'];
            if (!empty($bookedRooms[$roomType])) {
                if (($roomInfo['quantity'] ?? 0) != 'unlimited' && $roomInfo['quantity'] < count($bookedRooms[$roomType])) {
                    lugError('The quantity of the room doesnt match the request.', ['$roomType' => $roomType, '$roomInfo[quantity]' => $roomInfo['quantity'] ?? 0, 'count($bookedRooms[$roomType])' => count($bookedRooms[$roomType])]);
                    $this->addError('The quantity of the room doesnt match the request.');
                    if ($this->objectIsBooking) {
                        $this->addErrorToBook(trans('messages.capacity'));
                    }
                    if ($this->objectIsFromCachedItem) {
                        (new CachedBuffer('package', $this->rawResult['availability']->package_id, $this->seller))->clearCachedBuffer();
                    }
                }
                if ($this->rawResult['sellectedAvailability']['rooms'][$roomType]['sellerPrice']['details']['bySellerCurrency']['base'] != $roomInfo['pricing']) {
                    lugError("Price Changed for room type $roomType", ['$new' => $roomInfo, 'old' => $this->rawResult['sellectedAvailability']['rooms'][$roomType]]);
                    $this->addError('Price Changed.');
                    if ($this->objectIsBooking) {
                        $this->addErrorToBook(trans('messages.priceChanged'));
                    }
                    if ($this->objectIsFromCachedItem) {
                        (new CachedBuffer('package', $this->rawResult['availability']->package_id, $this->seller))->clearCachedBuffer();
                    }
                }
                $requestedPassengersCount = $this->rawResult['passengersCount'][$roomType];
                $this->checkPassengersCount($availablePassengersCount[$roomType], $requestedPassengersCount);
            }
        }

        // Checking the date
        $from = $this->rawResult['availability']->from;
        if ($from < Carbon::now()->addDays($this->object->seller_info['registerLimit'] ?? 0)->format('Y-m-d')) {
            lugError('Booking an outdated availability', ['from' => $from, 'sellerInfo' => $this->object->seller_info, 'now + registerLimit' => Carbon::now()->addDays($this->object->seller_info['registerLimit'] ?? 0)->format('Y-m-d')]);
            $this->addError('Booking an outdated availability.');
            if ($this->objectIsBooking) {
                $this->addErrorToBook(trans('messages.outdated'));
            }
            if ($this->objectIsFromCachedItem) {
                (new CachedBuffer('package', $this->rawResult['availability']->package_id, $this->seller))->clearCachedBuffer();
            }
        }

        ///> chceking if the services are available
        $this->checkFromPlugin();
    }

    protected function checkFromPlugin()
    {
        $items = [
            'priceInfoAvailability' => $this->rawResult['sellectedAvailability'],
            'serviceInfoAvailability' => $this->object->service_info['sellectedAvailability'],
            'sellerInfoAvailability' => $this->object->seller_info['sellectedAvailability'],
            'availabilityId' => $this->object->seller_info['sellectedAvailability']['availabilityId'],
            'adultsAndChildren' => $this->rawResult['passengersCountSum'] - $this->rawResult['infantsCount']
        ];
        $result = apiActionFromMSS('supplier/beforeBook/' . $this->travelService, ['items' => $items], false);
        if (!$result) {
            $this->addError('plugin did not confirm');
            if ($this->objectIsBooking) {
                $this->addErrorToBook(trans('messages.unavailable'));
            }
            if ($this->objectIsFromCachedItem) {
                (new CachedBuffer('package', $this->rawResult['availability']->package_id, $this->seller))->clearCachedBuffer();
            }
        }
    }

    protected function checkFlightAllocationCapacity()
    {
        $flightAllocations = $this->rawResult['flightAllocations'] ?? [];
        foreach ($flightAllocations as $flightAllocation) {
            $flightPassengersCount = 0;
            foreach ($flightAllocation->passengers_info ?? [] as $flightPassengersInfo) {
                unset($flightPassengersInfo['infants']);
                $flightPassengersCount += array_sum(array_map(fn ($passengerTypeGroup) => count($passengerTypeGroup), $flightPassengersInfo ?? []));
            }
            $flightRemainCapacity = $flightAllocation->capacity - $flightPassengersCount;
            if (($this->rawResult['passengersCountSum'] - $this->rawResult['infantsCount']) > $flightRemainCapacity) {
                lugError('The remain capacity for the flight allocation is not enough for the number of the passengers.', ['infantsCount' => $this->rawResult['infantsCount'], 'passengersCountSum' => $this->rawResult['passengersCountSum'], 'flightremain' => $flightRemainCapacity, 'flightPassengersCount' => $flightPassengersCount]);
                $this->addError('Remain Capacity for the flight allocation is not enough.');
                if ($this->objectIsBooking) {
                    $this->addErrorToBook(trans('messages.capacity'));
                }
            }
        }
    }

    protected function checkPassengersCount($roomAvailablePassengersCount, $requestedPassengersCount)
    {
        foreach ($requestedPassengersCount as $room) {
            $aCount = $room['adults'];
            if ($aCount > $roomAvailablePassengersCount['maxAdults']) {
                lugError('adults count mismatch', ['aCount' => $aCount, 'roomAvailablePassengersCount' => $roomAvailablePassengersCount]);
                $this->addError('adult coun mismatch');
                if ($this->objectIsBooking) {
                    $this->addErrorToBook(trans('messages.passengersCountMismatch'));
                }
                if ($this->objectIsFromCachedItem) {
                    (new CachedBuffer('package', $this->rawResult['availability']->package_id, $this->seller))->clearCachedBuffer();
                }
                return false;
            }

            $cCount = $room['children'];
            if ($cCount > $roomAvailablePassengersCount['maxChildren']) {
                lugError('children count mismatch', ['cCount' => $cCount, 'roomAvailablePassengersCount' => $roomAvailablePassengersCount]);
                $this->addError('children count mismatch');
                if ($this->objectIsBooking) {
                    $this->addErrorToBook(trans('messages.passengersCountMismatch'));
                }
                if ($this->objectIsFromCachedItem) {
                    (new CachedBuffer('package', $this->rawResult['availability']->package_id, $this->seller))->clearCachedBuffer();
                }
                return false;
            }

            $cWOCount = $room['childrenWOBed'];
            if ($cWOCount > $roomAvailablePassengersCount['maxChildrenWOBed']) {
                lugError('childrenWOBed count mismatch', ['cWOCount' => $cWOCount, 'roomAvailablePassengersCount' => $roomAvailablePassengersCount]);
                $this->addError('childrenWOBed count mismatch');
                if ($this->objectIsBooking) {
                    $this->addErrorToBook(trans('messages.passengersCountMismatch'));
                }
                if ($this->objectIsFromCachedItem) {
                    (new CachedBuffer('package', $this->rawResult['availability']->package_id, $this->seller))->clearCachedBuffer();
                }
                return false;
            }

            $iCount = $room['infants'];
            if ($iCount > $roomAvailablePassengersCount['maxInfant']) {
                lugError('infants count mismatch', ['iCount' => $iCount, 'roomAvailablePassengersCount' => $roomAvailablePassengersCount]);
                $this->addError('infants count mismatch');
                if ($this->objectIsBooking) {
                    $this->addErrorToBook(trans('messages.passengersCountMismatch'));
                }
                if ($this->objectIsFromCachedItem) {
                    (new CachedBuffer('package', $this->rawResult['availability']->package_id, $this->seller))->clearCachedBuffer();
                }
                return false;
            }

            $totalCount = $aCount + $cCount + $cWOCount;
            if ($totalCount > $roomAvailablePassengersCount['maxCapacity']) {
                lugError('totalCount count mismatch', ['totalCount' => $totalCount, 'roomAvailablePassengersCount' => $roomAvailablePassengersCount]);
                $this->addError('totalCount coun mismatch');
                if ($this->objectIsBooking) {
                    $this->addErrorToBook(trans('messages.passengersCountMismatch'));
                }
                if ($this->objectIsFromCachedItem) {
                    (new CachedBuffer('package', $this->rawResult['availability']->package_id, $this->seller))->clearCachedBuffer();
                }
                return false;
            }

            if ($totalCount < $roomAvailablePassengersCount['minCapacity']) {
                lugError('totalCount count mismatch', ['totalCount' => $totalCount, 'roomAvailablePassengersCount' => $roomAvailablePassengersCount]);
                $this->addError('totalCount count mismatch');
                if ($this->objectIsBooking) {
                    $this->addErrorToBook(trans('messages.passengersCountMismatch'));
                }
                if ($this->objectIsFromCachedItem) {
                    (new CachedBuffer('package', $this->rawResult['availability']->package_id, $this->seller))->clearCachedBuffer();
                }
                return false;
            }
        }
        return true;
    }
}
