<?php

namespace App\TravelServices\Suppliers\Local\Logics\Hotel;

use App\TravelServices\Logics\Hotel\AvailableRooms as HotelAvailableRooms;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

trait AvailableRooms
{
    ///> PRICING INCOMPELETE (CHILD PRICE HANDLING)
    use HotelAvailableRooms;
    protected $passengerTypeCount, $passengerCount = 0, $roomsdata = [], $hotelRooms = [], $combinations = [], $roomDetail = [];
    protected function getContent()
    {
        $this->getPassengerTypeCount();
        $hotelId = $this->object->hotel_id;
        $dates = CarbonPeriod::create($this->queryParams['checkin'], Carbon::create($this->queryParams['checkout'])->subDay());
        $ci = $this->queryParams['destination_city']['id'];
        $si = $this->seller->id;
        $bc = $this->queryParams['nationality_country']['id'];
        $availabilities = 'hotel_availabilities';
        $rooms =  'rooms';
        $room_types = 'room_types';
        $room_type_pricings = 'room_type_pricings';
        $hotel_profiles = 'hotel_profiles';
        foreach ($this->passengerTypeCount as $groupIndex => $room) {
            $sf = $room['adults'] + $room['children'] ?? 0;
            $regex = "ci:$ci,si:$si,.*sf:$sf,";
            $availabilitiesGroup[$groupIndex] = app('db')->table($availabilities)
                ->select(app('db')->raw(
                    "$availabilities.id,$availabilities.status,room_id,room_type_pricing_id,
                inclusions,cancellation_policies,$rooms.room_type_id,date,
                $room_types.amenities,$room_types.description,name,$room_types.suitable_for,child_age"
                ))
                ->join("$rooms", "$rooms.id", "=", "$availabilities.room_id")
                ->join("$room_type_pricings", "$room_type_pricings.id", "=", "$availabilities.room_type_pricing_id")
                ->join("$room_types", "$room_types.id", "=", "$rooms.room_type_id")
                ->join("$hotel_profiles", "$hotel_profiles.hotel_id", "=", "$room_types.hotel_id")
                ->whereNotNull("$room_type_pricings.min_price")
                ->where("$room_types.hotel_id", $hotelId)
                ->where("$availabilities.status", types('roomStatus', 'open'))
                ->where("$availabilities.extra_index", "not like", "%bc:$bc,%")
                ->where("$availabilities.extra_index", "rlike", "$regex")
                ->whereNull("$room_types.deleted_at")
                ->whereNull("$availabilities.deleted_at")
                ->whereNull("$room_type_pricings.deleted_at")
                ->Where(function ($dateQuery) use ($dates, $availabilities) {
                    foreach ($dates as $date) {
                        $dt = $date->format('Y-m-d');
                        $dateQuery->orwhere("$availabilities.extra_index", "like", "%date:$dt,%");
                    }
                })->get();
            if (count($availabilitiesGroup[$groupIndex]) < 1) {
                lugError('No Available Rooms for one of the gueast groups', ['group' => $room, '$groupIndex' => $groupIndex]);
                return $this->addError("No available rooms for one of the gueast groups");
            };
        }

        $groupDates = [];
        foreach ($availabilitiesGroup as $groupIndex => $availabilities) {
            foreach ($availabilities as $availability) {
                $date = $availability->date;
                $groupDates[$groupIndex][$date][] = $availability->room_id;
                if (empty($this->roomsdata[$availability->room_id])) {
                    $this->roomsdata[$availability->room_id] = $availability;
                    unset($this->roomsdata[$availability->room_id]->date);
                }
                $this->roomsdata[$availability->room_id]->dates[$date] = ['availability_id' => $availability->id, 'inclusions' => $availability->inclusions, 'cancellation_policies' => $availability->cancellation_policies];
                unset($this->roomsdata[$availability->room_id]->inclusions);
                unset($this->roomsdata[$availability->room_id]->cancellation_policies);
            }
        }

        $this->hotelRooms = $this->cleanUpAvailabilities([$groupDates])[0] ?? []; ///> function shared by search
        if (empty($this->hotelRooms)) {
            lugError('No Available Rooms', ['$availabilitiesGroup' => $availabilitiesGroup, '$groupDates' => $groupDates]);
            return $this->addError("No available rooms");
        }
    }

