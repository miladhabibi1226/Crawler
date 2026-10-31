<?php

namespace App\TravelServices\Suppliers\FlyErbil\Logics\Flight;

use App\TravelServices\Logics\Issue as LogicsIssue;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;
    protected $latestSession, $ticks = [];
    public function sessionHandling()
    {
        return $this->checkSession($this->object->seller_info['session']);
    }

    protected function decodeRawResult()
    {
        //> next 2line: remove ota:OTA_AirDemandTicketRS attributes and remove ota namespace bc they caused error
        $this->rawResult['body'] = preg_replace('/(<ota:OTA_AirDemandTicketRS)([^>]+)>/', '$1>', $this->rawResult['body']);
        $this->rawResult['body'] = preg_replace('/ota:/m', '', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['decoded'] = json_decode(json_encode($xml), true);
    }

    public function requestGenerator()
    {
        $this->latestSession = json_decode($this->supplierSession->extras, true);
        $auth = $this->seller->api->configs;

        $passengersCount = ($this->object->service_info['query_params']['adults'] ?? 0) + ($this->object->service_info['query_params']['children'] ?? 0) + ($this->object->service_info['query_params']['infants'] ?? 0);
        $rphGroup = '';
        for ($i = 1; $i <= $passengersCount; $i++) {
            $rphGroup = "$rphGroup $i";
        }

        $depends = $auth + [
            'pnr' => $this->object->booking_info['pnr'][0],
            'pnrType' => $this->object->seller_info['bookingResult']['pnrType'],
            'rphGroup' => $rphGroup,
            'sellerCurrency' => $this->seller->currency->abb,
            'passengers' => $this->object->passenger_info,
            'totalPrice' => $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable']
        ];
        lugInfo('Issue Data', $depends);
        $this->call = [
            'fields' => view('FlyErbil.FlightIssue', $depends)->render(),
            'url' => $this->cleanUrlSlash($auth['url']),
            'apiMethod' => 'Demand Ticket',
            'headers' => ['Content-Type:application/xml', 'Authorization:Bearer ' . $this->latestSession['accessToken'], 'X-Refresh-Token:' . $this->latestSession['refreshToken']]
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $ticketResult = $this->rawResult['decoded']['TicketItemInfo'] ?? $this->callException('empty result');
        $this->objectStatus = 'issued';

        $this->items = $this->flightTicketInfo($this->getTickets($ticketResult ?? []));

        lugInfo('FlyErbil Flight Issue extractor Items', ['items' => $this->items, 'ticks' => $this->ticks]);
    }

    protected function getTickets($ticketResult)
    {
        $this->repelSingleMode($ticketResult);
        $passengers = [];
        foreach ($ticketResult as $traveler) {
            if (empty($traveler['@attributes']['TicketNumber'])) {
                $this->objectStatus = 'waiting_issue';
            } else {
                $this->ticks[] = $traveler['@attributes']['TicketNumber'];
            }
            $ticketNumber = [];
            foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                foreach ($leg['segments'] as $segmentIndex => $segment) { ///> FlyErbil has one ticket for all the legs/segments
                    $ticketNumber[$legIndex][$segmentIndex] = $traveler['@attributes']['TicketNumber'] ?? '';
                }
            }
            $passengers[strtoupper(str_replace(' ', '', $traveler['PassengerName']['Surname'] . $traveler['PassengerName']['GivenName']))] = $ticketNumber;
        }
        return $passengers;
    }

    protected function mapper()
    {
        $pnr = implode(',', $this->object->booking_info['pnr']) . ':' . implode(',', array_unique($this->ticks));
        $booking_info = $this->object->booking_info;
        $seller_info = $this->object->seller_info;
        $booking_info = array_merge($this->object->booking_info, ['tickets' => $this->items]);
        $booking_info['issued_at'] = Carbon::now();
        $this->object->booking_info = $booking_info;
        $this->object->seller_info = $seller_info;
        $this->object->status =  $this->objectStatus;
        $this->object->pnr =  $pnr;
        $this->object->update();
        $this->itemIssued($this->object->booking_info['pnr']);
    }
}
