<?php

namespace App\TravelServices\Suppliers\Local\Logics\Package;

use App\Jobs\LocalTicketNumber;
use Carbon\Carbon;

trait BookAndIssue
{
    use Book;

    protected function extractor()
    {
        $this->checkAvailabilityAndPricing();
        $this->items['bookingInfo'] = [
            'pnr' => convertId($this->object->id),
            'issued_at' => Carbon::now()
        ];
        lugInfo("Local Tour BookAndIssue extractor Items", ['items' => $this->items]);
    }

    protected function mapper()
    {        
        $this->updateLocalItems();
        $this->object->booking_info = array_merge($this->object->booking_info ?? [], $this->items['bookingInfo'] ?? []);
        $this->object->status =  'issued';
        $this->object->booked_at = Carbon::now();
        $this->object->pnr = $this->items['bookingInfo']['pnr'] . ':';
        $this->object->update();
        $this->itemIssued($this->object->booking_info['pnr']);
        dispatch(new LocalTicketNumber(node()->id, $this->object->id, app('log-system')->getPid()))->onQueue('flight');
    }

    protected function updateLocalItems()
    {
        ///> this is part of the mapper
        ///> updating used availabilities
        $this->updateFlightAllocations($this->rawResult['flightAllocations']);
        $this->updateAvailability();
    } 


    protected function done()
    {
        $this->callAfterIssuePlugin();
        $this->sellerTransaction();
        return ['status' => true];
    }

    protected function callAfterIssuePlugin()
    {
        //> need to commit transaction bc the plugin wants to update flight availability

        $restartIsNeede = dbTransactionIsOppen(); ///> dbTransactionIsOppen exists in commitDBTransaction and startDBTransaction but we can't depend on it bc at the time of the startDBTransaction we can't know if the transaction was closed by the commit here or it was not openned to begin with
        if ($restartIsNeede) {
            commitDBTransaction();
        }
        $data = apiActionFromMSS('supplier/afterIssue/' . $this->travelService, ['availabilityId' => $this->object->seller_info['sellectedAvailability']['availabilityId']]);
        if ($restartIsNeede) {
            startDBTransaction();
        }
        return $data ?? [];
    }
}