    protected function roomMapper()
    {
        $roomIndex = 1;
        foreach ($this->roomsdata as $roomId => $roomInfo) {
            $roomInclusions = $this->inclusionsModifier($roomInfo);
            if (empty($roomInclusions['inclusions'])) {
                lugError('No available inclusion', ['room' => $roomInfo]);
                $this->updateHotelRoomList($roomId);
                continue;
            }
            $roomCancelationPolicies = $this->cancelationPolicisModifier($roomInfo);
            if (empty($roomCancelationPolicies)) {
                lugError('cancellation_policies not matched between dates', ['room' => $roomInfo]);
                $this->updateHotelRoomList($roomId);
                continue;
            }

            $index = [];
            foreach ($roomInclusions['inclusions'] as $inclusion) {
                if ($inclusion['type'] == 'extraBed') {
                    continue;
                }
                $totalPrice = $inclusion['adultPrice']; /// TEMPORARY
                $this->roomsPriceList[$roomIndex] = [
                    'payable' => $totalPrice,
                    'rawPrice' => ['datesPriceList' => $roomInfo->dates, 'inclusion' => $inclusion, 'extraBed' => $roomInclusions['extraBed'], 'suitableFor' => $roomInfo->suitable_for]
                ];

                $data = [
                    'index' => $roomIndex,
                    'name' => $roomInfo->name . (empty($roomInclusions['extraBed']) ? '' : ' (extra bed available)'),
                    'inclusion' => $inclusion['type'],
                    'amenities' => json_decode($roomInfo->amenities, true),
                    'promtion' => '',
                    'cancelationPolicies' => $this->cancelationPolicisModifier($roomInfo),
                ];

                $this->roomDetail[$roomInfo->room_id][$inclusion['type']] = $data;
                $roomDetail[] = $data;

                $index[] = "$roomInfo->room_id:{$inclusion['type']}:$roomInfo->room_type_id";
                $roomIndex++;
            }
            $this->updateHotelRoomList($roomId, $index);
        }
        if (empty($roomDetail)) {
            lugError('No Rooms Available', ['$this->roomsdata' => $this->roomsdata, '$this->hotelRooms' => $this->hotelRooms]);
            $this->addError('No Rooms Available');
        }
        return $roomDetail ?? [];
    }

    public function getPassengerTypeCount()
    {
        $child_age = $this->object->seller_info['ageRange'];
        foreach ($this->queryParams['rooms_detail'] as $index => $room) {
            $childAgeRange = explode('-', $child_age);
            $adultChildren = array_filter($room['child-age'] ?? [], function ($item) use ($childAgeRange) {
                return $item >= $childAgeRange[1];
            });
            $infantChildren = array_filter($room['child-age'] ?? [], function ($item) use ($childAgeRange) {
                return $item <= $childAgeRange[0];
            });
            $this->passengerTypeCount[$index] = [
                'adults' => $room['adults'] + count($adultChildren),
                'children' => $room['children'] - count($adultChildren) - count($infantChildren),
                'infants' => count($infantChildren),
            ];
        }

        $serviceInfo = $this->object->service_info;
        $serviceInfo['passengerCount'] = $this->passengerTypeCount;
        $this->object->service_info = $serviceInfo;
        $this->object->update();
    }

    protected function updateHotelRoomList($roomId, $index = null)
    {
        ///> if the index is null it means the room has an issue so it must be removed from the list of the available rooms
        ///> if not, for each inclusion of each room there is an index item thet replaces the plain roomId
        $this->hotelRooms = array_map(function ($group) use ($roomId, $index) {
            $room = array_search($roomId, $group);
            if ($room !== false) { ///> means room exists for this group
                unset($group[$room]);
                if (!empty($index)) {
                    $group = array_merge($group, $index);
                }
            }
            return $group;
        }, $this->hotelRooms);
    }

    protected function extractCombinations()
    {
        $initialCombinations = $this->hotelCombinationMapper($this->hotelRooms) ?? [];
        foreach ($initialCombinations as $rawCombination) {
            $combinationRooms = explode(',', $rawCombination);
            $roomIndexes = [];
            foreach ($combinationRooms as $roomInfo) {
                list($roomId, $inclusion, $roomTypeId) = explode(':', $roomInfo);
                $roomIndexes[] = $this->roomDetail[$roomId][$inclusion]['index'];
            }
            $finalCombination[] = $roomIndexes;
        }
        return $finalCombination ?? [];
    }

    protected function cancelationPolicisModifier($room)
    {
        ///> Temporarily skippinng rooms that have different cancelation Policies for each day
        $dates = $room->dates;
        $cancelationPolicies = array_column($dates, 'cancellation_policies');
        if (count(array_unique($cancelationPolicies)) > 1) {
            return false;
        }

        $rawData = json_decode($cancelationPolicies[0], true);
        foreach ($rawData['penalties'] ?? [] as $penalty) {
            $toDate = $this->getPenaltyDate($penalty['until'], 'until');
            $fromDate = $this->getPenaltyDate($penalty['from'], 'from');
            if ($toDate <= Carbon::today()) {
                continue;
            }
            $policies[] = [
                'fromDate' => $fromDate->format('Y-m-d'),
                'toDate' => $toDate->format('Y-m-d'),
                'cancellationCharge' => $penalty['percent'],
                'currency' => null,
            ];
            if ($penalty['percent'] == 0) {
                $lastCancellationDeadline = $toDate;
            }
        }
        return [
            'lastCancellationDeadline' => empty($lastCancellationDeadline) ? "-" : $lastCancellationDeadline->addDay()->format('Y-m-d'),
            'defaultPolicy' =>  $rawData['description'] ?? "-",
            'policies' =>  $policies ?? [],
        ];
    }

