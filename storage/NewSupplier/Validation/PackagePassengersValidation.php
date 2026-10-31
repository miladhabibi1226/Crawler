<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\DummyTravelService;

trait PassengersValidation
{
    protected $leader = '';

    protected function validate()
    {
        if ($this->objectIsBooking) {
            $passengers = $this->object->passenger_info;
        } else {
            $passengers = request('passengersInfo');
        }

        $requestedPassengers = $this->object->seller_info['passengersCount'] ?? [];

        if (empty($passengers)) {
            $this->addError("Empty Passengers Info", "passengers");
        }

        if (count($passengers['adults'] ?? []) <= 0) {
            $this->addError("At Least one adults must be present", "passengers[adults]");
        }

        $aCount = count($passengers['adults'] ?? []);
        if ($aCount != $requestedPassengers['adults']) {
            $this->addError("Number of adults ($aCount entered) should be the same as the requested number: " . $requestedPassengers['adults'] ?? 0, "passengers[adults]");
        }

        $cCount = count($passengers['children'] ?? []);
        if ($cCount != $requestedPassengers['children']) {
            $this->addError("Number of children ($cCount entered) should be the same as the requested number: " . $requestedPassengers['children'] ?? 0, "passengers[children]");
        }

        $iCount = count($passengers['infants'] ?? []);
        if ($iCount != $requestedPassengers['infants']) {
            $this->addError("Number of infants ($iCount entered) should be the same as the requested number: " . $requestedPassengers['infants'] ?? 0, "passengers[infants]");
        }

        $types = ['adults' => 'adult', 'children' => 'child', 'infants' => 'infant'];
        foreach ($passengers as $type => $typePassengers) {
            if (!in_array($type, ['adults', 'children', 'infants'])) {
                continue;
            }
            foreach ($typePassengers as $key => $passenger) {
                if (!is_integer($key)) {
                    $this->addError("Invalid Index : $key.", "passengers[$type][$key]");
                } else {
                    $num = $key + 1;
                }
                if (empty($passenger['title'])) {
                    $this->addError("No title for $types[$type] No.$num.", "passengers[$type][$key][title]");
                }
                if (empty($passenger['first_name'])) {
                    $this->addError("No first name for $types[$type] No.$num.", "passengers[$type][$key][first_name]");
                }
                if (empty($passenger['last_name'])) {
                    $this->addError("No last name for $types[$type] No.$num.", "passengers[$type][$key][last_name]");
                }
                if (empty($passenger['age'])) {
                    $this->addError("No age for $types[$type] No.$num.", "passengers[$type][$key][age]");
                } else {
                    if (!is_numeric($passenger['age'])) {
                        $this->addError("Invalid age for $types[$type] No.$num.", "passengers[$type][$key][age]");
                    }
                }
                if ($type == 'adults') {
                    if (($passenger['age'] ?? 0) <= 18) {
                        $this->addError("Invalid age for $types[$type] No.$num. Acceptable adult age is greater than 18.", "passengers[$type][$key][age]");
                    }
                }
                if ($type == 'children') {
                    if (($passenger['age'] ?? 0) <= 2 || ($passenger['age'] ?? 0) >= 18) {
                        $this->addError("Invalid age for $types[$type] No.$num. Acceptable child age between 2 and 18.", "passengers[$type][$key][age]");
                    }
                }
                if ($type == 'infants') {
                    if (($passenger['age'] ?? 0) >= 2) {
                        $this->addError("Invalid age for $types[$type] No.$num. Acceptable infant age is less than 2.", "passengers[$type][$key][age]");
                    }
                }
            }
        }

        ///> to avoid returning values other than 'adults' 'children and 'infants' we set them directly into $this->items
        if (!empty($passengers['adults'])) {
            $this->items['adults'] = array_values($passengers['adults']);
        }
        if (!empty($passengers['children'])) {
            $this->items['children'] = array_values($passengers['children']);
        }
        if (!empty($passengers['infants'])) {
            $this->items['infants'] = array_values($passengers['infants']);
        }

        if ($this->objectIsBooking) {
            $this->object->passenger_info = $this->items;
            $this->object->update();
        }
        $this->leader = ($this->items['adults'][0]['title'] ?? '-') . '. ' . ($this->items['adults'][0]['first_name'] ?? '-') . ' ' . ($this->items['adults'][0]['last_name'] ?? '-');

        lugInfo('DummyName DummyTravelService passengers validation items', ['passengersInfo' => $this->items, 'errors' => $this->errors]);
    }

    protected function done()
    {
        if (!$this->objectIsBooking) {
            return ['status' => true, 'leader' => $this->leader, 'passengersInfo' => $this->items];
        }

        $this->object->leader = $this->leader;
        $this->object->update();
        return ['status' => true];
    }

    protected function failed($error = null)
    {
        return ['status' => false, 'errors' => $this->errors];
    }
}
