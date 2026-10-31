<?php

namespace App\TravelServices\Logics\Hotel;

use App\TravelServices\Logics\Search as LogicsSearch;
use Carbon\Carbon;
use App\Models\Hotel;
use App\Models\HotelBuffer;
use MatinUtils\NodeTracker\Models\HotelSupplier;

trait Search
{
    use LogicsSearch;
    protected $hotelSuppliers = [], $hotelCodes;

    protected function defaultSearchValidity()
    {
        $rooms = array_map(fn($room) => array_sum($room), $this->queryParams['rooms_detail']);
        $passengersCount = array_sum($rooms);
        $maxTime = $passengersCount * 2; ///> 2 min for each passenger
        return $maxTime < $this->minimumDefaultSearchValidity ? $this->minimumDefaultSearchValidity : $maxTime;
    }

    protected function hotelModifier($source)
    {
        $mapped = $this->hotelMapper($source);
        return [
            'supplier' => '',
            'luxotaCode' => $this->seller->luxotaCode->code,
            'searchValidity' => $this->searchValidityDeadline(),
            'hotel' => $mapped->info ?? $this->callException('info is not calculated in hotelMapper'),
            'query_params' => $this->queryParams,
            'support' => ['pid' => ['search' => app('log-system')->getPid()]],
            'attachs' => [
                'hotelId' => $mapped->hotelId
            ],
        ];
    }

    abstract protected function hotelMapper($rooms);

    protected function priceModifier($rawPrices, $conditions)
    {
        $mapped = $this->priceMapper($rawPrices);
        return [
            'priceInfo' => $mapped->price,
            'conditions' => $conditions,
            'rawPrices' => $rawPrices,
        ];
    }

    abstract protected function priceMapper($price);

    protected function checkHotelStarRating($item)
    {
        return $item['service']['hotel']['star'] > 1;
    }

    protected function mapper()
    {
        $hotels = [];
        $skippedForStarRate = 0;
        if (!empty($this->items)) {
            $onholdable = $this->isOnholdable();
            foreach ($this->items as $key => $item) {
                if (!$this->checkHotelStarRating($item)) {
                    $skippedForStarRate++;
                    continue;
                }
                $serviceInfo = $item['service'];
                $priceInfo = $item['price'];
                extract($serviceInfo['attachs']);
                unset($serviceInfo['attachs']);
                $referenceId = createReferenceId();
                $hotels[] = [
                    'reference_id' => $referenceId,
                    'search_query_id' => $this->object->id,
                    'seller_id' => $this->seller->id,
                    'hotel_id' => $hotelId,
                    'seller_info' => json_encode($item['seller']),
                    'service_info' => json_encode($serviceInfo),
                    'price_info' => json_encode($priceInfo),
                    'hotel_title' => $serviceInfo['hotel']['title'],
                    'property_type' => $serviceInfo['hotel']['property_type'],
                    'star' => $serviceInfo['hotel']['star'],
                    'tripadvisor_rating' => $serviceInfo['hotel']['tripadvisor_rating'],
                    'district' => $serviceInfo['hotel']['districts'],
                    'total_b2b' => 0, ///> plugin will fill this
                    'total_b2c' => 0, ///> plugin will fill this
                    'currency_id' => $this->queryParams['marketplace_currency']['id'],
                    'is_onholdable' => ($item['seller']['onholdable'] ?? false) && $onholdable,
                    'created_at' => Carbon::now(),
                ];
            }
            $hotels = $this->calculatePriceforEachItem($hotels);
            $start = microtime(true);
            HotelSupplier::upsert($this->hotelSuppliers, ['data_reference', 'supplier_key'], ['hotel_id']);
            foreach (array_chunk($hotels ?? [], 100) as $hotelBuffers) {
                HotelBuffer::insert($hotelBuffers);
            }
        }
        lugInfo(count($hotels) . ' items inserted for ' . $this->seller->alias . '. Time: ' . (microtime(true) - ($start ?? microtime(true))) . '. skipped for Star Rating :' . $skippedForStarRate);
    }

    protected function loadCountries()
    {
        $supplierCountries = $this->seller->getSupplierCountries()->with('country')->get();
        $countries = [];
        foreach ($supplierCountries as $row) {
            $countries[$row->country->abb] = $row->country->toArray();
        }
        return $countries;
    }

    protected function translateCity()
    {
        $city = $this->seller->getSupplierCities()->with('city')->where('city_id', $this->queryParams['destination_city']['id'])->select('supplier_key')->first()->toArray();

        return $city['supplier_key'];
    }

    protected function getQueryParams()
    {
        return $this->queryParams;
    }

    protected function getHotelId($hotelCode, $hotelTitle)
    {
        if (empty($this->hotelCodes[$hotelCode]['hotel_id'])) {
            $hotel = Hotel::firstOrCreate( ///> need optimization
                [
                    'city_id' => $this->queryParams['destination_city']['id'],
                    'title' => $hotelTitle
                ],
            );
            $this->hotelSuppliers[] = ['hotel_id' => $hotel->id, 'data_reference' => $this->dataReference, 'supplier_key' => $hotelCode];
            $this->hotelCodes[$hotelCode] = ['hotel_id' => $hotel->id, 'data_reference' => $this->dataReference, 'supplier_key' => $hotelCode, 'hotel' => $hotel];
        }
        return $this->hotelCodes[$hotelCode]['hotel_id'];
    }

    public function loadHotels($hotelCodes)
    {
        $dataReference = $this->dataReference;
        $supplierHotels = $this->seller
            ->getSupplierHotels()
            ->whereIn('supplier_key', $hotelCodes)
            ->with(['commonProfile' => function ($query) use ($dataReference) {
                $query->where('data_reference', $dataReference);
            }])
            ->get()
            ->keyBy('supplier_key');
        return $supplierHotels->toArray();
    }

    public function loadDistricts($tags)
    {
        return $this->seller->getSupplierDistricts()->whereIn('supplier_key', $tags)->with('district')->get();
    }

    protected function hotelPricingConditions($serviceInfo)
    {
        return [
            'city_id' => $this->queryParams['destination_city']['id'],
            'country_id' => $this->queryParams['destination_city']['country']['id'],
            'hotel_id' => $serviceInfo['attachs']['hotelId']
        ];
    }
}
