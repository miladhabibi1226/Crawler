<?php

namespace App\TravelServices\Suppliers\Local\Logics\Tour;

use App\TravelServices\Suppliers\Local\Logics\Flight\PassengersValidation as FlightPassengersValidation;
use Carbon\Carbon;

trait PassengersValidation
{
    use FlightPassengersValidation;

    protected $requestedPassengers;

    protected function setData()
    {
        if ($this->objectIsBooking) {
            $this->passengersInfo = $this->object->passenger_info;
            $this->requestedPassengers = $this->object->seller_info['passengersCount'] ?? [];
        } else {
            $this->passengersInfo = request('passengersInfo');
            ///>in this case we don't have 'create book' before passengers validation, so we don't have the passengers' count to check, we assume the user is sending only passengers number that they want (might be an issue only if some 3rd user is calling our api out)
            $this->requestedPassengers = $this->object->seller_info['passengersCount'] ?? [
                'adults' => count($this->passengersInfo['adults'] ?? []),
                'children' => count($this->passengersInfo['children'] ?? []),
                'infants' => count($this->passengersInfo['infants'] ?? [])
            ];
            $sellerInfo = $this->object->seller_info;
            $sellerInfo['passengersCount']  = $this->requestedPassengers;
            $this->object->seller_info = $sellerInfo;
            $this->object->update();
        }
        $this->passengersForm = $this->object->seller_info['passengersForm'] ?? [];
        $this->optionalItems = $this->getOptionalItems();
    }

    protected function validatePassengerTypeCount()
    {
        $aCount = count($this->passengersInfo['adults'] ?? []);
        if ($aCount == 0) {
            $this->addError($this->transMessage('adultsCount'), $this->messageKey());
            return false;
        }
        if ($aCount != $this->requestedPassengers['adults']) {
            $this->addError($this->transMessage('passengerTypeCount', ['adults', $aCount, $this->requestedPassengers['adults']]), $this->messageKey());
            return false;
        }

        $cCount = count($this->passengersInfo['children'] ?? []);
        if ($cCount != $this->requestedPassengers['children']) {
            $this->addError($this->transMessage('passengerTypeCount', ['children', $cCount, $this->requestedPassengers['children']]), $this->messageKey());
            return false;
        }

        $iCount = count($this->passengersInfo['infants'] ?? []);
        if ($iCount != $this->requestedPassengers['infants']) {
            $this->addError($this->transMessage('passengerTypeCount', ['infants', $iCount, $this->requestedPassengers['infants']]), $this->messageKey());
            return false;
        }
        return true;
    }

    protected function getLastDateOfTrip()
    {
        if (empty($this->departure)) {
            ///> temporary bc of api-out
            ///> tour and event aren't really used so
            $this->departure = empty($this->object->service_info['availabilities']['to']) ? Carbon::now() : Carbon::parse($this->object->service_info['availabilities']['to']);
        }
    }

    protected function getLeader()
    {
        $firstNameCaption = $this->getPassengerFormCaption('first_name');
        $lastNameCaption = $this->getPassengerFormCaption('last_name');
        return ($this->items['adults'][0][$firstNameCaption] ?? '-') . ' ' . ($this->items['adults'][0][$lastNameCaption] ?? '-');
    }

    protected function ageRange()
    {
        return [2, 18];
    }
}
