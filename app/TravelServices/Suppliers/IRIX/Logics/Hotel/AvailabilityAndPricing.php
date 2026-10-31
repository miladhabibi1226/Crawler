<?php

namespace App\TravelServices\Suppliers\IRIX\Logics\Hotel;


trait AvailabilityAndPricing
{
    public function requestGenerator()
    {
        $depends = $this->object->service_info + $this->object->seller_info + $this->seller->api->configs;
        $offerIndex = $this->findOfferId();
        if (empty($offerIndex)) {
            return $this->addError('OfferId not found');
        }
        lugInfo("IRIX Hotel AvailabilityAndPricing Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields()),
            'method' => "POST",
            'timeout' => 20,
            'url' =>  "{$depends['url']}/api/hotels/v1/search/results/{$depends['srkToken']}/hotels/{$depends['hotelCode']}/offers/{$offerIndex}/availability?token={$depends['resultsToken']}",
            'apiMethod' => 'HotelAvailabilityAndPricing'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $result = $this->rawResult['decoded'] ?? $this->addError('empty Response');
        if (empty($result['availabilityToken'])) {
            return $this->addError('empty availabilityToken', [$this->rawResult]);
        }

        $price = $this->getPriceFromPriceInfo();
        if ($result['package']['price']['selling']['value'] != $price) {
            lugError('IRIX AvailabilityAndPricing price mismatch', ['resultPrice' => $result['package']['price']['selling']['value'], 'PriceInfo' => $this->object->price_info]);
            return $this->addError('mismatch payable price');
        }

        if ($result['package']['complete'] != 'true' && $result['package']['status'] != 'OK') {
            lugError('IRIX AvailabilityAndPricing status is not OK', ['complete' => $result['package']['complete'], 'status' => $result['package']['status']]);
            return $this->addError('error in preBook');
        }

        $this->items['availabilityToken'] = ['availabilityToken' => $result['availabilityToken']];
        $remarks = [];
        foreach ($result['remarks'] as $remark) {
            $remarks[$remark['name']] = implode(';', $remark['list']);
        }
        $this->items['remarks'] =  $remarks;
        lugInfo('IRIX AvailabilityAndPricing Extractor items', [$this->items]);
    }

    protected function mapper()
    {
        ///> 1- when request comes from apiout, in buffer we dont have booking_info, so here we put remarks in seller_info
        ///> 2- later, in book api(next step), we put remarks from seller_info into booking_info and remove it from seller_info
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['remarks'] = $this->items['remarks'];
        $this->object->seller_info = $sellerInfo;
        $this->object->seller_info = $this->object->seller_info + $this->items['availabilityToken'];
        $this->object->update();
    }

    protected function getPriceFromPriceInfo()
    {
        if ($this->objectIsBooking) {
            ///> marketplace - in serviceInfo search in selectedCombination
            $price = $this->object->price_info['bookingCalculations']['sellerPrice']['details']['bySellerCurrency']['payable'];
        } else {
            ///> apiout - in serviceInfo search in rooms
            $price = $this->object->price_info['selectedCombination']['sellerPrice']['details']['bySellerCurrency']['payable'];
        }
        return $price;
    }

    protected function getFields()
    {
        $depends = $this->object->price_info;
        $roomsTokens = $depends['selectedCombination']['sellerPrice']['rawPrice']['roomTokens'];
        return [
            "packageToken" => $depends['selectedCombination']['sellerPrice']['rawPrice']['token'],
            "roomTokens" => $roomsTokens
        ];
    }

    protected function findOfferId()
    {
        $roomIndexInPriceInfo = $this->object->price_info['selectedCombination']['roomIndexes'][0];
        if ($this->objectIsBooking) {
            ///> marketplace - in serviceInfo search in selectedCombination
            $offerId = explode(':', $this->object->service_info['selectedCombination'][0]['code'])[0];
        } else {
            ///> apiout - in serviceInfo search in rooms
            foreach ($this->object->service_info['rooms'] as $room) {
                if ($room['index'] == $roomIndexInPriceInfo) {
                    $offerId = explode(':', $room['code'])[0];
                    break;
                }
            }
        }
        if (empty($offerId)) {
            lugError("offerId is empty");
            return;
        }
        return $offerId;
    }

    protected function failed($error = null)
    {
        $this->failBook();
        return ['status' => false];
    }
}
