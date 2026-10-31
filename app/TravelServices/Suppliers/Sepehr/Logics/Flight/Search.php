<?php

namespace App\TravelServices\Suppliers\Sepehr\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use MatinUtils\NodeTracker\Models\Country;
trait Search
{
    use FlightSearch;
    protected $currentClass, $passengersCount, $translatedQueryParams;

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path($this->getSearchSampleAddress()));
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            $this->latestSession = 'fromFile';
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Request generator unsuccessful");
            return false;
        }

        ///> we used multiCurls so
        foreach ($this->rawResult as $index => $rawResult) {
            $this->rawResult[$index]['decoded'] = json_decode($rawResult['body'], true);
        }
        ///> no need to check errors bc we'll skip the responses that have no results
    }

    protected function prepareCalls($auth)
    {
        $depends = $this->translatedQueryParams + $auth;
        // lugInfo("Sepehr Search Data ({$this->seller->alias})", [$depends]);
        $this->call[] = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($auth['url']) . $this->getSearchUrl(),
            'timeout' => 20,
            'apiMethod' => 'Search'
        ];
    }

    protected function extractSupplierCodes()
    {
        foreach ($this->rawResult as $rawResult) {
            preg_match_all('/"Code":"(\w+)"/', $rawResult['body'], $ap);
            preg_match_all('/"Airline":"(\w+)"/', $rawResult['body'], $al);
            preg_match_all('/"Aircraft":"(\w+)"/', $rawResult['body'], $eq);
            $airportCodes = array_merge($airportCodes ?? [], $ap[1] ?? []);
            $airlineCodes = array_merge($airlineCodes ?? [], $al[1] ?? []);
            $equipments = array_merge($equipments ?? [], $eq[1] ?? []);
        }
        $this->airports = $this->loadAirports(array_unique($airportCodes));
        $this->airlines = $this->loadAirLines(array_unique($airlineCodes));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$this->rawResult' => $this->rawResult, 'airlines' => array_unique($airlineCodes), 'airports' =>  array_unique($airportCodes), 'airplanes' => array_unique($equipments), '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function isAvailable($code)
    {
        $airportCodes = [
            "THR", "IKA", "MHD", "KIH", "TEQ", "SAW", "IST", "SYZ", "SAW", "AWZ", "IFN", "NJF", "TEQ", "BND", "ABD", "TBZ", "PGU", "GSM", "TBS", "ZBR",
            "ZAH", "IAQ", "BUZ", "BGW", "RAS", "KER", "KSH", "IAQ", "AZD", "DNZ", "ISE", "AYT", "DNZ", "DEF", "DWC", "DXB", "ISE", "GBT", "IIL", "OMH",
            "ADU", "DWC", "XBJ", "HDM", "GYD", "ADB", "ESB", "MCT", "LHE", "RZR", "NSH", "LRR", "EVN", "KUL", "MRX", "SDG", "AFZ", "KHD", "DMK", "BKK",
            "PYK", "LFM", "ACZ", "VAN", "SHJ", "SVO", "VKO", "DME", "ZIA", "PVG", "EBL", "ISU", "GCH", "LHR", "LGW", "LON", "AKW", "SYJ", "BJB", "JWN",
            "KBL", "LON", "LHR", "CQD", "JAR", "ATH", "KWI", "BXR", "KKS", "BEY", "DMK", "FRA", "IMQ", "PAR", "ORY", "CDG", "AJK", "CDG", "ORY", "YES",
            "CAN", "RUD", "MIL", "MXP", "BGY", "BDH", "ZIA", "SVO", "DME", "XNH", "BOM", "DAM", "DOH", "BSR", "IHR", "MUC", "DEL", "KHY", "MIL", "PFQ",
            "BCN", "RJN", "BER", "TXL", "DUS", "BGY", "JYR", "ARN", "TCX", "AMS", "HAM", "KHI", "ECN", "FCO", "ASF", "PEK", "BRU", "BUS", "DYU", "ACP",
            "GOT", "ALA", "AUH", "KLM", "BEG", "ADA", "HEA", "KYA", "BJV", "LED", "AMM", "SCO", "VAR", "KZN", "IEV", "KBP", "HKT", "IEV", "VIE", "DMM",
            "CGN", "MZR", "LCA", "ISB", "TAS", "KDH", "DLM", "BAH", "MSQ", "CPH", "NBO", "JSK", "TXL", "OSL", "HKG", "BER", "VKO", "MXP", "JAI", "KBP",
            "TSE", "SXI", "KHK", "LGW", "PAR", "ASB", "THR", "IKA", "DXB", "IST", "BKK", "AYT", "BUZ", "AEU", "LVP", "SZX", "SIN", "YYZ", "YMQ", "BNX",
            "SLL", "YKO", "GZP", "QMJ", "SNX", "OHS", "TZX", "FRU", "CMB", "SRY",
        ];
        return in_array($code, $airportCodes);
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'nationality', 'passport_number', 'passport_expiry', 'passport_country']
        ];
        $data = $this->addPassengerFormItem($items);
        if ($this->flightIsInIran()) {
            $data = $this->addPassengerFormItem(['all' => ['nationalId']], $data);
        }
        return $data;
    }

    protected function flightIsInIran()
    {
        $iran = Country::where('en', 'Iran')->first()->id;
        return $this->queryParams['legs'][0]['origin']['city']['country_id'] == $iran && $this->queryParams['legs'][0]['destination']['city']['country_id'] == $iran; ///> sepehr flights only have one leg so
    }
}
