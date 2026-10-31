<?php

namespace App\TravelServices\Suppliers\FlyErbil\Logics\Flight;

use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;

trait FareRules
{
    use FlightFareRules;
    public function canBeDone()
    {
        if ($this->checkSession($this->object->seller_info['session']) === false) {
            $this->addError('Session Expired.');
            return false;
        }
        return true;
    }

    protected function getContent()
    {
        ///> using flyErbil's 'AirPrice' action to get the  fareRules

        ///> next line is commented to use when we're testing and we want to use an example of the response that we have stored
        // $this->fromFile = 1;
        if ($this->fromFile) {
            lugInfo('Reading from file');
            $xml = file_get_contents(storage_path('Samples/FlyErbil/FlightFareRules.xml'));
            //> next 2line: remove ota:OTA_AirLowFareSearchRS attributes and remove ota namespace bc they caused error
            $xml = preg_replace('/(<ota:OTA_AirPriceRS)([^>]+)>/', '$1>', $xml);
            $xml = preg_replace('/ota:/m', '', $xml);
            $this->rawResult['body'] = $xml;
            $xml = new \SimpleXMLElement($xml);
            $this->rawResult['decoded'] = json_decode(json_encode($xml), true);
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }

        //> next 2line: remove ota:OTA_AirLowFareSearchRS attributes and remove ota namespace bc they caused error
        $this->rawResult['body'] = preg_replace('/(<ota:OTA_AirPriceRS)([^>]+)>/', '$1>', $this->rawResult['body']);
        $this->rawResult['body'] = preg_replace('/ota:/m', '', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['decoded'] = json_decode(json_encode($xml), true);
    }

    public function requestGenerator()
    {
        $this->LugTheResponse = false;
        $this->LugTheRequest = false;
        $this->latestSession = json_decode($this->supplierSession->extras, true);
        $auth = $this->seller->api->configs;
        $depends = $auth + ['sellerInfo' => $this->object->seller_info, 'queryParams' => $this->queryParams];
        // lugInfo('FareRules data', $depends);
        $this->call = [
            'fields' => view('FlyErbil.FlightFareRules', $depends)->render(),
            'url' => $this->cleanUrlSlash($auth['url']),
            'headers' => ['Content-Type:application/xml', 'Authorization:Bearer ' . $this->latestSession['accessToken'], 'X-Refresh-Token:' . $this->latestSession['refreshToken']],
            'apiMethod' => 'Fare Rules'
        ];
        return $this->action();
    }

    public function extractor()
    {
        ///> flyErbil doesn't have fareRules in known categories like other suppliers. it has only one rule for each leg
        ///> extracting fare rules in to one group 'common' each leg (by airport and airline)

        $legs = $this->rawResult['decoded']['PricedItineraries']['PricedItinerary']['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'];
        $this->repelSingleMode($legs);
        foreach ($legs as $leg) {
            $segments = $leg['FlightSegment'];
            $this->repelSingleMode($segments);
            foreach ($segments as $segment) {
                $item = [];
                $item['group'] = [
                    'airline' => $segment['MarketingAirline']['@attributes']['Code'],
                    'cities' => $segment['DepartureAirport']['@attributes']['LocationCode'] . '-' . $segment['ArrivalAirport']['@attributes']['LocationCode'],
                ];
                $item['details'][] = [
                    'title' => 'Common',
                    'text' => $segment['TPA_Extensions']['FareRule']
                ];
                $this->items[] = $item;
            }
        }
        lugInfo('FlyErbil FareRules extractor Items', ['Items' => $this->items]);
    }
};
