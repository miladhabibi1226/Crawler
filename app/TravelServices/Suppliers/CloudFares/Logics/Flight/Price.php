<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;

trait Price
{
    use BeforeBook;
    protected $responseSample = 'Samples/CloudFares/Validate.json';
    protected function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $brandIndexes = array_column($this->getBrandIndexes('service_info'), "index");
        $depends = $this->seller->api->configs + $this->object->seller_info + ['brandIndexes' => $brandIndexes];
        lugInfo('Revalidate data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'method' => 'POST',
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/b2b/v1/flight/get-price',
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'Price'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $this->items = [];
        $result = $this->rawResult['decoded']['data'] ?? [];
        if (empty($result)) {
            lugError('Empty fare rules result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->addError(trans('technical'));
        }
        //check price
        $oldPrice = $this->getOldPrice();
        $newPrice = $result['price']['total'];
        if (abs($oldPrice - $newPrice) > 0.1) {
            lugError('Price Changed', ['newPrice' => $newPrice, 'oldPrice' => $oldPrice, 'result' => $result['price']]);
            $this->addError(trans('messages.priceChanged'));
            $this->addErrorToBook(trans('messages.priceChanged'));
            $this->failBook();
            return false;
        }
        $this->items['price_result_id'] = $result['result_id'];
        lugInfo('CloudFares Price extractor Items', ['items' => $this->items]);
    }

    protected function getOldPrice()
    {
        if ($this->objectIsBooking) {
            return $this->object->price_info['bookingCalculations']['sellerPrice']['details']['bySellerCurrency']['payable'];
        } else {
            $total = 0;
            foreach ($this->getBrandIndexes('price_info') as $brandPrice) {
                $total += $brandPrice['sellerPrice']['rawPrice']['total'];
            }
            return $total;
        }
    }

    protected function mapper()
    {
        $seller_info = array_merge($this->object->seller_info,  $this->items);
        $seller_info['beforeBooks']['price'] = true;
        $this->object->seller_info = $seller_info;
        $this->object->update();
    }

    protected function getFields($depends)
    {
        return [
            "trace_id" => $depends['trace_id'],
            "result_id" => $depends['result_id'],
            "brands_id" => $depends['brandIndexes']
        ];
    }
}
