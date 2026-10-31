<?php

namespace App\TravelServices\Suppliers\GRS\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'GRS';
        $this->dataReference = types('supplierDataReference', $this->supplierName);
        $this->defaultHeader = [
            'Content-Type:application/json',
            'Client-Token:' . $this->seller->api->configs['token'],
        ];
    }

    protected function getContent() ///> Book class doesn't use this
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/GRS/Hotel' . ucfirst($this->action) . '.json'));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'] ?? '', true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function availableRooms()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (GRS-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (GRS-Hotel) -> - Available Rooms', ['rawResult' => $this->rawResult['decoded'] ?? []]);
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (GRS-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (GRS-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (GRS-Hotel) -> - Available Rooms');
        return $this->done();
    }

    protected function getPricing($resultRooms) //> used in search and availableRooms
    {
        $requestedRooms = $this->queryParamsRoomDetailsAccordingToHotel();
        list($availableRooms, $combinationsRaw) = $this->extractCombinations($resultRooms, $requestedRooms);
        if (empty($combinationsRaw)) return false;
        foreach ($combinationsRaw as $comboKey => $combination) {
            $combinationRooms = $combinations[$comboKey]['rooms'] = explode(',', $combination);
            $raw = [];
            foreach ($combinationRooms as $requestedRoomKey => $roomIndex) {
                $price = 0;
                $roomInfo = $raw[] = $availableRooms[$roomIndex];
                $pricingForEachDay = $roomInfo['rawPricing'];
                foreach ($pricingForEachDay as $pricingForOneDay) {
                    ///> this is all very confusing but it works since we check the rooms total price with GRS in the Book response
                    ///> نیم‌بها ی کودک و یا نفر اضافه زمانی محاسبه می‌شود که ظرفیت اتاق پر شده‌باشد
                    ///> infant is free
                    $price += $pricingForOneDay['grs_rate'];
                    if (($requestedRooms[$requestedRoomKey]['adults'] ?? 0) + ($requestedRooms[$requestedRoomKey]['children'] ?? 0) <= $roomInfo['room_type_capacity']) {
                        continue;
                    }
                    if (($requestedRooms[$requestedRoomKey]['adults'] ?? 0) > $roomInfo['room_type_capacity']) {
                        ///> means extra bed for adult is needded
                        ///> elsewhere we have checked that the number of adults don't get higher than (room_type_capacity + room_type_extra_capacity) 
                        $price += $pricingForOneDay['extend_bed_grs_rate'];
                    }
                    if (($requestedRooms[$requestedRoomKey]['children'] ?? 0) > 0) {
                        ///> means there is extra babies
                        $price += $pricingForOneDay['baby_cot_grs_rate'];
                    }
                }
                $combinations[$comboKey]['pricing']['details'][$requestedRoomKey] = $price;
            }
            $combinations[$comboKey]['pricing']['totalPrice'] = $tp = array_sum($combinations[$comboKey]['pricing']['details']);
            if ($tp <= ($minprice ?? $tp)) {
                $minprice = $tp;
                $rawPrice = $raw;
            }
        }
        return ['minPrice' => ['totalPrice' => $minprice, 'rawPrice' => $rawPrice], 'availableRooms' => $availableRooms, 'combinations' => $combinations, 'requestedRooms' => $requestedRooms];
    }

    protected function extractCombinations($resultRooms, $requestedRooms) //> used in search and availableRooms
    {
        ///> have to make our own combinations, then get their min price
        $temp = $combinations = [];
        $availableRooms = $this->extractAvailableRooms($resultRooms);
        foreach ($requestedRooms as $requestedroomsIndex => $requestedRoom) {
            foreach ($availableRooms as $key => $availableRoom) {
                if ($availableRoom['totalCapacity'] >= $requestedRoom['adults'] && $availableRooms[$key]['inventory'] > 0) { ///> keep it $availableRooms[$key]['inventory'] bc it will be updated
                    if (($requestedRoom['children'] ?? 0) < 1 ||  $availableRoom['accept_child']) {
                        $temp[$requestedroomsIndex][] = $availableRoom['index'];
                        $availableRooms[$key]['inventory'] = $availableRoom['inventory'] - 1;
                    }
                }
            }
        }
        if (count($temp) != count($requestedRooms)) return [[], []]; ////> when inventory of all the rooms are only 1. there won't be any availability for the 2nd (and other) room
        $combinations = $temp[0] ?? [];
        unset($temp[0]);
        foreach ($temp as $requestedroomsIndex => $availabilities) {
            $combo = [];
            foreach ($availabilities as $availableRoom) {
                foreach ($combinations as $combination) {
                    $combo[] = "$combination,$availableRoom";
                }
            }
            $combinations = $combo;
        }
        return [$availableRooms, $combinations];
    }

    protected function queryParamsRoomDetailsAccordingToHotel() //> used in search and availableRooms
    {
        $childrenRules = $this->hotelProfile['extra_data']['children'];
        $requestedRooms = $this->queryParams['rooms_detail'];
        foreach ($requestedRooms as $roomIndex => $guests) {
            if (!empty($guests['child-age'])) {
                foreach ($guests['child-age'] as $index => $age) {
                    if ($age <= $childrenRules['max_infant_age']) {
                        $requestedRooms[$roomIndex]['infants'] = ($requestedRooms[$roomIndex]['infants'] ?? 0) + 1;
                        $requestedRooms[$roomIndex]['children'] = ($requestedRooms[$roomIndex]['children'] ?? 0) - 1;
                        unset($requestedRooms[$roomIndex]['child-age'][$index]);
                    }
                    if ($age > $childrenRules['max_child_age']) {
                        $requestedRooms[$roomIndex]['adults'] = ($requestedRooms[$roomIndex]['adults'] ?? 0) + 1;
                        $requestedRooms[$roomIndex]['children'] = ($requestedRooms[$roomIndex]['children'] ?? 0) - 1;
                        unset($requestedRooms[$roomIndex]['child-age'][$index]);
                    }
                    $requestedRooms[$roomIndex]['child-age'] = array_values($requestedRooms[$roomIndex]['child-age']);
                }
            }
        }
        return $requestedRooms;
    }

    protected function haveSupplierResponseError()
    {
        $rawError = $this->rawResult['decoded']['message'] ?? null;
        if (!empty($rawError)) {
            lugWSResError("GRS response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError ?? $rawError);
            return $rawError ?? $rawError;
        }
        $rawError = $this->rawResult['decoded']['errors'] ?? null;
        if (!empty($rawError)) {
            foreach ($rawError as $error) {
                $errors[] = "{$error['name']}: {$error['message']}";
            }
            $rawError = implode('; ', $errors);
            lugWSResError("GRS response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError ?? $rawError);
            return $rawError ?? $rawError;
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError("GRS response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'GRS response is invalid';
        }
        $this->supplierConversaion();
        return false;
        return true;
    }

    protected function applyLang($item)
    {
        if (!is_array($item)) {
            $item = json_decode($item, true);
        }
        $lang = strtolower($this->queryParams['current_lang']);
        return $item[$lang] ?? ($item['en'] ?? ($item['fa'] ?? ''));
    }
}
