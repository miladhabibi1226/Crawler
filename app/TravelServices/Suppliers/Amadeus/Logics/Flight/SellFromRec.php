<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

trait SellFromRec
{
    protected $method = 'Air_SellFromRecommendation';

    public function requestGenerator()
    {
        $depends = $this->headerData() + $this->queryParams + ['include' => 'SellFromRec', 'rawTimes' => $this->object->seller_info['rawTimes'], 'legsServiceInfo' => $this->modifyLegsData()];
        lugInfo("Amadeus SellFromRec Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('Amadeus.header.start', $depends)->render(),
            'timeout' => 20,
            'url' => $this->cleanUrlSlash($depends['api_url']),
            'apiMethod' => 'SellFromRec'
        ];
        return $this->action();
    }

    protected function modifyLegsData()
    {
        $legs = $this->getServiceInfo()['legs'];
        $lqueryParamsLegs = $this->queryParams['legs'];
        foreach ($legs as $legindex => $leg) {
            $legs[$legindex]['info']['org'] = $lqueryParamsLegs[$legindex]['origin']['airport']['abb'] ?? $lqueryParamsLegs[$legindex]['origin']['city']['abb'];
            $legs[$legindex]['info']['dst'] = $lqueryParamsLegs[$legindex]['destination']['airport']['abb'] ?? $lqueryParamsLegs[$legindex]['destination']['city']['abb'];
        }
        return $legs;
    }

    public function extractor()
    {
        $this->supplierSession = $this->saveSession();
        $result = $this->rawResult['decoded']['Air_SellFromRecommendationReply'] ?? [];
        $itineraryDetails = $result['itineraryDetails'];
        $this->repelSingleMode($itineraryDetails);
        foreach ($itineraryDetails as $itineraryDetail) {
            $segmentInformation = $itineraryDetail['segmentInformation'];
            $this->repelSingleMode($segmentInformation);
            foreach ($segmentInformation as $segment) {
                if (strtoupper($segment['actionDetails']['statusCode'] ?? '') != 'OK') {
                    $this->addError('can not sell this segment');
                    $this->items['failed segments'] = $segment;
                }
            }
        }
        // lugInfo('Amadeus SellFromRec extractor Items', ['failed segments' => $this->items]);
    }

    protected function mapper()
    {
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['beforeBooks']['sellFromRec'] = true;
        $sellerInfo['sessions']['sellFromRec'] = $this->supplierSession->session;
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
