<?php

namespace App\TravelServices\Logics;

/**
 * These methods are used to generate passengerInfo Items
 * All services except local suppliers use it in search (and targetSearch) action. and mystiFly's targetSearch that doesn't use the common targetSearch
 * For local suppliers the passengerForms are pre-set by the admins
 */
trait PassengerInfoCreator
{
    /**
     * Adds passenger info items with details
     *
     *
     * @param  array,     $supplierRequirements         The items that the supplier needs
     * @param  array|null,     $data         The existing passengersForm data, that mostly empty
     *
     * @return array    An array of all the requested passenger for items and their details
     */
    protected function addPassengerFormItem(array $supplierRequirements, array $data = [])
    {
        foreach ($supplierRequirements as $type => $passengerInfoItems) {
            foreach ($passengerInfoItems as $item) {
                $data[$type][$item] = [$this->passengerFormItems($item)];
            }
        }
        return $data;
    }

    /**
     * List of all th eavailable items
     *
     * @param  string,         $type         The item's type, like 'phone', 'email', etc
     * @param  array|null,     $item         The existing passengersForm data, that mostly empty
     *
     * @return array
     */
    protected function passengerFormItems(string $type, $item = null)
    {
        list($defaultCity, $defaultCountry) = $this->initializeDefaultCity();

        $formItems = [
            'phone' => [
                'type' => 'phone',
                'caption' => 'Phone',
                'description' => '',
            ],
            'email' => [
                'type' => 'email',
                'caption' => 'Email',
                'description' => '',
            ],
            'text_area' => [
                'type' => 'textArea',
                'caption' => 'Note',
                'description' => '',
                'optional' => true
            ],
            'gender' => [
                'type' => 'select',
                'caption' => 'Gender',
                'items' => [
                    'Male' => 'Male',
                    'Female' => 'Female'
                ],
                'description' => '',
            ],
            'first_name' => [
                'type' => 'text',
                'caption' => 'First_name',
                'description' => '',
            ],
            'last_name' => [
                'type' => 'text',
                'caption' => 'Last_name',
                'description' => '',
            ],
            'birthdate' => [
                'type' => 'birthdate',
                'caption' => 'Date of birth',
                'description' => '',
            ],
            'passport_expiry' => [
                'type' => 'expirydate',
                'caption' => 'Passport Expiry Date',
                'description' => '',
            ],
            'passport_number' => [
                'type' => 'text',
                'caption' => 'Passport Number',
                'description' => '',
            ],
            'passport_country' => [
                'type' => 'CountryCode',
                'caption' => 'Passport Issuing Country',
                'description' => '',
                'default' => $defaultCountry ?? []
            ],
            'nationality' => [
                'type' => 'CountryCode',
                'caption' => 'Nationality',
                'description' => '',
                'default' => $defaultCountry ?? []
            ],
            'nationalId' => [
                'type' => 'text',
                'caption' => 'National Id',
                'description' => '',
            ],
            'city' => [
                'type' => 'cityCode',
                'caption' => 'City',
                'description' => '',
                'default' => $defaultCity ?? []
            ],
            'domestic_first_name' => [
                'type' => 'text',
                'caption' => 'Domestic first name',
                'description' => '',
            ],
            'domestic_last_name' => [
                'type' => 'text',
                'caption' => 'Domestic last name',
                'description' => '',
            ],
            'age' => [
                'type' => 'number',
                'caption' => 'Age',
                'description' => '',
            ]
        ];
        if (empty($item)) {
            return $formItems[$type];
        }
        return $formItems[$type][$item] ?? null;
    }

    /**
     * Add generic items like text, date, etc to the passengerForm with requested caption
     *
     * @param  string,         $type         The item's type, like 'text', 'date', etc
     * @param  string,         $caption      The item's caption
     * @param  array|null,     $data         The existing passengersForm data, that mostly empty
     * @param  bool|null,      $optional     Determines if the item needs to be put as optional or not
     *
     * @return array
     */
    protected function addPassengerFormGenericItem(string $type, string $caption, array $data = [], $optional = false)
    {
        $formItems = [
            'text' => [
                'type' => 'text',
                'caption' => 'Text',
                'description' => '',
                'optional' => $optional
            ],
            'date' => [
                'type' => 'date',
                'caption' => 'Date',
                'description' => '',
                'optional' => $optional
            ],
        ];
        $formItem = $formItems[$type];
        $formItem['caption'] = $caption;
        $data[$type][] = $formItem;
        return $data;
    }

    /**
     * Finds the defult city and country for each node
     *
     * @return array
     */
    protected function initializeDefaultCity()
    {
        static $defaultCity, $defaultCountry = [];
        if (empty($defaultCity[node()->id]) && !empty(node()->city)) {
            $defaultCity[node()->id] = ['id' => node()->city->id, 'title' => node()->city->title];
            $defaultCountry[node()->id] = ['id' => node()->city->country->id, 'abb' => node()->city->country->abb, 'title' => node()->city->country->title];
        }
        return [$defaultCity[node()->id] ?? [], $defaultCountry[node()->id] ?? []];
    }
}
