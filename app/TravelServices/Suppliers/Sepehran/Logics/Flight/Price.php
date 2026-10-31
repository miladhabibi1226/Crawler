<?php

namespace App\TravelServices\Suppliers\Sepehran\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;

trait Price
{
    use BeforeBook;

    public function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $this->call = [
            'fields' => json_encode($this->getFields()),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . "/api/v2/flight/AirPrice",
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'Price'
        ];
        return $this->action();
    }

    protected function getFields()
    {
        $data = [
            "outbound" => [
                "ref_number" => $this->object->seller_info['refNumbers']['outbound']
            ],
            "adult" => $this->queryParams['adults'],
            "child" => $this->queryParams['children'],
            "inf" => $this->queryParams['infants']
        ];

        if (!empty($this->object->seller_info['refNumbers']['return'])) {
            $data['return'] = [
                "ref_number" => $this->object->seller_info['refNumbers']['return']
            ];
        }

        return $data;
    }

    public function extractor()
    {
        $this->items = [];
        $validateResult = $this->rawResult['decoded'];
        //check empty
        if (empty($validateResult['total'])) {
            lugError('no priceInfo', [$validateResult]);
            $this->addError(trans('messages.unavailable'));
            $this->addErrorToBook(trans('messages.unavailable'));
            $this->failBook();
            return false;
        }
        //check capacity
        $totalPassengers = $this->queryParams['adults'] + $this->queryParams['children'];
        if (!empty($validateResult['flights'])) {
            foreach ($validateResult['flights'] as $flight) {
                if ($flight['capacity'] == '0' || $flight['capacity'] <  $totalPassengers) {
                    lugError('capacity is full', [$validateResult['flights']]);
                    $this->addError(trans('messages.unavailable'));
                    $this->addErrorToBook(trans('messages.unavailable'));
                    $this->failBook();
                    return false;
                }
            }
        }
        //check price
        $this->checkForPriceChange($validateResult);
    }

    protected function checkForPriceChange($PricedItinerary)
    {
        ///> currently we will fail the booking if the price has changed
        $searchTotalPrice = $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'];
        if (round($PricedItinerary['total'], 4) !== round($searchTotalPrice, 4)) {
            lugError('Price Change', ['oldPrice' => round($PricedItinerary['total'], 4), 'newPrice' => round($searchTotalPrice, 4), 'search' => $this->object->price_info['sellerPrice']['rawPrice'], 'result' => $PricedItinerary]);
            $this->addError(trans('messages.priceChanged'));
            $this->failBook();
            $this->addErrorToBook(trans('messages.priceChanged'));
        }
    }
}
