<?php

namespace App\TravelServices\Suppliers\Quantum\Logics;

use App\TravelServices\Logics\Book as LogicsBook;
use Carbon\Carbon;

trait Book
{
    use LogicsBook;

    protected function extractor()
    {
        if (str_starts_with(($this->rawResult['http_code'] ?? 200), '5')) {
            $this->addError('invalis status recieved');
            lugWarning("invalis status recieved ({$this->rawResult['http_code']}) from api-out. booking status will be 'contact_admin");
        }
        $this->items['bookingInfo'] = $this->rawResult['decoded']['bookingInfo'];
        $this->items['sellerInfo'] = [
            'bookingReferenceId' => $this->rawResult['decoded']['bookingReferenceId'],
            'supplierReference' => $this->rawResult['decoded']['bookingSerial'] ?? '',
        ];
        lugInfo("Quantum Flight Book extractor items", ['Items' => $this->items]);
    }

    protected function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->getFields();
        if (empty($depends['requestType'])) {
            $depends['requestType'] = env('API_INTERNAL', true) ? 'internal' : 'internalWithData';
        }
        // lugInfo('Request Data', [$depends]);
        $this->call = [
            'api' => 'v0/book',
            'variables' => [$this->travelService],
            'host' => $auth['url'],
            'fields' => $depends,
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    protected function mapper()
    {
        if (!$this->responseStatusIsInvalid()) {
            $expireOn =  $this->checkExpiryTime(Carbon::create($this->items['bookingInfo']['expires_at']));
            $this->object->booking_info = $this->items['bookingInfo'];
            $this->object->status = 'on_hold';
            $this->object->booked_at = Carbon::now();
            $this->object->expire_on = $expireOn;
            $this->object->unlock_on = null;
            $this->object->pnr = $this->items['pnr'] ??  implode(',', (array) ($this->items['bookingInfo']['pnr'] ?? [])) . ':';
        }
        $this->object->seller_info = array_merge($this->object->seller_info, $this->items['sellerInfo']);
        $this->object->update();
        lugWarning('booking status ' . $this->object->status);
        return ['status' => false];
    }

    protected function failed($error = NULL)
    {
        if ($this->responseStatusIsInvalid()) {
            $this->object->status = match ($this->rawResult['decoded']['bookingStatus'] ?? 'contact_admin') {
                'issued' => 'issued',
                'waiting_issue' => 'waiting_issue',
                'on_hold' => 'on_hold',
                'incomplete' => 'incomplete',
                'failed' => 'failed',
                default => 'contact_admin'
            };
        }
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['bookingReferenceId'] = $this->rawResult['decoded']['bookingReferenceId'] ?? '';
        $sellerInfo['supplierReference'] = $this->rawResult['decoded']['bookingSerial'] ?? '';
        $this->object->seller_info = $sellerInfo;
        $this->object->update();

        lugWarning('booking status ' . $this->object->status);
        return ['status' => false];
    }
}
