<?php

namespace App\TravelServices\Suppliers\Local\Logics\Hotel;

use App\TravelServices\Logics\Hotel\Search as HotelSearch;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use MatinUtils\NodeTracker\Models\HotelProfile;
use stdClass;

trait Search
{
    use HotelSearch;
    protected $roomPriceList, $hotelsPriceList;

    protected function getContent()
    {
        $this->regularSearch();

        if (empty($this->rawResult)) {
            return $this->addError('No result');
        }
    }

    protected function regularSearch()
    {
        $dates = CarbonPeriod::create($this->queryParams['checkin'], Carbon::create($this->queryParams['checkout'])->subDay())->toArray();
        $ci = $this->queryParams['destination_city']['id'];
        $si = $this->seller->id;
        $bc = $this->queryParams['nationality_country']['id'];
        $availabilities = 'hotel_availabilities';
        $rooms =  'rooms';
        $room_types = 'room_types';
        $room_type_pricings = 'room_type_pricings';

        foreach ($this->queryParams['rooms_detail'] as $groupIndex => $room) {
            $sf = $room['adults'] + $room['children'] ?? 0;
            ///> this is not very acurate for sf. bc the child age range is different in each hotel so some of the children might be actually infants (no bed allocation)
            ///> it will be checked in available rooms tho

            $regex = "ci:$ci,si:$si,.*sf:$sf,";
            $queryResult[$groupIndex] = app('db')->table($availabilities)
                ->select(app('db')->raw("$room_types.hotel_id,room_id,date,min_price"))
                ->join("$rooms", "$rooms.id", "=", "$availabilities.room_id")
                ->join("$room_type_pricings", "$room_type_pricings.id", "=", "$availabilities.room_type_pricing_id")
                ->join("$room_types", "$room_types.id", "=", "$rooms.room_type_id")
                ->whereNotNull("$room_type_pricings.min_price")
                ->where("$availabilities.status", types('roomStatus', 'open'))
                ->where('extra_index', "not like", "%bc:$bc,%")
                ->where('extra_index', "rlike", "$regex")
                ->whereNull("$room_types.deleted_at")
                ->whereNull("$availabilities.deleted_at")
                ->whereNull("$room_type_pricings.deleted_at")
                ->Where(function ($dateQuery) use ($dates) {
                    foreach ($dates as $date) {
                        $dt = $date->format('Y-m-d');
                        $dateQuery->orwhere('extra_index', "like", "%date:$dt,%");
                    }
                })->get()->toArray();
        }
        $hotels = [];
        $this->roomPriceList = [];
        foreach ($queryResult  as $groupIndex => $availabilitiesGroup) {
            foreach ($availabilitiesGroup as $availability) {
                $hotels[$availability->hotel_id][$groupIndex][$availability->date][] = $availability->room_id;
                $this->roomPriceList[$availability->room_id] = $availability->min_price;
            }
        }
        $hotels = $this->cleanUpAvailabilities($hotels);
        $this->rawResult = $this->getHotelCombination($hotels);
    }

    protected function extractor()
    {
        $hotels = HotelProfile::whereIn('hotel_id', array_keys($this->rawResult))->get();
        $this->hotelsPriceList = $this->calculateHotelMinPrice();
        $skippedByexception = 0;
        foreach ($hotels as $hotelProfile) {
            $item = [];
            $item['service'] = $this->hotelModifier($hotelProfile);
            $conditions = $this->getPricingConditions($item['service']);
            if (!$this->checkAccessExceptions($conditions, $this->getAccessExceptions())) {
                $skippedByexception++;
                continue;
            }
            $item['price'] = $this->priceModifier(['min_combination_price' => $this->hotelsPriceList[$hotelProfile->hotel_id]], $conditions);
            $item['seller'] = [
                'onholdable' => true,
                'sellerCode' => $this->seller->abb,
                'passengersForm' => $hotelProfile->passengers_form,
                'ageRange' => $hotelProfile->child_age,
                'afters' => [
                    'availableRooms' => false,
                ],
                'beforeBooks' => [
                    'availabilityAndPricing' => false
                ]
            ];
            $this->items[] = $item;
        }
        if (empty($this->items)) {
            $this->addError('no items passed extractor');
        }
        lugInfo('S>>>> Local Hotel Search Extractor items - ' . $this->seller->alias, ['supplier raw response items count' => count($this->rawResult), 'skippedByexception' => $skippedByexception,  'Inserted items' => count($this->items), 'first 3 items' => array_slice($this->items ?? [], 0, 3)]);
    }

    protected function priceMapper($source)
    {
        $newPrice = new stdClass;
        $newPrice->price = ['totalPrice' => $source['min_combination_price']];
        return $newPrice;
    }

    protected function hotelMapper($hotelProfile)
    {
        $newHotel = new stdClass;
        $newHotel->info = [
            'title' => $hotelProfile->title,
            'property_type' => 0, ///> todo
            'star' => $hotelProfile->rating,
            'tripadvisor_rating'  => $hotelProfile->trip_advisor,
            'districts' => '', ///> todo
            'thumbnail' => $hotelProfile->thumbnail,
            'details' => $hotelProfile->description,
            'tripadvisor_review_url' => '#', ///> todo
            'phone' => $hotelProfile->phone  ?? '-',
            'address' => $hotelProfile->address ?? []
        ];
        $newHotel->hotelId = $hotelProfile->hotel_id;
        return $newHotel;
    }

    protected function getHotelCombination($hotels)
    {
        $combinations = [];
        foreach ($hotels as $hotelId => $availabilitiesGroup) {
            $combinations = array_shift($availabilitiesGroup);
            foreach ($availabilitiesGroup as $availabilities) {
                $combo = [];
                foreach ($availabilities as $room) {
                    foreach ($combinations as $combination) {
                        $input_line = "$combination,$room";
                        if (!preg_match('/\b(\d+)\b.*\b\1\b/', $input_line, $output_array)) {
                            $combo[] = "$combination,$room";
                        }
                    }
                }
                $combinations = $combo;
            }
            if (empty($combinations)) {
                unset($hotels[$hotelId]);
            } else {
                $hotels[$hotelId] = $combinations;
            }
        }
        return $hotels;
    }

    protected function calculateHotelMinPrice()
    {
        $minPrice = [];
        foreach ($this->rawResult as $hotelId => $combinations) {
            foreach ($combinations as $combination) {
                $rooms = explode(',', $combination);
                $hotelMinPrice = array_sum(array_map(function ($room) {
                    return $this->roomPriceList[$room];
                }, $rooms)) * $this->queryParams['nights_no']; ///> inacurate bc we're considering that the price of each room is the same in all dates. actually it might be different
                $minPrice[$hotelId] = ($minPrice[$hotelId] ?? $hotelMinPrice) > $hotelMinPrice ? $hotelMinPrice : ($minPrice[$hotelId] ?? $hotelMinPrice);
            }
        }
        return $minPrice;
    }
}
