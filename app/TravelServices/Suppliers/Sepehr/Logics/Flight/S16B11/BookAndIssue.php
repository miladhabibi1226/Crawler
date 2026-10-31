<?php

namespace App\TravelServices\Suppliers\Sepehr\Logics\Flight\S16B11;

use App\TravelServices\Suppliers\Sepehr\Logics\Flight\BookAndIssue as CommonSepehrBookAndIssue;

use Carbon\Carbon;

trait BookAndIssue
{
    use CommonSepehrBookAndIssue;
    protected function getBookUrl()
    {
        return '/api/Partners/Flight/Booking/V11/Book';
    }
    protected function getBookSampleAddress()
    {
        return 'Samples/Sepehr/v16/FlightBook.json';
    }

    protected function getFields($depends)
    {
        $data = [
            'UserName' => $depends['username'],
            'Password' => $depends['password'],
            'DepartureSegment' => [
                'FlightNumber' => $depends['bookingRequestData']['departure']['FlightNumber'],
                'DepartureDateTime' => $depends['bookingRequestData']['departure']['FlightDate'],
                'OriginIataCode' => $depends['bookingRequestData']['departure']['OriginIataCode'],
                'DestinationIataCode' => $depends['bookingRequestData']['departure']['DestinationIataCode'],
                'FareName' => $depends['bookingRequestData']['departure']['FareName'],
                'LockId' => null
            ],
            'ReturningSegment' => null,
            'AdultPassengerList' => $this->processPassengers($depends['passengers']['adults']),
            'ChildPassengerList' => [],
            'InfantPassengerList' => [],
            'MobileNumber' => $depends['passengerContactPhone'] ?? $depends['passengers']['adults'][0]['Phone']['countryPhoneCode'] . $depends['passengers']['adults'][0]['Phone']['phone'],
            'Email' => $depends['passengerContactEmail'] ?? $depends['passengers']['adults'][0]['Email'],
            'TotalPayable' => round($depends['payable'], 2),
            'YourLocalInventoryPnr' => $depends['referenceId']
        ];

        if (isset($depends['passengers']['children'])) {
            $data['ChildPassengerList'] = $this->processPassengers($depends['passengers']['children']);
        }

        if (isset($depends['passengers']['infants'])) {
            $data['InfantPassengerList'] = $this->processPassengers($depends['passengers']['infants']);
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
                $passengerData['IranianNationalId'] = $passenger['National Id'];
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
        $bookResult = $this->rawResult['decoded'] ?? '';
        //empty items
        if (empty($bookResult['PassengerList'])) {
            $this->addError('empty book result');
            $this->object->status = 'incomplete';
            $this->object->update();
            return false;
        }
        // get pnrs
        $originalPnrs = [];
        foreach ($bookResult['PassengerList'] as $item) {
            if (empty($item['DepartureSegment']['OriginalPnr'])) {
                lugError('No PNR');
                $this->objectStatus = 'contact_admin';
            }
            $originalPnrs[] = $item['DepartureSegment']['OriginalPnr'];
        }
        if (isset($item['ReturningSegment']['OriginalPnr'])) {
            if (empty($item['ReturningSegment']['OriginalPnr'])) {
                lugError('No PNR');
                $this->objectStatus = 'contact_admin';
            }
            $originalPnrs[] = $item['ReturningSegment']['OriginalPnr'];
        }

        $this->objectStatus = 'issued';
        $localPnr = $bookResult['LocalPnr'] ?? null;
        if (empty($localPnr)) {
            lugWarning('localPnr is empty', [$bookResult]);
        }
        $this->items['bookingInfo'] = [
            'issued_at' => Carbon::now(),
            'pnr' => array_unique($originalPnrs),
            'tickets' => $this->flightTicketInfo($this->getTickets($bookResult['PassengerList'] ?? [])),
        ];
        $this->items['sellerInfo'] = ['supplierReference' => $localPnr];
        $this->items['pnr'] = implode(',', array_unique($originalPnrs)) . ':' . implode(',', array_unique($this->ticks));
        lugInfo('Sepehr BookAndIssue extractor Items', ['items' => $this->items, 'ticks' => $this->ticks]);
    }

    protected function getTickets($ticketResult)
    {
        $legSegments = $this->getLegSegments();
        $passengers = $ticketNumber = [];
        foreach ($ticketResult as $traveler) {
            //departure
            if (empty($traveler['DepartureSegment']['LocalTicketNumber'])) {
                $this->objectStatus = 'contact_admin';
            } else {
                $this->ticks[] = $traveler['DepartureSegment']['LocalTicketNumber'];
            }
            $departureSegment = $legSegments[$traveler['DepartureSegment']['OriginIataCode'] . '/' . $traveler['DepartureSegment']['DestinationIataCode']];
            $departureTicketNumber[$departureSegment['segmentIndex']] = $traveler['DepartureSegment']['LocalTicketNumber'] ?? '';
            //return
            if (isset($traveler['ReturningSegment'])) {
                if (empty($traveler['ReturningSegment']['LocalTicketNumber'])) {
                    $this->objectStatus = 'contact_admin';
                } else {
                    $this->ticks[] = $traveler['ReturningSegment']['LocalTicketNumber'];
                }
                $returnSegment = $legSegments[$traveler['ReturningSegment']['OriginIataCode'] . '/' . $traveler['ReturningSegment']['DestinationIataCode']];
                $returnTicketNumber[$returnSegment['segmentIndex']] = $traveler['ReturningSegment']['LocalTicketNumber'] ?? '';
            }
            $passengers[strtoupper(str_replace(' ', '', $traveler['LastName'] . $traveler['FirstName']))] = [$departureTicketNumber, $returnTicketNumber ?? []];
        }
        return $passengers;
    }
}
