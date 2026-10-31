<?php

namespace App\TravelServices\Suppliers\ITours\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;

trait ValidateFlight
{
    use BeforeBook;
    public function sessionHandling()
    {
        return $this->authorizeRequest();
    }

    public function requestGenerator()
    {
        $key = $this->object->seller_info['bookingRequestData']['key'];
        $fields = ['key' => $key, 'isExtraBaggageRequested' => false];
        $this->call = [
            'fields' => json_encode($fields),
            'timeout' => 20,
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . "/api/Partners/Flight/V2/Validate",
            'apiMethod' => 'validate'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $validateResult = $this->rawResult['decoded']['result'] ?? [];
        if (empty($validateResult)) {
            $this->addError('no result');
            return false;
        }
        $this->checkForPriceChange($validateResult['pricedItinerary']['airItineraryPricingInfo']['itinTotalFare']['totalFare']);
        $this->items = ['validateKey' => $validateResult['pricedItinerary']['key'],];
        lugInfo('ITours Flight validate extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $seller_info = $this->object->seller_info;
        $seller_info['bookingRequestData']['validateKey'] = $this->items['validateKey'];
        $this->object->seller_info = $seller_info;
        $this->object->update();
    }
}
