<?php

namespace App\TravelServices\Suppliers\CityNet\Logics\Flight;

trait AsyncTicketing
{
    use Issue; ///> for mapper, done
    public function canBeDone()
    {
        $this->authorizeRequest();
        $this->object->status = 'waiting_issue';
        $this->object->update();
        return true;
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/CityNet/FlightAsyncTicketing.json'));
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->object->seller_info + ['sellerCurrency' => $this->seller->currency->abb];
        lugInfo('Issue Data', $depends);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'method' => 'GET',
            'url' => $this->cleanUrlSlash($auth['url']) . '/report/contract/passenger/byitinerary',
            'apiMethod' => 'report'
        ];
        return $this->action();
    }

    protected function getFields($depends)
    {
        $fields = [
            'ContractNo' => $depends['issueRequestData']['ContractNo'],
            'SubsystemId' => $depends['bookingRequestData'][0]['SubsystemId'],
            'CombinationId' => $depends['bookingRequestData'][0]['CombinationId'],
            'SessionId' => $depends['bookingRequestData'][0]['SessionId'],
        ];
        return $fields;
    }

    protected function extractor()
    {
        $ticketResult = $this->rawResult['decoded']['Items'] ?? [];
        if ($this->rawResult['decoded']['Items'] == 'No active passengers found') {
            $this->items['bookingInfo'] = [
                'pnr' => $this->object->booking_info['pnr'] ?? [],
                'tickets' => $this->flightTicketInfo($this->getTickets($ticketResult['Passengers'] ?? [])),
            ];
            $this->objectStatus = 'canceled';
            sendCancelledBySupplier($this->object);
            $this->items['pnr'] = '';
        } else {
            $this->objectStatus = 'issued';
            $this->items['bookingInfo'] = [
                'pnr' => $this->object->booking_info['pnr'] ?? [],
                'tickets' => $this->flightTicketInfo($this->getTickets($ticketResult['Passengers'] ?? [])),
            ];
            $this->items['pnr'] = implode(',', $this->object->booking_info['pnr'] ?? []) . ':' . implode(',', array_unique(array_merge(...$this->ticks)));
        }
        lugInfo('CityNet Flight AsyncTicketing extractor Items', ['items' => $this->items, 'objectStatus' => $this->objectStatus]);
    }

    protected function getTickets($AirTravelers)
    {
        $passengers = [];
        foreach ($AirTravelers as $passengerKey => $traveler) {
            $ticketNumber = [];
            $travelerFirstName = strtoupper(str_replace(' ', '', $traveler['GivenName'] ?? ''));
            $travelerLastName = strtoupper(str_replace(' ', '', $traveler['SurName'] ?? ''));
            $this->ticks[] = $ticketNumberAll = $traveler['Eticket'] ?? [];

            if (count($ticketNumberAll) == count($this->object->service_info['legs'])) {
                foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                    foreach ($leg['segments'] as $segmentIndex => $segment) {
                        $ticketNumber[$legIndex][$segmentIndex] = $ticketNumberAll[$legIndex] ?? ($ticketNumberAll[0] ?? '');
                        if ($this->isInProgress($ticketNumber[$legIndex][$segmentIndex] ?? 'In Progress')) {
                            $this->objectStatus = 'waiting_issue';
                        }
                    }
                }
            } else {
                ///> this way the tickets might end up in the wrong segment but there really isn't any other way since it is not mentioned in the response that each ticket belongs to which segment
                $ticketIndex = 0;
                foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                    foreach ($leg['segments'] as $segmentIndex => $segment) {
                        $ticketNumber[$legIndex][$segmentIndex] = $ticketNumberAll[$ticketIndex] ?? '';
                        $ticketIndex++;
                    }
                }
            }
            $passengers[$travelerLastName . $travelerFirstName] = $ticketNumber;
        }
        return $passengers;
    }

    protected function isInProgress($value)
    {
        return in_array(($value ?? 'In Progress'), ['In Progress', 'inprocess']);
    }

    protected function done()
    {
        ///> rewriting this here bc this class uses issue trait and issue trait does sellerTransaction in done
        return ['status' => true];
    }
}
