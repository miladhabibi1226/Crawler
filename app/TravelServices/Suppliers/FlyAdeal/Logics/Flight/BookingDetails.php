<?php

namespace App\TravelServices\Suppliers\FlyAdeal\Logics\Flight;

use App\TravelServices\Logics\Flight\Book as FlightBook;

use Carbon\Carbon;


trait BookingDetails
{

    protected function canBeDone()
    {
        $this->authorizeRequest($this->object->seller_info['bookingSession'] ?? '');
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult = ['http_code' => 200, 'body' => file_get_contents(storage_path('Samples/FlyAdeal/BookingDetails.json'))];
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs;
        lugInfo('BookingDetails data', [$depends]);
        $this->call = [
            'fields' => '',
            'method' => 'GET',
            'url' =>  "{$depends['url']}/api/nsk/v1/booking",
            'apiMethod' => 'BookingDetails'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $Result = $this->rawResult['decoded']['data'] ?? [];
        $this->objectStatus = 'issued';
        if (empty($Result)) {
            lugError('empty booking', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->objectStatus = 'contact_admin';
            return $this->addError('empty Result');
        }
        $pnr = [$Result['recordLocator'] ?? null];
        if (empty($pnr)) {
            $this->addError('No pnr');
            lugWarning('No pnr', [$this->rawResult]);
            $this->failBook();
        }

        $this->items['bookingInfo'] = [
            'issued_at' =>  Carbon::now(),
            'tickets' => $this->flightTicketInfo($this->getTickets($Result['recordLocator'] ?? null)),
            'pnr' => array_unique($pnr ?? [])
        ];
        $this->items['pnr'] = implode('.', array_unique($pnr)) . ':' . implode('.', array_unique($pnr));
        if (strtolower($Result['info']['status']) != '2') { ///> 0 = Default. 1 = Hold. 2 = Confirmed. 3 = Closed. 4 = HoldCanceled. 5 = PendingArchive. 6 = Archived. 7 = ArchivedPrivate. 777 = ProcessedForArchive
            lugError('Result', ['Result' => $Result]);
            $this->objectStatus = 'incomplete';
            return $this->addError('invalid Result');
        }
        if (strtolower($Result['info']['paidStatus']) != '1') { ///> 0 = UnderPaid. 1 = PaidInFull. 2 = OverPaid
            lugError('Result', ['Result' => $Result]);
            $this->objectStatus = 'incomplete';
            return $this->addError('invalid Result');
        }
        $this->items['info'] = $Result['info'];
        lugInfo('FlyAdeal BookingDetails extractor Items', ['Items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info ?? [], $this->items['bookingInfo']);
        $this->object->pnr =  $this->items['pnr'];
        $this->object->booked_at = $this->items['bookingInfo']['issued_at'];
        $this->object->status = $this->objectStatus;
        $this->object->update();
        if ($this->objectStatus == 'issued') {
            $this->itemIssued($this->object->booking_info['pnr']);
        }
    }

    public function done()
    {
        if ($this->objectStatus == 'issued') {
            $this->sellerTransaction();
        }
        $this->callSignOut($this->object->seller_info['bookingSession']);
        return ['status' => true];
    }

    protected function getTickets($pnr)
    {
        ///> FlyAdeal does not have tickets so we'll put pnr for tickets
        $passengers = [];
        foreach ($this->object->passenger_info as $passengerType => $typePassengers) {
            foreach ($typePassengers as $passenger) {
                $passengers[strtoupper(str_replace(' ', '', $passenger['Last_name'] . $passenger['First_name']))] = $pnr;
            }
        }
        return $passengers;
    }
}
