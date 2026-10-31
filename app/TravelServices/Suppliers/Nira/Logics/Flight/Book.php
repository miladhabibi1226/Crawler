<?php

namespace App\TravelServices\Suppliers\Nira\Logics\Flight;
use App\TravelServices\Logics\Flight\Book as FlightBook;
use Carbon\Carbon;

trait Book
{
    use FlightBook;
    protected $responseSample = 'Samples/Nira/FlightBook.json';

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs + $this->object->seller_info + $this->object->service_info  + ['passengers' => $this->object->passenger_info];
        lugInfo('Book data', [$depends]);
        $query = implode('&',$this->getFields($depends));
        $this->call = [
            'fields' => "",
            'url' => $this->cleanUrlSlash($depends['url']) . "/ws2/cgi-bin/NRSWEB.cgi/ReservJS?{$query}",
            'method' => "POST",
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded']['AirReserve'][0];

        if (empty($BookResult)) {
            $this->failBook();
            return $this->addError('empty result',['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
        }

        if(empty($BookResult['PNR'])) {
            $this->failBook();
            return $this->addError('book result pnr empty, we have error');
        }

        $this->items['bookingInfo'] = [
            'pnr' => (array) ($BookResult['PNR'] ?? null),
            'expires_at' => null
        ];

        lugInfo('Nira Flight Book extractor Items', ['items' => $this->items]);
    }

    protected function getFields($depends)
    {
        $counter = 1;
        $passengersCount = $depends['passengersCount']['adults'] + $depends['passengersCount']['children'] + $depends['passengersCount']['infants'];
        $departureDate = $depends['query_params']['legs'][0]['departure'];
        $flightClass = $depends['legs'][0]['info']['resBookDesigCode'];
        $bookInfo = [
            "Airline={$this->Airline}",
            "cbSource={$depends['legs'][0]['info']['departure']['airport']['abb']}",
            "cbTarget={$depends['legs'][0]['info']['arrival']['airport']['abb']}",
            "FlightClass={$flightClass}",
            "FlightNo={$depends['legs'][0]['info']['flight_number']}",
            "DepartureDate={$departureDate}",
            "edtContact={$depends['passengerContactPhone']}",
            "OfficeUser={$depends['officeUser']}",
            "OfficePass={$depends['officePass']}",
            "no={$passengersCount}"
        ];
        foreach($depends['passengers'] as $passengerType) {
            foreach($passengerType as $passengerInfo) {
                $age = ($a = Carbon::parse($passengerInfo['Date of birth'])->age) == 0 ? 1 : $a;
                $issuedBYCountry = $passengerInfo['CountriesInfo']['Passport Issuing Country']['abb'];
                $birthDayCountry = $passengerInfo['CountriesInfo']['Nationality']['abb'];
                $birthDay = Carbon::parse($passengerInfo['Date of birth'])->format('dMy');
                $expireDate = Carbon::parse($passengerInfo['Passport Expiry Date'])->format('dMy');
                if($depends['isIranianDomesticFlight'] && $passengerInfo['CountriesInfo']['Nationality']['abb'] == 'IR') {
                    ///> code meli: I_IssuedBYCountry_NationalId_BirthDayCountry_BirthDay_Gender_Expiredate_LastName_FirstName
                    $id = "I_{$issuedBYCountry}_{$passengerInfo['National Id']}_{$birthDayCountry}_{$birthDay}_{$passengerInfo['Gender']}_{$expireDate}_{$passengerInfo['Last_name']}_{$passengerInfo['First_name']}";
                }else {
                    ///> pasport format: P_IssuedBYCountry_NationalId_BirthDayCountry_BirthDay_Gender_Expiredate_LastName_FirstName
                    $id = "P_{$issuedBYCountry}_{$passengerInfo['Passport Number']}_{$birthDayCountry}_{$birthDay}_{$passengerInfo['Gender']}_{$expireDate}_{$passengerInfo['Last_name']}_{$passengerInfo['First_name']}";
                }
                $bookInfo["edtName{$counter}"] = "edtName{$counter}={$passengerInfo['First_name']}";
                $bookInfo["edtLast{$counter}"] = "edtLast{$counter}={$passengerInfo['Last_name']}";
                $bookInfo["edtAge{$counter}"] = "edtAge{$counter}={$age}";
                $bookInfo["edtID{$counter}"] = "edtID{$counter}={$id}";
                $counter++;
            }
        }
        return $bookInfo;
    }

    protected function haveSupplierResponseError()
    {
        $rawError = empty($this->rawResult['decoded']['AirReserve'][0]['PNR']) ? true : false;
        if ($rawError) {
            lugWSResError("Nira response contains error ({$this->seller->alias})", ['supplierError: ' => $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError ?? $rawError);
            return $this->rawResult['decoded']['AirReserve'][0]['Error'] ?? 'Error Happen';
        }
        $this->supplierConversaion();
        return false;
    }
}
