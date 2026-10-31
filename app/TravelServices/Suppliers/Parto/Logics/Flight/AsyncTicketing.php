<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Flight;


trait AsyncTicketing
{
    use BookingData;

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['decoded'] = json_decode(file_get_contents(storage_path('Samples/Parto/FlightBookingData.json')), true);
            return true;
        }
        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            $this->object->status = 'incomplete';
            $this->object->update();
            return false;
        }

        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            $this->object->status = 'incomplete';
            $this->object->update();
            return false;
        }
    }
}
