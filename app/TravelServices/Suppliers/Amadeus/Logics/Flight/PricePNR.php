<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

use Carbon\Carbon;

trait PricePNR
{
    protected $method = 'Fare_PricePNRWithBookingClass';
    public function canBeDone()
    {
        $this->supplierSession = $this->retrieveSession('sellFromRec');
        if (empty($this->supplierSession)) {
            $this->addError('Session Expired.');
            $this->addErrorToBook(trans('messages.session'));
            return false;
        }
        return true;
    }

    public function requestGenerator()
    {
        $depends = $this->headerData() + ['include' => 'PricePNR', 'session' => json_decode($this->supplierSession->extras, true)];
        lugInfo("Amadeus PricePNR Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('Amadeus.header.inseries', $depends)->render(),
            'timeout' => 20,
            'url' => $this->cleanUrlSlash($depends['api_url']),
            'apiMethod' => 'PricePNR'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $result = $this->rawResult['decoded']['Fare_PricePNRWithBookingClassReply'] ?? [];
        $fareList = $result['fareList'] ?? [];
        if (empty($fareList)) {
            $this->addError('no fareList');
            return false;
        }
        $this->repelSingleMode($fareList);
        foreach ($fareList as $passengerTypeGroup) {
            $paxSegReference = $passengerTypeGroup['paxSegReference']['refDetails'];
            $this->repelSingleMode($paxSegReference);
            $typeCount = count($paxSegReference);

            $fareDataSupInformation = collect($passengerTypeGroup['fareDataInformation']['fareDataSupInformation']);
            $perPassengerTotalFare = $fareDataSupInformation->where('fareDataQualifier', '712')->first(); ///> 712 => Total fare amount
            $totolFare = ($totolFare ?? 0) + ($perPassengerTotalFare['fareAmount'] * $typeCount);
        }

        $searchAmount = $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'] ?? 0;
        if (round(floatval($searchAmount), 5) != round(floatval($totolFare), 5)) {
            lugError('Price Change', ['searchAmount' => $searchAmount, 'totolFare' => $totolFare, 'search' => $this->object->price_info['sellerPrice']['rawPrice'], 'result' => $fareList]);
            $this->failBook();
            $this->addError("Price Has Changed");
            $this->addErrorToBook(trans('messages.priceChanged'));
        }

        $this->items = ['expires_at' => $this->getExpiryTime($fareList[0]['lastTktDate']['dateTime'] ?? []), 'response' => $totolFare, 'search' => $searchAmount];
        lugInfo('Amadeus PricePNR extractor Items', ['items' => $this->items]);
    }

    protected function getExpiryTime($lastTktDate)
    {
        if (empty($lastTktDate['day'])) {
            $dt = Carbon::now()->addHour();
        } else {
            $dt = Carbon::now();
            $dt->year = $lastTktDate['year'];
            $dt->month = $lastTktDate['month'];
            $dt->day = $lastTktDate['day'];
            $dt->hour = $lastTktDate['hour'] ?? 0;
            $dt->minute = $lastTktDate['minutes'] ?? 0;
        }
        return $dt;
    }

    protected function mapper()
    {
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['beforeBooks']['pricePNR'] = true;
        $sellerInfo['expires_at'] = $this->items['expires_at'];
        $this->object->seller_info = $sellerInfo;
        $this->object->update();
    }

    protected function failed($error = null)
    {
        $this->failBook();
        $this->expireSupplierCache();
        $this->callSignOut('sellFromRec' . $this->object->reference_id);
        $err = [];
        foreach ($error ?? [] as $errorMessages) {
            if ($this->getErrorClass($errorMessages) != 'technical') {
                $err[] = $errorMessages;
            }
        }
        if (empty($this->getSupplierErrorMessage())) {
            $error = implode('. ', $err);
        } else {
            $error = $this->getSupplierErrorMessage();
        }
        return ['status' => false, 'message' => $error];
    }
}
