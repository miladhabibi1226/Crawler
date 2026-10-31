<?php

namespace App\TravelServices\Suppliers\Local\Logics\Hotel;

use App\TravelServices\Suppliers\Local\Logics\Expire as LogicsExpire;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\HotelAvailability;
use MatinUtils\NodeTracker\Models\Invoice;

trait Expire
{
    use LogicsExpire;

    protected function mapper()
    {
        $this->getAvailabilities();
        foreach ($this->availabilities as $availability) {
            if ($this->availabilityIsBlocked($availability)) {
                $availability->delete();
            } else {
                $availability->status = 'open';
                $availability->update();
            }
        }
        $bookingInfo = $this->object->booking_info + ['expired_at' => Carbon::now()];
        $this->object->booking_info = $bookingInfo;
        $this->object->status =  'expired';
        $this->object->update();

        $invoice = Invoice::find($this->object->invoiceItems->first()->invoice_id);
        if (!in_array($invoice->payment_status, ['paid', 'confirmed'])) {
            $invoice->payment_status = 'not_authorized';
            $invoice->update();
        }
    }

    protected function getAvailabilities()
    {
        $rooms = $this->object->price_info['selectedCombination']['sellerPrice']['rawPrice'];
        foreach ($rooms as $roomsPricings) {
            foreach ($roomsPricings['datesPriceList'] as $availabilityInfo) {
                $availabilityIds[] = $availabilityInfo['availability_id'];
            }
        }
        $this->availabilities = HotelAvailability::whereIn('id', $availabilityIds)->get();
        lugInfo('Hotel Availabilities', ['rooms' => $rooms, 'availabilities' => $this->availabilities]);
    }

    protected function availabilityIsBlocked($availability)
    {
        return in_array($availability->id, $this->object->seller_info['blockedAvailabilities'] ?? []);
    }
}
