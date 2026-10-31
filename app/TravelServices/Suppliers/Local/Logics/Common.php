<?php

namespace App\TravelServices\Suppliers\Local\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;
use MatinUtils\NodeTracker\Models\FlightAllocation;

trait Common
{
    use LogicsCommon;

    protected $sessionLifeTime, $sellerAlias;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Local';
        $this->dataReference = types('supplierDataReference', 'Quantum');
        $this->sellerAlias = $this->seller->alias ?? 'Local';
        $this->cacheAllowed = false;
    }

    public function availabilities()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Availabilites', ['Data' => $this->getErrors(), 'object' => []]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Availabilites', ['Data' => $this->getErrors(), 'object' => []]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Availabilites', ['Data' => $this->getErrors(), 'object' => []]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Availabilites');
        return $this->done();
    }

    public function availableRooms()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->sellerAlias . ' (Local-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'object' => []]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->sellerAlias . ' (Local-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'object' => []]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->sellerAlias . ' (Local-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'object' => []]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->sellerAlias . ' (Local-Hotel) -> - Available Rooms');
        return $this->done();
    }

    public function availabilityAndPricing()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->sellerAlias . ' (Local-Hotel) -> - Availability And Pricing', ['Data' => $this->getErrors(), 'object' => []]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->sellerAlias . ' (Local-Hotel) -> - Availability And Pricing', ['Data' => $this->getErrors(), 'object' => []]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->sellerAlias . ' (Local-Hotel) -> - Availability And Pricing', ['Data' => $this->getErrors(), 'object' => []]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->sellerAlias . ' (Local-Hotel) -> - Availability And Pricing');
        return $this->done();
    }

    public function CheckAvailability()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Check Availabilites', ['Data' => $this->getErrors(), 'object' => []]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Check Availabilites', ['Data' => $this->getErrors(), 'object' => []]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Check Availabilites', ['Data' => $this->getErrors(), 'object' => []]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Check Availabilites', ['Data' => [$this->object->toArray()]]);
        return $this->done();
    }

    public function bookAndissue()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBedone ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> BookAndIssue', ['Data' => $this->getErrors(), 'object' => [$this->object->toArray()]]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> BookAndIssue', ['Data' => $this->getErrors(), 'object' => [$this->object->toArray()]]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> BookAndIssue', ['Data' => $this->getErrors(), 'object' => [$this->object->toArray()]]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> BookAndIssue', ['Data' => $this->getErrors(), 'object' => [$this->object->toArray()]]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> BookAndIssue', ['Data' => [$this->object->toArray()]]);
        return $this->done();
    }

    public function book()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBedone ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Book', ['Data' => $this->getErrors(), 'object' => [$this->object->toArray()]]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Book', ['Data' => $this->getErrors(), 'object' => [$this->object->toArray()]]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Book', ['Data' => $this->getErrors(), 'object' => [$this->object->toArray()]]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Book', ['Data' => $this->getErrors(), 'object' => [$this->object->toArray()]]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Book', ['Data' => [$this->object->toArray()]]);
        return $this->done();
    }

    public function issue()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBedone ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Issue', ['Data' => $this->getErrors(), 'object' => [$this->object->toArray()]]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Issue', ['Data' => $this->getErrors(), 'object' => [$this->object->toArray()]]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Issue', ['Data' => [$this->object->toArray()]]);
        return $this->done();
    }

    public function allocations()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: GetContent ' . $this->sellerAlias . ' (Local-' . ucfirst($this->travelService) . ') -> Allocations', ['Data' => $this->getErrors(), 'object' => $this->object]);
            return $this->failed($this->errors);
        }

        return $this->done();
    }

    protected function cleanUpAvailabilities($hotels)
    {
        foreach ($hotels as $hotelId => $availabilitiesGroup) {
            if (count($availabilitiesGroup) != count($this->queryParams['rooms_detail'])) {
                unset($hotels[$hotelId]);
                continue;
            }
            foreach ($availabilitiesGroup as $groupIndex => $group) {
                if (count($group) != $this->queryParams['nights_no']) {
                    unset($hotels[$hotelId]);
                    continue 2;
                }
                $hotels[$hotelId][$groupIndex] = array_intersect(...array_values($group));
            }
        }
        return $hotels;
    }

    protected function packagePassengerCountSum($passengersCount)
    {
        $infantCount = 0;
        $sum = array_sum(array_map(function ($roomTypeGroup) use (&$infantCount) {
            $infantCount += $roomTypeGroup['infants'];
            return array_sum($roomTypeGroup);
        }, array_merge(...array_values($passengersCount))));
        return ['total' => $sum, 'infants' => $infantCount];
    }

    protected function getFlightAllocationsForPackage($flighInfo)
    {
        if (!empty($flighInfo['departure'] ?? null)) $allocations[] = $flighInfo['departure'];
        if (!empty($flighInfo['return'] ?? null)) $allocations[] = $flighInfo['return'];
        if (!empty($allocations)) {
            return FlightAllocation::whereIn('id', $allocations)->select('id', 'capacity', 'passengers_info')->get();
        }
        return [];
    }

    protected function availablePackagePassengerCount($ogRooms)
    {
        //> 1st bed can only be adult, every other bed can be either child or adult, each extrabed can be adult, child, either or neither (set by admin). max childWOBed is set by admin, infant can be max 2 by defult
        foreach ($ogRooms as $roomType => $room) {
            if (!is_string($roomType)) {
                if (empty($room['name'])) {
                    lugwarning('empty room name', [$ogRooms]);
                }
                $roomType = $room['name'];
            }
            $extraBed1 = ($room["extraBed1"] ?? 'no');
            $extraBed2 = ($room["extraBed2"] ?? 'no');
            $extraBed3 = ($room["extraBed3"] ?? 'no');
            $maxCapacity = ($room['beds'] ?? 0) + $room['childWOBed'] + ($extraBed1 == 'no' ? 0 : 1) + ($extraBed2 == 'no' ? 0 : 1) + ($extraBed3 == 'no' ? 0 : 1);
            $maxAdults = (int) ($room['beds'] ?? 0);
            $maxChild =  ($room['beds'] ?? 0) - 1;
            for ($i = 1; $i <= 3; $i++) {
                $maxAdults += in_array(${"extraBed$i"}, ['adult', 'both']) ? 1 : 0;
                $maxChild += in_array(${"extraBed$i"}, ['child', 'both']) ? 1 : 0;
            }
            $availablePassengersCount[$roomType] = [
                'maxAdults' => $maxAdults,
                'maxChildrenWOBed' => (int) $room['childWOBed'],
                'maxChildren' => $maxChild,
                'maxInfant' => 2,
                'maxCapacity' => $maxCapacity,
                'minCapacity' => (int) ($room['minParticipants'] ?? 0)
            ];
        }
        return $availablePassengersCount ?? [];
    }

    public function failBook()
    {
        $this->object->status = 'failed';
        $this->object->update();
        sendSupplierIssueErrorNotification($this->object, $this->seller, "Process failed. Check the logs", $this->supplierName, $this->supplierConnection['method'] ?? '-');
    }
}
