<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Flight;


trait RetrieveBookingData
{
    protected function getContent()
    {
        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    protected function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends['travelService'] = $this->object->travel_service;
        if (!empty($this->object->seller_info['bookingReferenceId'])) {
            $depends['referenceId'] = $this->object->seller_info['bookingReferenceId'];
        } else {
            $depends['clientBookingReference'] = $this->object->reference_id;
        }
        lugInfo('Request Data', [$depends]);
        $this->call = [
            'api' => 'v0/book/data',
            'host' => $this->cleanUrlSlash($auth['url']),
            'params' => $depends,
            'method' => 'GET',
            'apiMethod' => 'Book Data'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $this->items =  $this->rawResult['decoded'];
        $pnr = implode(',', (array) ($this->items['bookingInfo']['pnr'] ?? []));
        $this->items['pnr'] =  $pnr;
        $ticketInfo = $this->items['bookingInfo']['tickets'] ?? [];
        $bookingStatus = $this->items['bookingStatus'] ?? 'contact_admin';
        if ($bookingStatus == 'issued') {
            $this->ticks = array_column($ticketInfo, 'ticket_number') ?? [];
            $this->ticks = array_merge(...$this->ticks) ?? [];
            $this->ticks = array_merge(...$this->ticks) ?? [];
            $this->items['pnr'] =  $pnr . ':' . implode(',', array_unique($this->ticks));
        }
        lugInfo('Quantum RetrieveBookingData extractor items', ['Items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->booking_info = $this->items['bookingInfo'] ?? [];
        $this->object->async_action = ($this->items['bookingStatus'] ?? 'contact_admin') == 'waiting_issue';
        $this->object->pnr = $this->items['pnr'] ?? null;
        $this->object->status = $this->items['bookingStatus'] ?? 'contact_admin';
        $this->object->update();
        if ($this->object->status == 'issued') {
            $this->itemIssued($this->items['bookingInfo']['pnr'] ?? []);
            $this->sellerTransaction();
        }
    }
}
