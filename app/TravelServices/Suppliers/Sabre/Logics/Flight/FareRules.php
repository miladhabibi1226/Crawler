<?php

namespace App\TravelServices\Suppliers\Sabre\Logics\Flight;

use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;

trait FareRules
{
    use FlightFareRules;
    protected function canBeDone()
    {
        $this->findOrCreateSupplierSession('soap');
        if (empty($this->supplierSession)) {
            $this->addError('Session Expired.');
            return false;
        }
        return true;
    }

    protected function decodeRawResult()
    {
        foreach ($this->rawResult as $key => $rawResult) {
            $xml = new \SimpleXMLElement($rawResult['body']);
            $this->rawResult[$key]['decoded'] = json_decode(json_encode($xml->xpath('soap-env:Body')), true)[0] ?? [];
            $this->rawResult[$key]['fault'] = json_decode(json_encode($xml->xpath('//soap-env:Fault')), true)[0] ?? [];
        }
    }

    public function requestGenerator()
    {
        // $this->LugTheRequest = true;
        // $this->multi = true;
        $this->LugTheResponse = 0;
        $depends = $this->seller->api->configs + ['session' => $this->supplierSession->session, 'fareRulesRequestData' => $this->object->seller_info['fareRulesRequestData']];
        lugInfo('FareRules data', [$depends]);
        foreach ($this->object->seller_info['fareRulesRequestData'] as $PassengerType => $data) {
            if ($this->queryParams[['ADT' => 'adults', 'CNN' => 'children', 'INF' => 'infants'][$PassengerType]] < 1) continue; ///> if the main search is based on 111 but user's actual search doesn't have children or infants we don't need to get their fare rules
            foreach ($data as $index => $fareSource) {
                $depends['fareInfo'] = $fareSource;
                $fareCode = $fareSource['fareBasisCode'];
                if (empty($calls["$PassengerType-$fareCode"])) {
                    $depends['departure'] = strtoupper($fareSource['directionality']) == 'FROM' ? $this->queryParams['legs'][0]['departure'] : $this->queryParams['legs'][1]['departure'];
                    $calls["$PassengerType-$fareCode"] = $this->call = [
                        'url' => $depends['soapUrl'],
                        'headers' => [
                            "Content-Type:text/xml",
                        ],
                        'timeout' => 20,
                        'fields' => $this->minifyXML(view('Sabre.FlightFareRules', $depends)->render()),
                        'apiMethod' => 'Fare Rules'
                    ];
                    $this->action();
                    $results["$PassengerType-$fareCode"] = $this->rawResult;
                }
            }
        }
        $this->rawResult = $results ?? [];
    }

    public function extractor()
    {
        $fareData =  $this->object->seller_info['fareRulesRequestData'];
        foreach ($this->rawResult as $key => $rawResult) {
            $item = [];
            list($passegerType, $fareCode) = explode('-', $key);
            $fare = [];
            foreach ($fareData[$passegerType]  as $key => $fareInfo) {
                if ($fareInfo['fareBasisCode'] == $fareCode) {
                    $fare = $fareInfo;
                    break;
                }
            }
            $item['group'] = [
                'passengerType' => $passegerType,
                'airline' => $fare['governingCarrier'],
                'airports' => $fare['beginAirport'] . ' - ' . $fare['endAirport']
            ];
            $fareRulesResult = $rawResult['decoded']['OTA_AirRulesRS']['FareRuleInfo']['Rules']['Paragraph'] ?? ($rawResult['decoded']['OTA_AirRulesRS']['DuplicateFareInfo'] ?? []);
            if (empty($fareRulesResult)) {
                lugError('empty fareRulesResult', $rawResult);
                continue;
            }
            $this->repelSingleMode($fareRulesResult);
            foreach ($fareRulesResult as $fareRule) {
                $item['details'][] = [
                    'title' => $fareRule['@attributes']['Title'] ?? 'Fare Info',
                    'text' => $fareRule['Text'] ?? '',
                ];
            }
            $this->items[] = $item;
        }
        // lugInfo('Sabre FareRules extractor Items', ['Items' => $this->items]);
    }

    protected function haveSupplierResponseError()
    {
        foreach ($this->rawResult as $key => $rawResult) {
            $rawError = $rawResult['fault'] ?? null;
            if (!empty($rawError)) {
                $err = $rawError['faultstring'] ?? null;
                lugWSResError("Sabre ({$this->seller->alias}) response contains error", ['supplier error: ' => $rawError['faultstring'] ?? $rawError]);
            } else {
                if ($rawResult['http_code'] != 200) {
                    $err = "Sabre ({$this->seller->alias}) response is invalid: " . $rawResult['http_code'];
                    lugWSResError($err, ['rawResult' => $rawResult]);
                }
            }
        }
        $this->supplierConversaion($err ?? null);
        return $err ?? false;
    }
}
