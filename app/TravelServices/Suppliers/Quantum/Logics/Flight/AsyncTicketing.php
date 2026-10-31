<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Flight;

use Carbon\Carbon;

trait AsyncTicketing
{
    protected $needsSchedule = false;

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
        if (empty($this->object->seller_info['bookingReferenceId'])) {
            return lugError('No referenceId');
        }
        $depends = [
            'referenceId' => $this->object->seller_info['bookingReferenceId'],
        ];
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
        $ticketInfo = $this->items['bookingInfo']['tickets'] ?? [];
        if ($this->items['bookingStatus'] == 'waiting_issue') {
            $this->needsSchedule = true;
            $this->items['pnr'] =  $pnr;
        } else {
            ///> have to use try catch bc parto has problems
            try {
                $this->ticks = array_column($ticketInfo, 'ticket_number') ?? [];
                $this->ticks = array_merge(...$this->ticks) ?? [];
                $this->ticks = array_merge(...$this->ticks) ?? [];
            } catch (\Throwable $th) {
                lugError('parto tickets ' . $th->getMessage(), ['exceptionLine' => $th->getFile() . ' line: ' . $th->getLine(), 'trace' => $th->getTraceAsString()]);
                $this->ticks = [];
            }
            $this->items['pnr'] =  $pnr . ':' . implode(',', array_unique($this->ticks));
            if ($this->items['bookingStatus'] == 'canceled') {
                sendCancelledBySupplier($this->object);
            }
        }
        lugInfo('Quantum book/AndIsuue extractor items', ['Items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->booking_info = $this->items['bookingInfo'];
        $this->object->async_action = ($this->items['bookingStatus'] ?? 'waiting_issue') == 'waiting_issue';
        $this->object->pnr = $this->items['pnr'] ?? null;
        $this->object->status = $this->items['bookingStatus'] ?? 'waiting_issue';
        $this->object->update();
        if ($this->object->status == 'issued') {
            $this->itemIssued($this->items['bookingInfo']['pnr'] ?? []);
            $this->sellerTransaction();
        }
    }
}
