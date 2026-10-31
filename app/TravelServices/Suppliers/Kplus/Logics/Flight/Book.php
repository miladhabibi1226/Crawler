<?php

namespace App\TravelServices\Suppliers\Kplus\Logics\Flight;

use Carbon\Carbon;
use App\TravelServices\Logics\Flight\Book as FlightBook;

trait Book
{
    use FlightBook;
    public function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs + $this->object->seller_info + ['passengers' => $this->object->passenger_info];
        lugInfo('Book data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'method' => 'POST',
            'url' =>  "{$depends['url']}/Air.svc/Rest/Json/Book",
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded']['Result']['Booking'] ?? [];
        if (empty($BookResult)) {
            lugError('empty result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            return $this->addError('empty result');
        }
        if (!$this->checkSupplierBookingStatus($BookResult)) {
            lugError('invalid status', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            return $this->addError('empty result');
        }
        $this->items['bookingInfo'] = [
            'pnr' => $this->collectPnrs($BookResult) ?? [],
            'expires_at' => $BookResult['ExpirationDate'] ?? ''
        ];
        $this->items['sellerInfo'] = [
            'supplierReference' => $BookResult['SystemPnr'] ?? '',
            'BookingStatusType' => $BookResult['BookingStatusType'] ?? ''
        ];
        if ($this->bookingType == 'bookAndIssue') {
            $this->booKandIssueItems($BookResult); ///> in BookAndIssue trait
        }
        lugInfo('Kplus Flight Book/AndIssue extractor Items', ['items' => $this->items]);
    }

    protected function collectPnrs($BookResult)
    {
        foreach ($BookResult['FlightBookingList'] ?? [] as $FlightBooking) {
            $pnrs[] = $FlightBooking['Pnr'];
        }
        return $pnrs ?? [];
    }

    protected function checkSupplierBookingStatus($BookResult)
    {
        return ($BookResult['BookingStatusType'] ?? '') == '1';
    }

    protected function failed($error = null)
    {
        if (empty($this->object->seller_info['supplierReference'])) {
            $this->object->seller_info = array_merge($this->object->seller_info ?? [], ['supplierReference' => $this->rawResult['decoded']['Result']['Booking']['SystemPnr'] ?? '']);
            $this->object->update();
        }
        return ['status' => false];
    }

    protected function getFields($depends)
    {
        foreach ($depends['passengers'] as $passengerType => $passengerTypeGroup) {
            foreach ($passengerTypeGroup as $passengers) {
                $paxInfo[] = [
                    "FlightPaxType" => ['adults' => 0, 'children' => 1, 'infants' => 2][$passengerType],
                    "Pax" => [
                        "DateOfBirth" => Carbon::create($passengers['Date of birth'])->format('d.m.Y'),
                        "Email" => '',
                        "FirstName" => $passengers['First_name'],
                        "GenderType" => strtolower($passengers['Gender']) == 'male' ? 0 : 1,
                        "LastName" => $passengers['Last_name'],
                        "MobilePhone" => '',
                        "NationalityCode" => $passengers['CountriesInfo']['Nationality']['abb'],
                        "PassportNumber" => $passengers['Passport Number'],
                        "PassportValidityDate" => $passengers['Passport Expiry Date']
                    ]
                ];
            }
        }
        $fields = [
            "request" => [
                "TokenCode" => $this->latestSession,
                "ResultKeys" => $depends['ValidateKeys'],
                "PaymentInfo" => ["PaymentType" => $this->bookingType == 'bookOnly' ? "6" : "2"],
                "ContactInfo" => [
                    "Email" => $depends['passengerContactEmail'],
                    "FirstName" => $depends['passengers']['adults'][0]['First_name'],
                    "GenderType" => strtolower($depends['passengers']['adults'][0]['Gender']) == 'male' ? 0 : 1,
                    "LastName" => $depends['passengers']['adults'][0]['Last_name'],
                    "Phone" =>  $depends['passengerContactPhone']
                ],
                "InvoiceInfo" => [
                    "CompanyName" => $depends['companyName'],
                    "Address" => $depends['companyName'],
                    "InvoiceInfoTitle" => "InvoiceInfoTitle",
                    "InvoiceInfoType" => "1"
                ],
                "PaxInfo" => ["FlightPaxes" => $paxInfo]
            ]
        ];
        return $fields;
    }
}
