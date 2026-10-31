<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\Old;

use App\TravelServices\Logics\BeforeBook;

trait AvailabilityAndPricing
{
    use BeforeBook;
    public function sessionHandling()
    {
        return $this->checkSession($this->object->seller_info['session']);
    }

    protected function decodeRawResult()
    {
        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//s:Body')), true);
    }

    public function requestGenerator()
    {
        $combination = $this->object->price_info['selectedCombination'];
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth + ['combination' => $combination['roomIndexes']];
        lugInfo('Blade Data', [$depends]);
        $this->call = [
            'fields' => view('TBO.AvailablityAndPricing', $depends)->render(),
            'url' => $auth['url'],
            'apiMethod' => 'Availablity And Pricing'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $result = $this->rawResult['decoded'][0]['AvailabilityAndPricingResponse'] ?? $this->callException('empty Response');

        if ($result['PriceVerification']['@attributes']['Status'] == false) {
            lugError('Price changed', [$result]);
            $this->addError('Price changed');
            return $this->failBook();
        }

        $this->repelSingleMode($result['HotelCancellationPolicies']['HotelNorms']);
        $this->items = [
            'hotelNorms' => $result['HotelCancellationPolicies']['HotelNorms'] ?? '',
            'availableForBook' => $result['AvailableForBook'] ?? 'false', //> for bookAndIssue
            'availableForConfirmBook' => $result['AvailableForConfirmBook'] ?? 'false', //> for bookOnly
            'priceVerification' => $result['PriceVerification'] ?? 'false',
            'accountInfo' => $result['AccountInfo'] ?? null,
        ];

        if (($this->bookingType == 'bookAndIssue' && $result['AvailableForBook'] == 'false') || ($this->bookingType == 'bookOnly' && $result['AvailableForConfirmBook'] == 'false')) {
            $this->addErrorToBook(trans('messages.unavailableHotel'));
            $this->addError("This item is not available For Booking.");
            $this->failBook();
        }

        lugInfo('TBO AvailabilityAndPricing Extractor items', [$this->items]);
    }

    protected function mapper()
    {
        $this->object->seller_info = $this->object->seller_info + ['supplierAvailabilityResponse' => $this->items];
        $this->object->update();
    }
}
