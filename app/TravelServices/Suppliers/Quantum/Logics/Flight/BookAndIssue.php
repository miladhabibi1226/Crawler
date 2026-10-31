<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Flight;

use App\TravelServices\Suppliers\Quantum\Logics\BookAndIssue as LogicsBookAndIssue;

trait BookAndIssue
{
    use LogicsBookAndIssue;
    protected $ticks;
    protected function getFields()
    {
        return [
            'referenceId' => $this->object->seller_info['bufferReferenceId'],
            'passengersInfo' => $this->object->passenger_info,
            'leader' => $this->object->leader,
            'hookUrl' => $this->hookUrl(),
            'clientBookingReference' => $this->object->reference_id
        ];
    }

    protected function extractor()
    {
        $this->items =  $this->rawResult['decoded'];
        if ($this->responseStatusIsInvalid()) {
            $this->addError('invalis status recieved');
            return lugWarning("invalis status recieved from api-out. booking status will be 'contact_admin");
        }

        $pnr = implode(',', (array) ($this->items['bookingInfo']['pnr'] ?? []));
        $ticketInfo = $this->items['bookingInfo']['tickets'] ?? [];
        if ($this->items['bookingStatus'] == 'waiting_issue') {
            $this->needsSchedule = true;
            $this->items['pnr'] =  $pnr;
        } else {
            try {
                $this->ticks = array_column($ticketInfo, 'ticket_number') ?? [];
                $this->ticks = array_merge(...$this->ticks) ?? [];
                $this->ticks = array_merge(...$this->ticks) ?? [];
            } catch (\Throwable $th) {
                $this->ticks = [];
            }
            $this->items['pnr'] =  $pnr . ':' . implode(',', array_unique($this->ticks));
        }

        lugInfo('Quantum Flight book/AndIsuue extractor items', ['Items' => $this->items, 'ticks' => $this->ticks]);
    }
}
