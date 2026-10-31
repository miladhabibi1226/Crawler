<?php

namespace App\TravelServices\Suppliers\MystiFly\Logics\Flight;

use App\TravelServices\Logics\PassengerInfoCreator;

trait TargetSearch
{
    use PassengerInfoCreator;
    ///> we are disabling targetSearch for MistyFly bc we have disabled their cache

    public function canBeDone()
    {
        if (empty($this->object->buffer->seller_info['validateResult'])) {
            $this->addError('revalidate was unsuccessful');
        }
    }

    protected function done()
    {
        return [
            'status' => true,
            'items' => [[
                'service' => $this->object->buffer->serviceInfo->data,
                'price' => $this->object->buffer->price_info,
                'sellerInfo' => $this->sellerInfo(),
            ]],
            'queryParams' => $this->queryParams
        ];
    }

    protected function sellerInfo()
    {
        ///> there is a RequiredFieldsToBook item that we get in the revalidate response. these are the passenger info items required for the book api that might be different for each airline
        ///> we'll be adding these items to our existing passengerform, which is only the defult items
        ///> we have called the revalidate api when geting the fareRules and stored the RequiredFieldsToBook items in the buffer's sellerInfo. fare Rules api is called before target search my marketplace
        $sellerInfo = $this->object->buffer->seller_info;
        $requiredPassengerInfo = $sellerInfo['validateResult']['RequiredFieldsToBook'];
        if (!empty($requiredPassengerInfo)) {
            $passengersForm = $sellerInfo['passengersForm'];
            foreach ($requiredPassengerInfo as $item) {
                switch ($item) {
                    case 'Passport':
                        $passengersForm = $this->addPassengerFormItem(['all' => ['passport_expiry', 'passport_number', 'passport_country']], $passengersForm);
                        break;
                    case 'DOB':
                        $passengersForm = $this->addPassengerFormItem(['all' => ['birthdate']], $passengersForm);
                        break;
                    case 'PassengerNationality':
                        $passengersForm = $this->addPassengerFormItem(['all' => ['nationality']], $passengersForm);
                        break;
                    case 'PostCode':
                        $passengersForm['lead'] = $this->addPassengerFormGenericItem('text', 'Post Code', $passengersForm['lead']);
                        break;
                    case 'NationalID':
                        $passengersForm = $this->addPassengerFormItem(['all' => ['nationalId']], $passengersForm);
                        break;
                }
            }
            $sellerInfo['passengersForm'] = $passengersForm;
        }
        return $sellerInfo;
    }
}
