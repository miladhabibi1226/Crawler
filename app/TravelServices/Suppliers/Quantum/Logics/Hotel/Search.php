<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Hotel;

use App\Models\Hotel;
use App\TravelServices\Logics\Search as MainSearch;
use App\TravelServices\Suppliers\Quantum\Logics\Search as QuantumSearch;

trait Search
{
    use QuantumSearch, MainSearch {
        QuantumSearch::mapper as MainQuantumMappper;
    }
    protected $resultSample = 'Samples/Quantum/HotelSearch.json';
    protected $newHotels = [];

    public function getFields()
    {
        return [
            'nationality' => $this->queryParams['nationality_country']['id'],
            'city' => $this->queryParams['destination_city']['id'],
            'checkInDate' => $this->queryParams['checkin'],
            'checkOutDate' => $this->queryParams['checkout'],
            'rooms' => $this->queryParams['rooms_detail'],
            'language' => $this->queryParams['current_lang'],
            'currency' => $this->seller->currency_id
        ];
    }

    protected function getSellerInfo($item)
    {
        if (!env('API_INTERNAL', true)) {
            ///> means the data is being provided from an apiOut outside of the network (with different database)
            ///> in this case we need to insert the hotels that don't exist
            $this->newHotels[] = [
                'id' => $item['hotelId'],
                'city_id' => $this->queryParams['destination_city']['id'],
                'title' => $item['serviceInfo']['hotel']['title']
            ];
        }
        $afterSearch = $item['afterSearch'];
        $ogSupplierNeedsDetail = $afterSearch['detail'] ?? false;
        if (!env('API_INTERNAL', true)) {
            ///> means the data is being provided from an apiOut outside of the network (with different database)
            ///> in this case we need to get the hotel Profile from the other server and save it here
            ///> the hotelProfile might already exist in this server but bc it's not conviniant to check it here we'll check in the 'detail' afterSearch
            $afterSearch['detail'] = false;
        }
        return [
            'onholdable' => $item['onholdable'] ?? false,
            'sellerCode' => "{$this->seller->abb}({$item['sellerCode']})",
            'bufferReferenceId' => $item['referenceId'],
            'passengersForm' => $this->updateOriginalPassengerForm($item['passengersForm']),
            'afters' => $afterSearch,
            'createBooks' => $item['hasCreateBookActions'] ? ['getRoomInformation' => false] : [],
            'ogSupplierNeedsDetail' => $ogSupplierNeedsDetail
        ];
    }

    protected function priceModifier($priceDetails, $conditions = [])
    {
        return [
            'priceInfo' => ['totalPrice' => $priceDetails['payable']],
            'conditions' => $conditions,
            'rawPrices' => $priceDetails,
        ];
    }

    protected function getBufferData($item, $onholdable, $now)
    {
        return [
            'reference_id' => $item['referenceId'],
            'search_query_id' => $this->object->id,
            'seller_id' => $this->seller->id,
            'hotel_id' => $item['hotelId'],
            'seller_info' => json_encode($item['sellerInfo']),
            'service_info' => json_encode($item['serviceInfo']),
            'price_info' => json_encode($item['priceInfo']),
            'hotel_title' => $item['serviceInfo']['hotel']['title'],
            'property_type' => $item['serviceInfo']['hotel']['property_type'],
            'star' => $item['serviceInfo']['hotel']['star'],
            'tripadvisor_rating' => $item['serviceInfo']['hotel']['tripadvisor_rating'],
            'district' => $item['serviceInfo']['hotel']['districts'],
            'total_b2b' => 0, ///> plugin will fill this
            'total_b2c' => 0, ///> plugin will fill this
            'is_onholdable' => $onholdable && $item['onholdable'],
            'currency_id' => $this->queryParams['marketplace_currency']['id'],
            'created_at' => $now,
        ];
    }

    protected function mapper()
    {
        if (!env('API_INTERNAL', true)) {
            Hotel::insertOrIgnore($this->newHotels);
        }
        $this->MainQuantumMappper();
    }
}
