<?php

namespace App\TravelServices\Suppliers\Kplus\Logics\Flight;


trait Recomendations
{
    protected function getRecommendations($rawResults) ///> used by search and before book
    {
        $requestedLegsCount = $this->queryParams['tripType'] == 'openReturn' ? 2 : count($this->queryParams['legs']); ///> tripType is oneWay => legsCount is 1. tripType is roundTrip => legsCount is 2. tripType is multiDestination => legsCount is 2 or more. tripType is openReturn => legsCount is 1 in queryParams but we have sent 2 in our request to supplier.
        $notEnoughLegs = 0;
        foreach ($rawResults as $SearchResult) {
            foreach ($SearchResult['Results'] as $i => $Result) {
                ///> in round trips some results (type 1) are for one way combinations and we don't want them in our rwsults
                if (count($Result['Legs']) == $requestedLegsCount) {
                    $recs = $this->combineLegs($Result['Legs']);
                    foreach ($recs as $recommendation) {
                        foreach ($Result['Fares'] as $fare) {
                            $keys = $baggageInfo = $segs = [];
                            ///> collecting fareInfo for each seg
                            $alternativeLegs = collect($fare['FareAlternativeLegs']);

                            ///> collecting baggage info for all the segments
                            foreach ($fare['PassengerFares'] as $PassengerFare) {
                                $passengerType = [0 => 'ADT', 1 => 'CHD', 2 => 'INF'][$PassengerFare['PassengerType']];
                                foreach ($PassengerFare['BaggageAllowances'] as $BaggageAllowances) {
                                    $baggageInfo[$BaggageAllowances['SegmentRef']][$passengerType] = $BaggageAllowances;
                                }
                            }

                            ///> setting baggage info and fare info for each seg
                            foreach ($recommendation as $legIndex => $leg) {
                                $fareInfo = $alternativeLegs->where('AlternativeRef', $leg['AlternativeRef'])->first();
                                $FareSegments = collect($fareInfo['FareSegments']);
                                foreach ($leg['Segments'] as $segIndex => $Segment) {
                                    $recommendation[$legIndex]['Segments'][$segIndex]['baggageInfo'] = $baggageInfo[$Segment['SegmentRef']] ?? [];
                                    $recommendation[$legIndex]['Segments'][$segIndex]['fareInfo'] = $FareSegments->where('SegmentRef', $Segment['SegmentRef'])->first();
                                    $segs[$Segment['SegmentRef']] = [
                                        'origin' => $Segment['DepartureAirport']['Code'],
                                        'destination' => $Segment['ArrivalAirport']['Code'],
                                        'airline' => $Segment['TicketingAirline']['Code']
                                    ];
                                }
                                $keys[] = $fareInfo['Key'];
                            }
                            $recommendations[] = [
                                'legs' => $recommendation,
                                'priceInfo' => ['PassengerFares' => $fare['PassengerFares'], 'TotalPrice' => $fare['TotalPrice']],
                                'keys' => $keys,
                                'segReferences' => $segs
                            ];
                        }
                    }
                } else {
                    $notEnoughLegs ++;
                }
            }
        }
        return $recommendations ?? [];
    }

    protected function combineLegs($legs)
    {
        $combinations = [[]]; // Start with an array containing one empty combination
        foreach ($legs as $leg) {
            $newCombinations = [];
            $alternatives = $leg['AlternativeLegs'];
            foreach ($combinations as $combination) {
                foreach ($alternatives as $alternative) {
                    $newCombinations[] = array_merge($combination, [$alternative]);
                }
            }
            $combinations = $newCombinations;
        }
        return $combinations;
    }
}
