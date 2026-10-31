<?php

namespace App\TravelServices\Logics\Flight;

use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\Airline;

trait OpenReturn
{

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path("Samples/$this->supplierName/FlightSearch.json"));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }
        //if tripType is openReturn we handle it in handelOpenReturnResult function
        if ($this->translatedQueryParams['tripType'] === 'openReturn' && $this->action === 'search') {
            if (!$this->handleOpenReturnResult()) {
                $this->addError('there is no result for openReturn');
                return false;
            }
        } else {
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            $error = $this->haveSupplierResponseError();
            if (!empty($error)) {
                $this->addError($error);
                return false;
            }
        }
    }

    protected function handleOpenReturnResult()
    {
        foreach ($this->rawResult as $date => $openReturnresult) {
            $openReturnresult['decoded'] = json_decode($openReturnresult['body'], true);
            $error = $this->haveSupplierResponseError($openReturnresult);
            if (!empty($error) || $this->checkOpenReturnEmptyResult($openReturnresult)) {
                unset($this->rawResult[$date]);
                continue;
            }
            $this->rawResult = $openReturnresult;
            $this->queryParams['legs'][1]['departure'] = $date;
            if (isset($this->queryParams['legs'][1]['departureShamsiDate'])) {
                $parts = preg_split('/[-\/]/', $this->queryParams['legs'][1]['departureShamsiDate']);
                $year = (int)$parts[0];
                if ($year >= 1400 && $year <= 1500) {
                    [$gy, $gm, $gd] = explode('-', $date);
                    [$jy, $jm, $jd] = $this->gregorianToJalali($gy, $gm, $gd);
                    $shamsiDate = sprintf('%04d-%02d-%02d', $jy, $jm, $jd);
                    $this->queryParams['legs'][1]['departureShamsiDate'] = $shamsiDate;
                } else {
                    $this->queryParams['legs'][1]['departureShamsiDate'] = $date;
                }
            }
            break;
        }
        return (!empty($this->rawResult));
    }

    protected function sendMultiCurlForOpenReturn($depends)
    {
        $openReturnDates = $this->collectOpenReturnDates($this->openReturnDateRange);
        foreach ($openReturnDates as $openReturnDate) {
            $this->call[$openReturnDate] = [
                'fields' => json_encode($this->getFields($depends, $openReturnDate)),
                'url' => $this->cleanUrlSlash($depends['url']) . $this->searchUrl(),
                'headers' => $this->getHeader(),
                'timeout' => 15,
                'apiMethod' => 'Search'
            ];
        }
        $this->multi = true;
        return $this->action();
    }

    protected function collectOpenReturnDates($openReturnDateRange)
    {
        $departureDate = Carbon::parse($this->translatedQueryParams['legs'][1]['departure']);
        $openReturnDates[0] = $this->translatedQueryParams['legs'][1]['departure'];
        for ($i = $openReturnDateRange; $i > 0; $i--) {
            $openReturnDates[] = $departureDate->copy()->subDays($i)->format('Y-m-d');
        }
        for ($i = 1; $i <= $openReturnDateRange; $i++) {
            $openReturnDates[] = $departureDate->copy()->addDays($i)->format('Y-m-d');
        }
        return $openReturnDates;
    }

    public function requestGenerator()
    {
        if ($this->translatedQueryParams['tripType'] === 'openReturn') {
            $this->LugTheResponse = 1;
        }
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->translatedQueryParams;
        if ($this->translatedQueryParams['tripType'] === 'openReturn' && $this->action === 'search') {
            return $this->sendMultiCurlForOpenReturn($depends);
        } else {
            $this->call = [
                'fields' => json_encode($this->getFields($depends)),
                'url' => $this->cleanUrlSlash($auth['url']) .  $this->searchUrl(),
                'headers' => $this->getHeader(),
                'timeout' => 15,
                'apiMethod' => 'Search',
            ];
            return $this->action();
        }
    }

    function gregorianToJalali($gy, $gm, $gd, $mod = '')
    {
        $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * ((int)($days / 12053)));
        $days %= 12053;
        $jy += 4 * ((int)($days / 1461));
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return ($mod == '') ? array($jy, $jm, $jd) : $jy . $mod . $jm . $mod . $jd;
    }

    protected function checkServiceInfoValidity($legs)
    {
        // In open return flights, we need to check whether the last airport in the outbound leg  TS2339
        // matches the first airport in the return leg (and vice versa).
        if (!$this->checkOpenReturnAirports($legs)) {
            // lugWarning('OpenReturn departure and arrival Airport in' . $this->supplierName . 'are not same', ['legs', [$legs]]);
            return false;
        }
        return true;
    }

    protected function checkOpenReturnAirports($legs)
    {
        //first leg
        $firstSegmentOfFirstLeg = reset($legs[0]['segments']);
        $lastSegmentOfFirstLeg = end($legs[0]['segments']);
        //second leg
        $lastSegmentOfSecondLeg = end($legs[1]['segments']);
        $firstSegmentOfSecondLeg = reset($legs[1]['segments']);
        if (
            $lastSegmentOfFirstLeg['arrival']['airport']['abb'] !== $firstSegmentOfSecondLeg['departure']['airport']['abb'] ||
            $firstSegmentOfFirstLeg['departure']['airport']['abb'] !== $lastSegmentOfSecondLeg['arrival']['airport']['abb']
        ) {
            return false;
        }
        return true;
    }


    protected function getSystemExceptions()
    {
        ///> openReturn flight are only available for iraqi airways airline so we'll filter the rest
        if ($this->queryParams['tripType'] == 'openReturn') {
            static $iraqiAirways;
            if (empty($iraqiAirways)) {
                $iraqiAirways = $this->airlines['IA'] ?? Airline::whereAbb('IA')->first()->toArray();
            }
            return ['airline_id' => $iraqiAirways['id'] ?? ''];
        }
        return [];
    }
}