    protected function inclusionsModifier($room)
    {
        $allInclusions = [];
        $dates = $room->dates;

        ///> To fild only inclusions that are available for all dates
        foreach ($dates as $date => $info) { ///> CHECK IF ALL DATES EXISTS????
            $inclusions = json_decode($info['inclusions'], true);
            foreach ($inclusions as $inclusion) {
                if ($inclusion['status'] ?? false) {
                    $validInclusions[$date][] = $inclusion['type'];
                }
            }
        }
        $validInclusions = array_intersect(...array_values($validInclusions));
        ///> to sum the price of the inclusions in all dates
        foreach ($dates as $date => $info) {
            $inclusions = json_decode($info['inclusions'], true);
            foreach ($inclusions as $inclusion) {
                if (in_array($inclusion['type'], $validInclusions)) {
                    $allInclusions[$inclusion['type']]['type'] = $inclusion['type'];
                    $allInclusions[$inclusion['type']]['adultPrice'] = ($allInclusions[$inclusion['type']]['adultPrice'] ?? 0) + $inclusion['adultPrice'];
                    $allInclusions[$inclusion['type']]['childPrice'] = ($allInclusions[$inclusion['type']]['childPrice'] ?? 0) + $inclusion['childPrice'];
                }
            }
        }

        $extraBed = $allInclusions['extraBed'] ?? [];
        unset($allInclusions['extraBed']);
        return ['inclusions' => $allInclusions, 'extraBed' => $extraBed];
    }

    protected function getPenaltyDate($input, $type)
    {
        if ($input == 0) {
            if ($type == 'from') {
                return Carbon::now();
            }
            return Carbon::create($this->queryParams['checkin']);
        }
        return Carbon::create($this->queryParams['checkin'])->subDays($input);
    }

    protected function hotelCombinationMapper($availabilitiesGroup)
    {
        $combinations = array_shift($availabilitiesGroup);

        ///> this is the same as the filter used bellow, only for the 1st group. will cleanUp later
        ///> will clean Up later
        $combo = [];
        foreach ($combinations as $combination) {
            $regex = preg_replace('/\d+\:/', '\d+\:', $combination);
            if (empty(preg_grep("/$regex/", $combo))) {
                $combo[] = $combination;
            }
        }
        $combinations = $combo;

        foreach ($availabilitiesGroup as $availabilities) {
            $combo = [];
            foreach ($availabilities as $room) {
                foreach ($combinations as $combination) {
                    $input_line = "$combination,$room";
                    if (!preg_match('/\b(,?(\d+:)\w+:\d+)\b.*\b\2\b/', $input_line, $output_array)) { ///> to make sure there are no duplicate rooms in one combination
                        $regex = preg_replace('/\d+\:/', '\d+\:', $input_line);
                        ///> to make sure there are no duplicate roomType+inclusion in the list of combinations 
                        ///> we don't want combinations with the same roomType+inclusions bc have the price
                        if (empty(preg_grep("/$regex/", $combo))) {
                            $combo[] = "$combination,$room";
                        }
                    }
                }
            }
            $combinations = $combo;
        }

        lugInfo('Raw combinations', ['$this->hotelRooms' => $this->hotelRooms, 'combination' => $combinations]);
        return $combinations;
    }

    protected function getRoomPrice($roomIndex, $groupIndex)
    {
        $roomInfo = $this->roomsPriceList[$roomIndex]['rawPrice'];
        $totalPrice = $this->roomsPriceList[$roomIndex]['payable'];
        if ($this->extraBedIsNeeded($roomInfo['suitableFor'], $groupIndex)) {
            if (empty($roomInfo['extraBed'])) {
                lugError('Extrabed needed but with no price', ['room price info' => $roomInfo, 'passengerTypeCount' => $this->passengerTypeCount, 'groupIndex' => $groupIndex]);
                $this->addError('Extrabed needed but with no price');
            }
            $totalPrice += $roomInfo['extraBed']['adultPrice'] ?? 0;
        }
        return $totalPrice;
    }

    protected function extraBedIsNeeded($suitableFor, $groupIndex)
    {
        $passengerCount =  $this->passengerTypeCount[$groupIndex]['adults'] + $this->passengerTypeCount[$groupIndex]['children'];
        return !strstr($suitableFor, $passengerCount);
    }
}
