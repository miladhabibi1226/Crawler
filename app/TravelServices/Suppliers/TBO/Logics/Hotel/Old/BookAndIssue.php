<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\Old;

use Carbon\Carbon;

trait BookAndIssue
{
    use Book;

    public function extractor()
    {
        $result = $this->rawResult['decoded'][0]['HotelBookResponse'] ?? $this->callException('empty Response');

        if (empty($result['ConfirmationNo'])) {
            $this->failBook();
            $this->addError('No PNR');
            return;
        }
        $this->items['bookingResult'] = [
            'pnr' => $result['ConfirmationNo']
        ];
        $this->items['bookingResult']['issued_at'] = Carbon::now();
        $this->items['sellerInfo'] = [
            'status' => $result['BookingStatus'] ??  '',
            'tripId' => $result['TripId'] ?? null,
            'priceChange' => $result['PriceChange']['@attributes']['Status'] ?? false
        ];
        lugInfo("TBO $this->action extractor Items", ['items' => $this->items]);
    }

    protected function mapper() ///> This method will be used for bookAndIssue as well
    {
        $this->updateExtraInfo();
        $this->object->refresh();
        $this->object->booking_info = $this->items['bookingResult'];
        $this->object->status = 'issued';
        $this->object->booked_at = Carbon::now();
        $this->object->pnr = $this->items['bookingResult']['pnr'] . ':';
        $this->object->update();
        $this->itemIssued($this->items['bookingResult']['pnr'] ?? []);
    }

    protected function updateExtraInfo()
    {
        ///> this is part of the mapper
        $seller_info = $this->object->seller_info;
        $seller_info['bookingResult'] = $this->items['sellerInfo'];
        $this->object->seller_info = $seller_info;
    }

    protected function done()
    {
        $this->sellerTransaction();
        return ['status' => true];
    }
}
