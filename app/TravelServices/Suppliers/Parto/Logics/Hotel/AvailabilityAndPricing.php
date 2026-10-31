<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Hotel;


trait AvailabilityAndPricing
{
    public function canBeDone()
    {
        $this->findOrCreateSupplierSession();
    }

    protected function getContent()
    {
        if ($this->fromFile) {
            $xml = new \SimpleXMLElement(file_get_contents(storage_path('Samples/Parto/HotelAvailabiltyAndPricing.xml')));
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            $this->latestSession = 'fromFile';
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }

        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $fareSouurceCode = $this->object->price_info['selectedCombination']['sellerPrice']['rawPrice']['FareSourceCode'];
        $this->call = [
            'fields' => json_encode(['SessionId' => $this->latestSession, 'FareSourceCode' => $fareSouurceCode]),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . '/Rest/Hotel/HotelCheckRate',
            'timeout' => 20,
            'apiMethod' => 'Availablity And Pricing'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $result = $this->rawResult['decoded']['PricedItinerary'] ?? $this->callException('empty PricedItinerary');

        if ($result['NetRate'] != $this->object->price_info['selectedCombination']['sellerPrice']['rawPrice']['NetRate']) {
            lugError('Price changed', [$result]);
            $this->addError('Price changed');
        }
        $fareSouurceCode = $result['FareSourceCode'] ?? '';
        if (empty($fareSouurceCode)) {
            $this->addError('No fareSouurceCode', [$result]);
        }
        $this->items = [
            'FareSourceCode' => $fareSouurceCode,
            'HotelPolicy' => $result['HotelPolicy'] ?? []
        ];

        lugInfo('Parto AvailabilityAndPricing Extractor items', [$this->items]);
    }

    protected function mapper()
    {
        $this->object->seller_info = $this->object->seller_info + ['checkRateResult' => $this->items];
        $this->object->update();
    }

    protected function failed($error = null)
    {
        if ($this->objectIsBooking) {
            $this->failBook();
        }
        return ['status' => false];
    }
}
