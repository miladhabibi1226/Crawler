<?php

namespace App\TravelServices\Suppliers\Travelport\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;

trait Price
{
    use BeforeBook;
    protected $finalAirPricingSolution, $ticks = [];

    protected function decodeRawResult()
    {
        $xml = preg_replace('/<(\/?)(\w+):(\w+)/', '<$1$3', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($xml);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//Body')), true)[0] ?? [];
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $this->object->passenger_info + $auth + ['tripType' => empty($this->queryParams['legs'][1]) ? 'OneWay' : 'Return', 'queryParams' => $this->queryParams, 'legs' => $this->object->seller_info['requestInfo'], 'traceId' => $this->object->seller_info['traceId'], 'passengerUuids' => $this->object->seller_info['passengerUuids']];
        lugInfo('priceInfo', [$depends]);
        $this->call = [
            'fields' => $fields = $this->minifyXML(view('Travelport.FlightPrice', $depends)->render()),
            'url' => $this->cleanUrlSlash($auth['url']),
            'headers' => [
                "Content-length: " . strlen($fields),
            ],
            'apiMethod' => 'Price'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $oldPrice = $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'];
        if (!$this->ectractAirPricingSolutions($oldPrice)) {
            $this->addError("extracting AirpricingSolutions was unsuccessful");
            $this->addErrorToBook(trans('messages.unavailable'));
            $this->failBook();
            return false;
        }
        $segmetnXml = $this->extractSegmentsXml($oldPrice);
        $result = $this->extractAsXml($oldPrice);
        $fareInfoXml = $result['fareInfoXml'];
        $fareNoteXml = $result['fareNoteXml'];
        $hostTokenXml = $result['hostTokenXml'];
        $this->items = [
            'sellerInfo' => [
                'AirPricingSolution' => $this->finalAirPricingSolution,
                'fareInfoXml' => $fareInfoXml,
                'segmentXml' => $segmetnXml,
                'hostTokenXml' => $hostTokenXml,
                'fareNotesXml' => $fareNoteXml
            ],
        ];
    }

    protected function mapper()
    {
        $this->object->seller_info = array_merge($this->object->seller_info ?? [], $this->items['sellerInfo'] ?? []);
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['beforeBooks']['Price'] = true;
        $this->object->seller_info = $sellerInfo;
        $this->object->update();
    }

    protected function extractSegmentsXml($oldPrice)
    {
        $segmentXml = '';
        $xml2 = new \SimpleXMLElement($this->rawResult['body']);
        $xml2->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml2->registerXPathNamespace('air', 'http://www.travelport.com/schema/air_v52_0');
        $airPricingSolutions = $xml2->xpath('//air:AirPricingSolution');

        foreach ($airPricingSolutions as $info) {
            $totalPrice = (string) $info['TotalPrice'];
            $totalPrice = preg_replace('/[^\d\.]/', '', $totalPrice);
            if (round($totalPrice, 5) === round($oldPrice, 5)) {
                $segments = $info->xpath('//air:AirItinerary');
                if (!empty($segments)) {
                    $segmentXml = $segments[0]->asXML();
                } else {
                    lugInfo('No segments found in this AirPricingInfo');
                }
                break;
            }
        }
        $newsegmentXml = $segmentXml;
        $newsegmentXml = str_replace(
            '<air:AirItinerary>',
            '',
            $newsegmentXml
        );

        $newsegmentXml = str_replace(
            '</air:AirItinerary>',
            '',
            $newsegmentXml
        );
        $newsegmentXml = preg_replace('/<air:FlightDetails[^>]*\/>/', '', $newsegmentXml);
        return $newsegmentXml;
    }

    protected function extractAsXml($oldPrice)
    {
        $fareInfos = '';
        $fareText = '';
        $fareInfoCollection = [];
        $xml2 = new \SimpleXMLElement($this->rawResult['body']);
        $xml2->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml2->registerXPathNamespace('air', 'http://www.travelport.com/schema/air_v52_0');
        $xml2->registerXPathNamespace('common', 'http://www.travelport.com/schema/common_v52_0');

        $airPricingSolutions = $xml2->xpath('//air:AirPricingSolution');
        foreach ($airPricingSolutions as $info) {
            // Extract and clean total price
            $totalPrice = (string) $info['TotalPrice'];
            $totalPrice = preg_replace('/[^\d\.]/', '', $totalPrice);
            if (round($totalPrice, 5) === round($oldPrice, 5)) {
                //extract Fare_Notes
                $fareNotes = $info->xpath('.//air:FareNote');
                $fareNoteXml = '';
                $newhostTokenXml = '';
                if (!empty($fareNotes)) {
                    foreach ($fareNotes as $fareNote) {
                        $fareNoteXml .= $fareNote->asXML() . "\n";
                    }
                }
                //extract Host_Tokens
                $hostTokens = $info->xpath('.//common_v52_0:HostToken');
                $hostTokenXml = '';
                foreach ($hostTokens as $hostToken) {
                    $hostTokenXml = $hostToken->asXML();
                    $hostTokenXml = str_replace(
                        '<common_v52_0:HostToken',
                        '<common_v52_0:HostToken xmlns:common_v52_0="http://www.travelport.com/schema/common_v52_0"',
                        $hostTokenXml
                    );
                    $newhostTokenXml .= $hostTokenXml . "\n";
                }
                //extract Fare_Infos
                $airPricingInfos = $info->xpath('.//air:AirPricingInfo');
                foreach ($airPricingInfos as  $index => $airPricingInfo) {
                    $fareText = '';
                    $fareInfos = $airPricingInfo->xpath('.//air:FareInfo');
                    foreach ($fareInfos as $fareInfo) {
                        $fareInfoXml = $fareInfo->asXML();
                        $newfareInfoXml = $fareInfoXml;
                        $newfareInfoXml = str_replace(
                            '<common_v52_0:ServiceData',
                            '<common_v52_0:ServiceData xmlns:common_v52_0="http://www.travelport.com/schema/common_v52_0"',
                            $newfareInfoXml
                        );

                        $newfareInfoXml = str_replace(
                            '<common_v52_0:ServiceInfo',
                            '<common_v52_0:ServiceInfo xmlns:common_v52_0="http://www.travelport.com/schema/common_v52_0"',
                            $newfareInfoXml
                        );

                        $newfareInfoXml = str_replace(
                            '<common_v52_0:Remarks',
                            '<common_v52_0:Remarks xmlns:common_v52_0="http://www.travelport.com/schema/common_v52_0"',
                            $newfareInfoXml
                        );
                        $newfareInfoXml = str_replace(
                            '<common_v52_0:Endorsement',
                            '<common_v52_0:Endorsement xmlns:common_v52_0="http://www.travelport.com/schema/common_v52_0"',
                            $newfareInfoXml
                        );
                        $newfareInfoXml = preg_replace('/<air:Brand\b[^>]*>.*?<\/air:Brand>/s', '', $newfareInfoXml);

                        $fareText .=  $newfareInfoXml . "\n";
                    }
                    $fareInfoCollection[$index] = $fareText;
                }
                break;
            }
        }
        return ['fareInfoXml' => $fareInfoCollection, 'fareNoteXml' => $fareNoteXml, 'hostTokenXml' => $newhostTokenXml];
    }

    protected function ectractAirPricingSolutions($oldPrice)
    {
        $priceCheck = false;
        $xml = preg_replace('/<(\/?)(\w+):(\w+)/', '<$1$3', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($xml);
        //empty result
        if (empty($xml->Body->AirPriceRsp->AirPriceResult->AirPricingSolution)) {
            $this->addError('Empty Price Result');
            return false;
        }
        $results = [
            'TransactionId' => (string) $xml->Body->AirPriceRsp['TransactionId'] ?? '',
            'ResponseTime' => (string) $xml->Body->AirPriceRsp['ResponseTime'] ?? '',
            'AirPricingSolution' => [],
        ];

        foreach ($xml->Body->AirPriceRsp->AirPriceResult->AirPricingSolution as $airPricingSolution) {
            $pricingDetails = [
                'Key' => (string) $airPricingSolution['Key'],
                'TotalPrice' => (string) $airPricingSolution['TotalPrice'],
                'BasePrice' => (string) $airPricingSolution['BasePrice'],
                'ApproximateTotalPrice' => (string) $airPricingSolution['ApproximateTotalPrice'] ?? '',
                'ApproximateBasePrice' => (string) $airPricingSolution['ApproximateBasePrice'] ?? ' ',
                'EquivalentBasePrice' => (string) $airPricingSolution['EquivalentBasePrice'] ?? '',
                'Taxes' => (string) $airPricingSolution['Taxes'] ?? '',
                'ApproximateTaxes' => (string) $airPricingSolution['ApproximateTaxes'] ?? '',
                'AirSegmentRefs' => [],
                'AirPricingInfo' => [],
            ];

            // Extract AirSegmentRefs
            foreach ($airPricingSolution->AirSegmentRef as $segmentRef) {
                $pricingDetails['AirSegmentRefs'][] = (string) $segmentRef['Key'];
            }

            foreach ($airPricingSolution->HostToken as $hostToken) {
                $pricingDetails['HostToken'][] =  str_replace(["\n", " "], '', (string) $hostToken);
            }
            foreach ($airPricingSolution->FareNote as $fareNote) {
                $pricingDetails['FareNote'][] =  str_replace(["\n", " "], '', (string) $fareNote);
            }
            // Extract AirPricingInfo
            foreach ($airPricingSolution->AirPricingInfo as $airPricingInfo) {
                $airPricingDetails = [
                    'Key' => (string) $airPricingInfo['Key'],
                    'TotalPrice' => (string) $airPricingInfo['TotalPrice'],
                    'BasePrice' => (string) $airPricingInfo['BasePrice'],
                    'ApproximateTotalPrice' => (string) $airPricingInfo['ApproximateTotalPrice'],
                    'ApproximateBasePrice' => (string) $airPricingInfo['ApproximateBasePrice'],
                    'Taxes' => (string) $airPricingInfo['Taxes'],
                    'ApproximateTaxes' => (string) $airPricingInfo['ApproximateTaxes'],
                    "LatestTicketingTime" => (string) $airPricingInfo['LatestTicketingTime'],
                    "PricingMethod" => (string) $airPricingInfo['PricingMethod'],
                    "PlatingCarrier" => (string) $airPricingInfo['PlatingCarrier'],

                ];

                if (isset($airPricingInfo->FareCalc)) {
                    foreach ($airPricingInfo->FareCalc as $fareCalc) {
                        $fareCalcInfo = [
                            'fareCalc' => str_replace(["\n", " "], '', (string)$fareCalc),
                        ];
                        $airPricingDetails['FareCalc'][] =    $fareCalcInfo;
                    }
                }

                foreach ($airPricingInfo->PassengerType as $passengerType) {
                    $passengerInfo = [
                        'Code' => (string) $passengerType['Code'],
                    ];
                    $airPricingDetails['PassengerType'][] =   $passengerInfo;
                }

                if (isset($airPricingInfo->TaxInfo)) {
                    foreach ($airPricingInfo->TaxInfo as $taxInfo) {
                        $taxInfo = [
                            'Category' => (string) $taxInfo['Category'],
                            'Amount' => (string) $taxInfo['Amount'],
                            'Key' => (string) $taxInfo['Key'],
                        ];
                        $airPricingDetails['TaxInfo'][] =   $taxInfo;
                    }
                }

                foreach ($airPricingInfo->BookingInfo as $bookingInfo) {
                    $bookingDetails = [
                        'BookingCode' => (string) $bookingInfo['BookingCode'],
                        'CabinClass' => (string) $bookingInfo['CabinClass'],
                        'FareInfoRef' => (string) $bookingInfo['FareInfoRef'],
                        'SegmentRef' => (string) $bookingInfo['SegmentRef'],
                        'HostTokenRef' => (string) $bookingInfo['HostTokenRef'],
                    ];
                    $airPricingDetails['BookingInfo'][] = $bookingDetails;
                }

                $pricingDetails['AirPricingInfo'][] = $airPricingDetails;
            }

            $results['AirPricingSolution'][] = $pricingDetails;
        }

        $airPricingSolutions = $results;
        foreach ($airPricingSolutions['AirPricingSolution'] as $airPricingSolution) {
            $newPrice = preg_replace('/[^\d\.]/', '', $airPricingSolution['TotalPrice']);

            if (round($newPrice, 5) === round($oldPrice, 5)) {
                $this->finalAirPricingSolution = $airPricingSolution;
                $priceCheck = true;
            }
        }
        if (!$priceCheck) {
            lugError('price changed', ['oldPrice' => $oldPrice, 'newPrice' =>  $airPricingSolutions]);
            $this->addError('price changed');
            return false;
        }
        return $results;
    }
}
