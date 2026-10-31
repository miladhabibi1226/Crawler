<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\Visa;

trait PassengersValidation
{
    protected $leader = '';

    protected function validate()
    {
        if ($this->objectIsBuffer) {
            $passengers = request('passengersInfo');
            $requestedPassengers = count(request('passengersInfo'));
        } else {
            $passengers = $this->object->passenger_info;
            $requestedPassengers = $this->object->seller_info['passengersCount'];
        }

        if (empty($passengers)) {
            $this->addError("Empty Passengers Info", "passengers");
            return;
        }
        if (empty($requestedPassengers)) {
            $this->addError("PassengersCount field is required", "passengers");
            return;
        }

        $passengersCount = count($passengers);
        if ($passengersCount <= 0) {
            return $this->addError("At least 1 passenger need to be present", "passengers");
        }

        if ($passengersCount != $requestedPassengers) {
            $this->addError("Passengers count ($passengersCount entered) should be the same as requested: $requestedPassengers.", "passengers");
        }

        foreach ($passengers as $number => $passenger) {
            if (!is_integer($number)) {
                $this->addError("Invalid Index : $number.", "passengers[$number]");
            } else {
                $num = $number + 1;
            }

            if (empty($passenger['title'])) {
                $this->addError("Title is required for passenger no.$num.", "passengers[$number][title]");
            }
            if (empty($passenger['first_name'])) {
                $this->addError("First name is required for passenger no.$num.", "passengers[$number][first_name]");
            }
            if (empty($passenger['last_name'])) {
                $this->addError("Last name is required for passenger no.$num.", "passengers[$number][last_name]");
            }
            if (empty($passenger['birthdate'])) {
                $this->addError("birthdate is required for passenger no.$num.", "passengers[$number][birthdate]");
            } else {
                preg_match("/\d{4}+-+\d{2}+-+\d{2}/", $passenger['birthdate'] ?? null, $bd);
                if (empty($bd[0])) {
                    $this->addError("Invalid birthdate for passenger no.$num. Acceptable format is: yyy-mm-dd", "passengers[$number][birthdate]");
                }
            }
            if (empty($passenger['nationality'])) {
                $this->addError("Nationality is required for passenger no.$num.", "passengers[$number][nationality]");
            } else {
                $nationality = $this->getCountry($passenger['nationality']);
                if (empty($nationality)) {
                    $this->addError("Invailid nationality for passenger no.$num.", "passengers[$number][nationality]");
                } else {
                    $passengers[$number]['nationalityInfo'] = $nationality;
                }
            }
            if (empty($passenger['passport_number'])) {
                $this->addError("Passport number is required for passenger no.$num.", "passengers[$number][passport_number]");
            }
            if (empty($passenger['passport_country'])) {
                $this->addError("Passport issuing country is required for passenger no.$num.", "passengers[$number][passport_country]");
            } else {
                $passportCountry = $this->getCountry($passenger['passport_country']);
                if (empty($passportCountry)) {
                    $this->addError("Invailid passport country for passenger no.$num.", "passengers[$number][passport_country]");
                } else {
                    $passengers[$number]['passportCountryInfo'] = $passportCountry;
                }
            }
            if (empty($passenger['passport_expiry'])) {
                $this->addError("Date of expire is required for passenger no.$num.", "passengers[$number][passport_expiry]");
            } else {
                preg_match("/\d{4}+-+\d{2}+-+\d{2}/", $passenger['passport_expiry'] ?? null, $pe);
                if (empty($pe[0])) {
                    $this->addError("Invalid date of expire for passenger no.$num. Acceptable format is: yyy-mm-dd", "passengers[$number][passport_expiry]");
                }
            }
            if (empty($passenger['photo1'])) {
                $this->addError("Photo1 is required for passenger no.$num.", "passengers[$number][photo1]");
            }
            if ($number == 0) {
                if (empty($passenger['email'])) {
                    $this->addError("Email is required for the lead passenger.", "passengers[$number][email]");
                }
                if (empty($passenger['phone'])) {
                    $this->addError("Phone number is required for the lead passenger.", "passengers[$number][phone]");
                }
            }
        }

        $this->items = array_values($passengers);

        if ($this->objectIsBooking) {
            $this->object->passenger_info = $this->items;
            $this->object->update();
        }
        $this->leader = ($this->items[0]['title'] ?? '') . '. ' . ($this->items[0]['first_name'] ?? '') . ' ' . ($this->items[0]['last_name'] ?? '');
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
