<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;


trait AsyncTicketing
{
    protected $method = 'PNR_Retrieve';

    public function requestGenerator()
    {
        $depends = $this->headerData() + $this->queryParams + ['include' => 'PNRRetrieve', 'bookingResult' => $this->object->seller_info['bookingResult']['reservation']];
        lugInfo("Amadeus AsyncTicketing Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('Amadeus.header.stateless', $depends)->render(),
            'url' => $this->cleanUrlSlash($depends['api_url']),
            'apiMethod' => 'PNRRetrieve'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $this->objectStatus = 'issued';
        $result = $this->rawResult['decoded']['PNR_Reply'] ?? [];
        $travellersInfo = $result['travellerInfo'] ?? [];
        $originDestinationDetails = $result['originDestinationDetails'] ?? [];
        $dataElements = $result['dataElementsMaster']['dataElementsIndiv'] ?? [];
        $pnrHeader = $result['pnrHeader'] ?? [];
        if (empty($travellersInfo) || empty($dataElements) || empty($pnrHeader) || empty($originDestinationDetails)) {
            $this->objectStatus = 'waiting_issue';
            $this->addError('Unhandled result');
            return lugError('Unhandled result', ['rawResult' => $this->rawResult['decoded']]);
        }
        $this->repelSingleMode($travellersInfo);
        $this->repelSingleMode($dataElements);
        $this->items['bookingInfo'] = [
            'pnr' => $this->getPnr($originDestinationDetails),
            'tickets' => $this->flightTicketInfo($this->getTickets($travellersInfo, $dataElements)),
            'passengerCount' => $psngrsCnt = $this->queryParams['adults'] + ($this->queryParams['children'] ?? 0) + ($this->queryParams['infants'] ?? 0)
        ];
        if (count($this->ticks) != $psngrsCnt) {
            $this->objectStatus = 'waiting_issue';
            $this->addError('ticket count mismatch');
            lugError('ticket count mismatch', ['rawResult' => $this->rawResult['decoded']]);
        }
        $this->items['pnr'] = implode(',', $this->items['bookingInfo']['pnr']) . ':' . implode(',', array_unique($this->ticks));
        lugInfo('Amadeus Flight Tickets extractor Items', ['items' => $this->items, 'ticks' => $this->ticks]);
    }

    protected function getPnr($originDestinationDetails)
    {
        $itineraryInfo = $originDestinationDetails['itineraryInfo'];
        $pnrs = [];
        $this->repelSingleMode($itineraryInfo);
        foreach ($itineraryInfo as $itinerary) {
            $pnrs[] = $itinerary['itineraryReservationInfo']['reservation']['controlNumber'];
        }
        return array_unique($pnrs);
    }

    protected function getTickets($travellersInfo, $dataElements)
    {
        $travellersData = $this->travellersData($travellersInfo);
        foreach ($dataElements as $dataElement) {
            if ($this->isTicketElement($dataElement)) {
                preg_match('/(PAX|INF) ([\w\-]+)/', $dataElement['otherDataFreetext']['longFreetext'] ?? '', $output_array);
                $this->ticks[] = $ticket = $output_array[2];
                $traveler = $travellersData[$this->passengerTattoo($dataElement)][$output_array[1]];
                foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                    foreach ($leg['segments'] as $segmentIndex => $segment) {
                        $ticketNumber[$legIndex][$segmentIndex] = $ticket;
                    }
                }
                $passengerIndex = strtoupper(str_replace(' ', '', $traveler['Last_name'] . $traveler['First_name']));
                $passengers[$passengerIndex] = $ticketNumber;
            }
        }
        return $passengers;
    }

    protected function passengerTattoo($dataElement)
    {
        $reference = $dataElement['referenceForDataElement']['reference'];
        $this->repelSingleMode($reference);
        $reference = collect($reference);
        return $reference->where('qualifier', 'PT')->first()['number'];
    }

    protected function isTicketElement($dataElement)
    {
        return $dataElement['elementManagementData']['segmentName'] == 'FA';
    }

    protected function travellersData($travellersInfo)
    {
        $passengersTatoo = [];
        foreach ($travellersInfo as $ptInfo) {
            $pt = $ptInfo['elementManagementPassenger']['reference']['number'];
            $passengersInfo = $ptInfo['enhancedPassengerData'];
            $this->repelSingleMode($passengersInfo);
            foreach ($passengersInfo as $passenger) {
                $passenger = $passenger['enhancedTravellerInformation'];
                $type = $passenger['travellerNameInfo']['type'] == 'INF' ? 'INF' : 'PAX';
                $passengersTatoo[$pt][$type] = ['First_name' => $passenger['otherPaxNamesDetails']['givenName'], 'Last_name' => $passenger['otherPaxNamesDetails']['surname']];
            }
        }
        return $passengersTatoo;
    }

    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info, $this->items['bookingInfo']);
        $this->object->pnr = $this->items['pnr'];
        $this->object->status = $this->objectStatus;
        $this->object->async_action = $this->objectStatus == 'waiting_issue';
        $this->object->update();
        if ($this->objectStatus == 'issued') {
            $this->itemIssued($this->items['bookingInfo']['pnr']);
        }
    }

    protected function ifBookingThenFail()
    {
        ///> we don't wanna fail book here
    }
}
