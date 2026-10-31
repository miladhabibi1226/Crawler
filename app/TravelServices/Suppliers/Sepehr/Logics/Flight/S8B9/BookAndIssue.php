<?php

namespace App\TravelServices\Suppliers\Sepehr\Logics\Flight\S8B9;

use App\TravelServices\Suppliers\Sepehr\Logics\Flight\BookAndIssue as CommonSepehrBookAndIssue;

use Carbon\Carbon;

trait BookAndIssue
{

    use CommonSepehrBookAndIssue;
    protected $ticks = [];
    protected function getBookSampleAddress()
    {
        return 'Samples/Sepehr/FlightBook.json';
    }

    protected function getBookUrl()
    {
        return '/api/Partners/Flight/Booking/V9/Book';
    }

    protected function getFields($depends)
    {
        $data = [
            'UserName' => $depends['username'],
            'Password' => $depends['password'],
            'DepartureSegment' => [
                'OriginIataCode' => $depends['bookingRequestData']['OriginIataCode'],
                'DestinationIataCode' => $depends['bookingRequestData']['DestinationIataCode'],
                'DepartureDateTime' => $depends['bookingRequestData']['FlightDate'],
                'FlightNumber' => $depends['bookingRequestData']['FlightNumber'],
                'FareName' => $depends['bookingRequestData']['FareName'],
                'LockId' => null
            ],
            'ReturningSegment' => null,
            'AdultPassengers' => $this->processPassengers($depends['passengers']['adults']),
            'ChildPassengers' => [],
            'InfantPassengers' => [],
            'MobileNumber' => $depends['passengerContactPhone'] ?? $depends['passengers']['adults'][0]['Phone']['countryPhoneCode'] . $depends['passengers']['adults'][0]['Phone']['phone'],
            'Email' => $depends['passengerContactEmail'] ?? $depends['passengers']['adults'][0]['Email'],
            'TotalPayable' => $depends['payable'],
            'YourLocalInventoryPnr' => $depends['referenceId']
        ];

        if (isset($depends['passengers']['children'])) {
            $data['ChildPassengers'] = $this->processPassengers($depends['passengers']['children']);
        }

        if (isset($depends['passengers']['infants'])) {
            $data['InfantPassengers'] = $this->processPassengers($depends['passengers']['infants']);
        }
        if (!empty($depends['bookingRequestData']['return'])) {
            $data['ReturningSegment'] = [
                'FlightNumber' => $depends['bookingRequestData']['return']['FlightNumber'],
                'DepartureDateTime' => $depends['bookingRequestData']['return']['FlightDate'],
                'OriginIataCode' => $depends['bookingRequestData']['return']['OriginIataCode'],
                'DestinationIataCode' => $depends['bookingRequestData']['return']['DestinationIataCode'],
                'FareName' => $depends['bookingRequestData']['return']['FareName'],
                'LockId' => null
            ];
        }
        return $data;
    }

    private function processPassengers($passengers)
    {
        return array_map(function ($passenger) {
            $passengerData = [
                'Title' => $passenger['title'],
                'FirstName' => $passenger['First_name'],
                'LastName' => $passenger['Last_name'],
                'BirthDate' => $passenger['Date of birth']
            ];

            if ($this->object->seller_info['isDomesticFlight'] && $passenger['CountriesInfo']['Nationality']['abb'] == 'IR') {
                $passengerData['IranianCartMelli'] =
                    ['CodeMelli' => $passenger['National Id']];
            } else {
                $passengerData['Passport'] = [
                    'Number' => $passenger['Passport Number'],
                    'ExpiryDate' => $passenger['Passport Expiry Date'],
                    'NationalityCountryCode' => $passenger['CountriesInfo']['Nationality']['abb'],
                    'PlaceOfIssueCountryCode' => $passenger['CountriesInfo']['Passport Issuing Country']['abb']
                ];
            }
            return $passengerData;
        }, $passengers);
    }

    protected function extractor()
    {
        $rawResults = $this->rawResult['decoded'] ?? '';
         //empty items
         if (empty($rawResults['Passengers'])) {
            $this->addError('empty book result');
            $this->object->status = 'incomplete';
            $this->object->update();
            return false;
        }
        //check pnrs
        if (empty($pnr)) {
            $this->objectStatus = 'waiting_issue';
        }
        $this->objectStatus = 'issued';
        $pnr = $rawResults['Pnr'] ?? null;
        $this->items['bookingInfo'] = [
            'issued_at' => Carbon::now(),
            'pnr' => [$pnr],
            'tickets' => $ticketInfo = $this->flightTicketInfo($this->getTickets($rawResults['Passengers'] ?? []))
        ];
        $this->items['pnr'] = $pnr . ':' . implode(',', array_unique($this->ticks));

        lugInfo('Sepehr BookAndIssue extractor Items', ['items' => $this->items, 'ticks' => $this->ticks]);
    }

    protected function getTickets($ticketResult)
    {
        $legSegments = $this->getLegSegments();
        $passengers = $ticketNumber = [];
        foreach ($ticketResult as $traveler) { ///> Sepehr has only 1 legSegment
            if (empty($traveler['DepartureSegment']['TicketNumber'])) {
                $this->objectStatus = 'waiting_issue';
            } else {
                $this->ticks[] = $traveler['DepartureSegment']['TicketNumber'];
            }
            $segment = $legSegments[$traveler['DepartureSegment']['OriginIataCode'] . '/' . $traveler['DepartureSegment']['DestinationIataCode']];
            $ticketNumber[$segment['legIndex']][$segment['segmentIndex']] = $traveler['DepartureSegment']['TicketNumber'] ?? '';
            $passengers[strtoupper(str_replace(' ', '', $traveler['LastName'] . $traveler['FirstName']))] = $ticketNumber;
        }
        return $passengers;
    }
}
