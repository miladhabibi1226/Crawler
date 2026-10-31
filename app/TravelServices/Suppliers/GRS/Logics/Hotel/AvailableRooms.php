<?php

namespace App\TravelServices\Suppliers\GRS\Logics\Hotel;

use App\Models\CommonHotelProfile;
use App\TravelServices\Logics\Hotel\AvailableRooms as CommonAvailableRooms;

trait AvailableRooms
{
    use CommonAvailableRooms {
        CommonAvailableRooms::mapper as parentMapper;
    }
    protected $hotelProfile;

    protected function requestGenerator()
    {
        $this->LugTheResponse = false;
        $this->call = [
            'fields' => '',
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . '/v1/properties/' . $this->object->seller_info['hotelCode'],
            'method' => 'GET',
            'apiMethod' => 'Available Rooms'
        ];
        return $this->action();
    }

    protected function roomMapper()
    {
        $this->hotelProfile = CommonHotelProfile::where('hotel_id', $this->object->hotel_id)->first();
        $profileRooms = collect($this->hotelProfile->rooms);
        $lang = strtolower($this->queryParams['current_lang']);
        foreach ($this->object->seller_info['searchResult']['availableRooms'] as $room) {
            $feomProfile = $profileRooms->where('id', $room['room_type_id'])->first();
            $roomDetail[] = [
                'index' => $room['index'],
                'name' => $feomProfile["name_$lang"] ?? $feomProfile['name'],
                'inclusion' => $room['meal_type_included'] . ($room['room_type_extra_capacity'] > 0 ? ', Extra Bed' : ''),
                'room_type_id' => $room['room_type_id'],
                'planCode' => $room['rate_plan_id'],
                'cancelable' => $room['cancelable'],
                'amenities' => $this->facilityNames($feomProfile['facilities'] ?? []),
                'cancelationPolicies' => [
                    'lastCancellationDeadline' => "",
                    'defaultPolicy' => $this->applyLang($this->hotelProfile->extra_data['rules']['کنسلی'] ?? []),
                    // 'policies' => $policies,
                ],
            ];
        }
        return $roomDetail ?? [];
    }

    protected function facilityNames($facilities)
    {
        $lang = strtolower($this->queryParams['current_lang']);
        foreach ($facilities as $facility) {
            $data[] =  $facility["name_$lang"] ?? $facility['name'];
        }
        return $data ?? [];
    }

    protected function roomCombinationsModifier()
    {
        $roomCombinations = $this->getCombinations();
        if (empty($roomCombinations)) {
            return false;
        }
        $availableRooms = collect($this->object->seller_info['searchResult']['availableRooms']);
        foreach ($roomCombinations ?? [] as $rawCombinations) {
            $rawPrice = [];
            foreach ($rawCombinations['rooms'] as $roomIndex) {
                $rawPrice[] = $availableRooms[$roomIndex];
            }
            $combinationsInfo[] = [
                'roomIndexes' => $rawCombinations['rooms'],
                'roomsFare' => $rawCombinations['pricing']['details'],
                'payable' => $rawCombinations['pricing']['totalPrice'],
                'rawPrice' => $rawPrice
            ];
        }
        return ['roomCombinations' => array_column($roomCombinations, 'rooms'), 'combinationsInfo' => $combinationsInfo];
    }

    protected function getCombinations()
    {
        $grsProfile = $this->rawResult['decoded']['value']['property'] ?? [];
        if (empty($grsProfile)) {
            $this->addError('empty property');
            return false;
        }
        $rules = collect($grsProfile['rules']);
        $this->UpdateProfileRules($rules);

        if (!$this->guestsHaveChildren()) {
            return $this->object->seller_info['searchResult']['combinations'];
        }

        $childrenRulesFromApi = $rules->where('category', 'children')->first()['conditions'];
        $childrenRulesFromProfile = $this->object->seller_info['children'];
        if ($childrenRulesFromProfile == $childrenRulesFromApi) {
            return $this->object->seller_info['searchResult']['combinations'];
        }
        lugWarning('Children rules has changed', ['childrenRulesFromProfile' => $childrenRulesFromProfile, 'childrenRulesFromApi' => $childrenRulesFromApi]);
        $pricing = $this->getPricing([]);
        $seller_info = $this->object->seller_info;
        $seller_info['children'] = $childrenRulesFromApi['conditions'];
        $seller_info['modifiedRoomsDetail'] = $pricing['requestedRooms'];
        $this->object->seller_info = $seller_info;
        $this->object->update();

        return $pricing['combinations'] ?? false;
    }

    protected function UpdateProfileRules($rules)
    {
        $chechInOut = $rules->where('name', 'ورود و خروج') ?? [];
        if (count($chechInOut) > 1) {
            lugWarning('more than one rule for checkin checkout', [$chechInOut]);
            $chechInOut = [];
        } else {
            $chechInOut = $chechInOut[0] ?? [];
        }
        $childAge = null;
        foreach ($rules as $rule) {
            if ($rule['name'] == 'ورود و خروج') {
                continue;
            }
            if ($rule['category'] == 'children') {
                $childAge = $rule['conditions']['max_infant_age'] . ',' . $rule['conditions']['max_child_age'];
                $children = $rule['conditions'];
            }
            $extrarules[$rule['name']] = ['fa' => $rule['description'], 'en' => $rule['description_en'], 'ar' => $rule['description_ar']];
        }
        $this->hotelProfile->child_age = $childAge;
        $this->hotelProfile->extra_data = [
            'CheckIn' => $chechInOut['conditions']['check_in'] ?? null,
            'CheckOut' => $chechInOut['conditions']['check_out'] ?? null,
            'children' => $children ?? [],
            'rules' => $extrarules ?? [],
        ];
        $this->hotelProfile->update();
    }

    protected function extractAvailableRooms($resultRooms)
    {
        return $this->object->seller_info['searchResult']['availableRooms'];
    }

    protected function guestsHaveChildren()
    {
        $hasChildren = false;
        foreach ($this->queryParams['rooms_detail'] as $guests) {
            if (!empty($guests['children'])) {
                $hasChildren = true;
            }
        }
        return $hasChildren;
    }

    protected function mapper()
    {
        $seller = $this->object->seller_info;
        unset($seller['searchResult']);
        $this->object->seller_info = $seller;
        $this->parentMapper();
    }
}
