<?php

namespace App\TravelServices\Suppliers\MystiFly\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;

trait Revalidate
{
    use BeforeBook;
    protected $responseSample = 'Samples/MystiFly/FlightRevalidate.json';
    public function sessionHandling()
    {
        return $this->authorizeRequest();
    }

    public function requestGenerator()
    {
        $this->call = [
            'fields' => json_encode(["Target" => "Test", "FareSourceCode" => $this->object->seller_info['FareSourceCode']]),
            'apiMethod' => 'Revalidate',
            'timeout' => 25,
            'url' =>  $this->cleanUrlSlash($this->seller->api->configs['url']) . "/api/v1/Revalidate/Flight",
        ];
        return $this->action();
    }

    public function extractor()
    {
        $this->items = [];
        $validateResult = $this->rawResult['decoded']['Data'] ?? [];

        if ($validateResult['IsValid'] != true || $validateResult['Success'] != true) {
            lugError('data is not valid', [$this->rawResult]);
            $this->addError('not Valid');
            $this->addErrorToBook(trans('messages.unavailable'));
            $this->failBook();
            return false;
        }

        if (empty($validateResult['PricedItineraries'][0]['AirItineraryPricingInfo'])) {
            lugError('no AirItineraryPricingInfo', [$this->rawResult]);
            $this->addError('no AirItineraryPricingInfo');
            $this->addErrorToBook(trans('messages.unavailable'));
            $this->failBook();
            return false;
        }
        //check count of PricedItineraries
        if (count($validateResult['PricedItineraries']) > 1) {
            lugError(' MystiFly revalidate items are more than 1', [$this->rawResult]);
            $this->addError('MystiFly revalidate items are more than 1');
            $this->addErrorToBook(trans('messages.unavailable'));
            $this->failBook();
            return false;
        }
        //checking HoldAllowed
        if ($validateResult['PricedItineraries'][0]['HoldAllowed'] == false && $this->bookingType == 'bookOnly') {
            lugError('this flight can not be holded', [$this->rawResult]);
            $this->addError('this flight can not be holded');
            $this->addErrorToBook(trans('messages.unavailable'));
            $this->failBook();
            return false;
        }
        //checking price
        $this->checkForPriceChange($validateResult['PricedItineraries'][0]['AirItineraryPricingInfo']['ItinTotalFare']);
        $this->items = [
            'sellerInfo' => [
                'validateResult' => [
                    'HoldAllowed' => $validateResult['PricedItineraries'][0]['HoldAllowed'],
                    'PaxNameCharacterLimit' => $validateResult['PricedItineraries'][0]['PaxNameCharacterLimit'] ?? null,
                    'RequiredFieldsToBook' => $validateResult['PricedItineraries'][0]['RequiredFieldsToBook'] ?? ["DOB"],
                    'FareSourceCode' => $validateResult['PricedItineraries'][0]['AirItineraryPricingInfo']['FareSourceCode']
                ],
                'HoldAllowed' => $validateResult['PricedItineraries'][0]['HoldAllowed']
            ],
        ];
        lugInfo('MystiFly Flight validate extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->seller_info = array_merge($this->object->seller_info ?? [], $this->items['sellerInfo'] ?? []);
        $this->object->update();
    }

    protected function checkForPriceChange($ItinTotalFare)
    {
        ///> currently we will fail the booking if the price has changed
        $this->items = [
            'oldPrice' => $searchTotalPrice = $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'],
            'newPrice' => $resultTotalPrice = $ItinTotalFare['TotalFare']['Amount'] ?? 0
        ];
        ///> by PO's request we'll be comparing only the intval of the prices
        if (intval($resultTotalPrice) != intval($searchTotalPrice)) {
            lugError('Price Change', ['search' => $this->object->price_info['sellerPrice']['rawPrice'], $this->items, 'result' => $ItinTotalFare]);
            $this->addError("Price has changed");
            if ($this->objectIsBooking) {
                $this->failBook();
                $this->addErrorToBook(trans('messages.priceChanged'));
            }
        }
    }
}
