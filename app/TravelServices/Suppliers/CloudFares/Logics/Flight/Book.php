<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics\Flight;

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
        lugInfo('Book data', [$depends, $this->latestSession]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/b2b/v1/flight/book',
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $bookResult = $this->rawResult['decoded']['data'] ?? [];
        //check empty 
        if (empty($bookResult) || empty($bookResult['status'])) {
            lugError('empty result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            $this->addErrorToBook(trans('technical'));
            return $this->addError(trans('technical'));
        }
        //check status
        if ($bookResult['status'] === 'cancel' || $bookResult['status'] !== 'book') {
            lugError('cancel-book-status', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            $this->addErrorToBook(trans('technical'));
            return $this->addError(trans('technical'));
        }
        //check pnr 
        if (empty($bookResult['pnr'])){
            lugError('empty pnr', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            $this->addErrorToBook(trans('technical'));
            return $this->addError(trans('technical'));
        }
        $this->items['bookingInfo'] = [
            'pnr' => (array) $bookResult['booking_reference'] ?? '',
            'booking_reference' => $bookResult['pnr'],
            'expires_at' => $bookResult['latest_ticketing_time'] ?? ''
        ];
        $this->items['sellerInfo'] = [
            'pnr' => $bookResult['pnr'],
            'supplierReference' =>$bookResult['booking_reference'] ?? '',
            'lastName' => $bookResult['passengers'][0]['lastName'] ?? $this->object->passenger_info['adults'][0]['Last_name']
        ];
        lugInfo('CloudFares Price extractor Items', ['items' => $this->items]);
    }

    protected function getFields($depends)
    {
        foreach ($depends['passengers'] as $passengerType => $passengerTypeGroup) {
            foreach ($passengerTypeGroup as $passenger) {
                $passengers[] = [
                    "title" => $passenger['title'],
                    "first" => $passenger['First_name'],
                    "last" => $passenger['Last_name'],
                    "dob" =>  $passenger['Date of birth'],
                    "type" => ['adults' => "ADT", 'children' => "CHD", 'infants' => "INF"][$passengerType],
                    "gender" => ['MALE' => "M", 'FEMALE' => "F"][strtoupper($passenger['Gender'])],
                    "nationality" =>  $passenger['CountriesInfo']['Nationality']['abb'],
                    "document_type" => "passport",
                    "document_no" => $passenger['Passport Number'],
                    "document_expiry" => $passenger['Passport Expiry Date']
                ];
            }
        }
        $fields =
            [
                "trace_id" => $depends['trace_id'],
                "result_id" => $depends['price_result_id'],
                "payment_type" => "wallet",
                "type" => "book",
                "contact" => [
                    "email" => $depends['email'],
                    "phone" => $depends['phone'],
                    "first_name" => $depends['first_name'],
                    "last_name" => $depends['last_name']
                ],
                "passengers" => $passengers

            ];

        return $fields;
    }
}
