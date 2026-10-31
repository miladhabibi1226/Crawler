<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;
    protected $airTripType = [];

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Accelaero';
        $this->dataReference = types('supplierDataReference', $this->supplierName);
        $this->supplierSearchValidity = 10;
        $this->sessionLifeTime = 480;
        $this->defaultHeader = [
            "User-Agent:luxota.network",
        ];
    }

    public function priceQuote()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Accelaero-' . $this->travelService . ') -> Price Quote', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Accelaero-' . $this->travelService . ') -> Price Quote', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Accelaero-' . $this->travelService . ') -> Price Quote', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Accelaero-' . $this->travelService . ') -> Price Quote', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Accelaero-' . $this->travelService . ') -> Price Quote', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    protected function haveSupplierResponseError() //> re-written for search
    {
        preg_match('/<ns1:Error([^s][^>]+)>/ims', $this->rawResult['body'], $rawErrors);
        if (!empty($rawErrors)) {
            preg_match('/ShortText="(.*?)"/ims', $this->rawResult['body'], $rawErr);
            lugWSResError("Accelaero response contain error ({$this->seller->alias})", ['supplier-error: ' => $rawErr[1] ?? ($rawErrors[1] ?? $rawErrors), 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawErr[1] ?? ($rawErrors[1] ?? $rawErrors));
            return $rawErr[1] ?? ($rawErrors[1] ?? $rawErrors);
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError("Accelaero response is invalid ({$this->seller->alias})", ['supplier-response: ' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'Invalid Response';
        }
        $this->supplierConversaion();
        return false;
    }

    protected function initialTripType()
    {
        $this->airTripType = match ($this->queryParams['tripType']) {
            'oneWay' => 'OneWay',
            'roundTrip' => 'Return',
            'openReturn' => 'Return',
            'multiDestination' => 'MultiCity'
        };
    }

    protected function getPriceViewName()
    {
        return ($this->seller->api->configs['version'] === 'New')
            ? 'Accelaero.PriceQuoteNew'
            : 'Accelaero.PriceQuote';
    }

    protected function getExtraHeaders($auth)
    {
        if ($this->seller->api->configs['version'] === 'New') {
            $headerTripType = match ($this->queryParams['tripType'] ?? '') {
                'oneWay' => 'ONEWAY',
                'roundTrip' => 'RETURN',
                'multiDestination' => 'MULTICITY',
            };
    
            return [
                'X-AERO-SALES-CHANNEL: OTA',
                'X-AERO-JOURNEY-TYPE: ' . $headerTripType,
                'X-AERO-USERID: ABY' . $auth['username'],
                'X-AERO-AGENT-CODE: ' . $auth['GSA_ID'],
            ];
        }
    
        return [];
    }
    protected function  compareSegments($priceQuoteResult, $OriginDestinationInformation)
    {
        $numberOfSearchSegments = 0;
        foreach ($OriginDestinationInformation['legs'] as $leg) {
            if (!empty($leg['segments'])) {
                $numberOfSearchSegments += count($leg['segments']);
            } else {
                $numberOfSearchSegments += count($leg);
            }
        }
        $numberOfPriceSegments = $priceQuoteResult[0]['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary']['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'];
        return (int)$numberOfSearchSegments === (int) count($numberOfPriceSegments);
    }

    function updateSegmentsWithCabinClass($rawODO)
    {
        $cabinClass = ['Economy' => 'Y', 'First' => 'F', 'Business' => 'C'][$this->queryParams['cabin']];
        $pattern = '/(<ns2:FlightSegment\s[^>]*)(>)/';
        $replacement = '$1 ResCabinClass="' . $cabinClass . '"$2';
        $updatedXml = preg_replace($pattern, $replacement, $rawODO);
        return $updatedXml;
    }
}
