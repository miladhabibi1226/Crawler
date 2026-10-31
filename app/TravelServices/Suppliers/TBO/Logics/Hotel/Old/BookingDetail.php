<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\Old;

trait BookingDetail
{
    protected function getContent()
    {
        if ($this->fromFile) {
            $xml = new \SimpleXMLElement(file_get_contents(storage_path('Samples/TBO/BookingDetail.xml')));
            $this->rawResult['body'] = json_decode(json_encode($xml->xpath('//s:Body')), true);
            lugInfo('Reading the result from file');
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }

        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['body'] = json_decode(json_encode($xml->xpath('//s:Body')), true);
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->object->booking_info + $auth;
        $this->call = [
            'fields' => view('TBO.BookingDetail', $depends)->render(),
            'url' => $auth['url']
        ];

        return $this->action();
    }

    public function extractor()
    {
        $details = $this->rawResult['body'][0]['HotelBookingDetailResponse']['BookingDetail'] ?? $this->callException('empty details');
        $this->items = [
            'AmendmentStatus' => $details['@attributes']['AmendmentStatus'] ?? 'false',
            'VoucherStatus' => $details['@attributes']['VoucherStatus'] ?? 'true',
            'invoiceNumber' => $details['@attributes']['InvoiceNumber'] ?? '',
            'BookingDate' => $details['BookingDate'] ?? '',
        ];
    }

    protected function mapper()
    {
        $booking = $this->object->booking_info;
        $booking['invoice'] = $this->items;
        $this->object->booking_info = $booking;
        $this->object->update();
    }
}
