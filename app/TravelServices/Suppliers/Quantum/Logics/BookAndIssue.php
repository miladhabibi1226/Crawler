<?php

namespace App\TravelServices\Suppliers\Quantum\Logics;

use Carbon\Carbon;

trait BookAndIssue
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
        $depends = $this->getFields();
        if (empty($depends['requestType'])) {
            $depends['requestType'] = env('API_INTERNAL', true) ? 'internal' : 'internalWithData';
        }
        lugInfo('Request Data', [$depends]);
        $this->call = [
            'api' => 'v0/bookAndIssue',
            'host' => $auth['url'],
            'variables' => [$this->travelService],
            'fields' => $depends,
            'apiMethod' => 'Book And Issue'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        if ($this->responseStatusIsInvalid()) {
            $this->addError('invalis status recieved');
            lugWarning("invalis status recieved  from api-out. booking status will be 'contact_admin");
        }
        $this->items =  $this->rawResult['decoded'];
        lugInfo("Quantum $this->action extractor items", ['Items' => $this->items]);
    }

    protected function mapper()
    {
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['bookingReferenceId'] = $this->items['bookingReferenceId'] ?? '';
        $sellerInfo['supplierReference'] = $this->items['bookingSerial'] ?? '';
        $this->object->seller_info = $sellerInfo;
        $this->object->booking_info = array_merge($this->object->booking_info, $this->items['bookingInfo']);
        $this->object->status = $this->items['bookingStatus'] ?? 'error';
        $this->object->booked_at = Carbon::now();
        $this->object->unlock_on = null;
        $this->object->async_action = $this->needsSchedule;
        $this->object->pnr = $this->items['pnr'] ??  implode(',', (array) ($this->items['bookingInfo']['pnr'] ?? []));
        $this->object->update();
        if ($this->object->status == 'issued') {
            $this->itemIssued($this->items['bookingInfo']['pnr'] ?? []);
        }
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

    protected function done()
    {
        if (in_array($this->object->status, ['issued'])) {
            $this->sellerTransaction();
        }
        return ['status' => true];
    }
}
