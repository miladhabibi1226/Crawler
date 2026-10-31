<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;

trait Revalidate
{
    use BeforeBook;
    protected $responseSample = 'Samples/Parto/FlightRevalidate.json';
    public function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $this->call = [
            'fields' => json_encode(["FareSourceCode" => $this->object->seller_info['FareSourceCode'], "SessionId" => $this->latestSession]),
            'timeout' => 20,
            'apiMethod' => 'Air Revalidate',
            'url' =>  $this->cleanUrlSlash($this->seller->api->configs['url']) . "/Rest/Air/AirRevalidate",
        ];
        return $this->action();
    }

    public function extractor()
    {
        $this->items = [];
        $validateResult = $this->rawResult['decoded'];
        if (empty($validateResult['PricedItinerary']['AirItineraryPricingInfo'])) {
            lugError('no AirItineraryPricingInfo', [$this->rawResult]);
            $this->addError('no AirItineraryPricingInfo');
            $this->addErrorToBook(trans('messages.unavailable'));
            $this->failBook();
            return false;
        }
        $this->checkForPriceChange($validateResult['PricedItinerary']['AirItineraryPricingInfo']['ItinTotalFare']);
        // lugInfo('Parto Flight validate extractor Items', ['items' => $this->items]);
    }
}
