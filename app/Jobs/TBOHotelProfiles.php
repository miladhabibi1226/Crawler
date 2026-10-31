<?php

namespace App\Jobs;

use App\Jobs\Job;
use MatinUtils\NodeTracker\Models\Seller;
use App\Models\CommonHotelProfile;
use App\Models\Hotel;
use MatinUtils\NodeTracker\Models\HotelSupplier;


class TBOHotelProfiles extends Job
{

    protected  $call, $rawResult, $queryParams, $hotels, $seller, $hotelCodes, $dataReference, $callback, $hotelSuppliers, $correct;

    public function __construct($queryParams, array $hotels, $hotelCodes, $seller, $dataReference, callable $callback = null)
    {
        // lugWarning(__METHOD__, [$hotels, $sellerDataReference, $isDomestic, $sellerId]);
        $this->queryParams = $queryParams;
        $this->hotels = $hotels;
        $this->hotelCodes = $hotelCodes;
        $this->seller = $seller;
        $this->dataReference = $dataReference;
        $this->callback = $callback;
    }

    public function operate()
    {
        $this->sendRequest();
        $this->saveToDataBase();
    }

    protected function sendRequest()
    {
        $this->requestGenerator();
        $curls = [];
        $multiCurl = curl_multi_init();
        foreach ($this->call as $key => $call) {
            $options = [
                CURLOPT_URL => $call['url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 1,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $call['method'] ?? "POST",
                CURLOPT_POSTFIELDS => $call['fields'],
                CURLOPT_HTTPHEADER => array_merge(($call['headers'] ?? []), ['Content-Type:application/json']),
                CURLOPT_TIMEOUT => $call['timeout'] ?? 20,
                CURLOPT_HEADER => true,
            ];
            if (!empty($call['cookies'])) {
                $options[CURLOPT_COOKIE] = $call['cookies'];
            }
            if (!empty(env('CURL_INTERFACE'))) {
                $options[CURLOPT_INTERFACE] = env('CURL_INTERFACE');
            }

            $curls[$key] = curl_init();
            curl_setopt_array($curls[$key], $options);
            curl_multi_add_handle($multiCurl, $curls[$key]);
        }
        $running = null;
        do {
            curl_multi_exec($multiCurl, $running);
        } while ($running);
        curl_multi_close($multiCurl);
        $this->rawResult = [];
        foreach ($curls as $key =>  $curl) {
            $headerSize = curl_getinfo($curls[$key], CURLINFO_HEADER_SIZE);
            $responseHeaders = substr(curl_multi_getcontent($curls[$key]), 0, $headerSize);
            preg_match('/(?<=set-cookie:).*/i', $responseHeaders, $responseCookies);
            $responseCookies = array_map('trim', $responseCookies, array_fill(0, count($responseCookies), "\r"));
            $this->rawResult[$key] = [
                'http_code' => $httpCodes[] = curl_getinfo($curls[$key], CURLINFO_HTTP_CODE),
                'cookie' => $responseCookies,
                'body' => substr(curl_multi_getcontent($curls[$key]), $headerSize)
            ];
        }
    }

    protected function saveToDataBase()
    {
        $data = [];
        $this->decodeRawResult();
        $this->checkError();
        $data = $this->collectData();
        if (!empty($this->hotelSuppliers)) {
            try {
                HotelSupplier::upsert($this->hotelSuppliers, ['data_reference', 'supplier_key'], ['hotel_id']);
            } catch (\Exception $e) {
                lugWarning('problem in upserting in HotelSupplier for TBO_New_Version ', [$e->getMessage()]);
            }
        }
        if (!empty($data)) {
            try {
                $chunks = array_chunk($data, 100);
                foreach ($chunks as $chunk) {
                    CommonHotelProfile::insertOrIgnore($chunk);
                }
            } catch (\Exception $e) {
                lugWarning('problem in upserting in CommonHotelProfile for TBO_New_Version ', [$e->getMessage()]);
            }
        }
    }

    protected function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $username = $auth['username'];
        $password = $auth['password'];
        $chunks = collect($this->hotels)->chunk(100);
        foreach ($chunks as $chunk) {
            $hotelcodes = $chunk->pluck('supplierKey')->implode(',');
            $this->call[] = [
                'fields' => json_encode(["Hotelcodes" => $hotelcodes, "Language" => "EN"]),
                'url' => $auth['url'] . '/HotelDetails',
                'headers' => [
                    'Authorization: Basic ' . base64_encode("$username:$password")
                ],
                'timeout' => 50,
                'apiMethod' => 'HotelDetails',
            ];
        }
    }

    protected function collectData()
    {
        foreach ($this->rawResult as $key => $rawResult) {
            //check empty 
            if ((empty($rawResult['decoded'])) || empty($rawResult['decoded']['HotelDetails'])) {
                continue;
            }
            $hotelDetails = $rawResult['decoded']['HotelDetails'];
            foreach ($hotelDetails as $hotelDetail) {
                $map = explode('|', $hotelDetail['Map']);
                $imageLinks = $hotelDetail['Images'] ?? '';
                $data[] = [
                    'hotel_id' => $this->getHotelId($hotelDetail['HotelCode'], $hotelDetail['HotelName'], $key) ?? '',
                    'data_reference' => $this->dataReference ,
                    'code' => $hotelDetail['HotelCode'] ?? '',
                    'title' => $hotelDetail['HotelName'] ?? '',
                    "facilities" => json_encode($hotelDetail['HotelFacilities'] ?? []),
                    "attractions" => json_encode($hotelDetail['Attractions'] ?? []),
                    'phone' => $hotelDetail['PhoneNumber'] ?? '',
                    'thumbnail' => $imageLinks[0] ?? null,
                    'description' => json_encode($hotelDetail['Description'] ?? []),
                    'images' =>  json_encode($hotelDetail['Images'] ?? []),
                    'address' => json_encode([
                        'lat' => $map[0] ?? null,
                        'long' => $map[1] ?? null,
                        'address' => $hotelDetail['Address'] ?? null,
                        'postal_code' => $hotelDetail['PinCode'] ?? null,
                    ]),
                    'rating' => $hotelDetail['HotelRating'] ?? 0,
                    'extra_data' => json_encode([  ////>  Extra data will be updated in GRS AvailableRooms
                        'CheckIn' => $hotelDetail['CheckInTime'] ?? null,
                        'CheckOut' => $hotelDetail['CheckOutTime'] ?? null,
                        'CountryCode' => $hotelDetail['CountryCode'] ?? [],
                        'CityName' => $hotelDetail['Istanbul'] ?? [],
                    ]),
                ];
            }
        }
        return $data;
    }

    protected function  decodeRawResult()
    {
        foreach ($this->rawResult as $index => $rawResult) {
            $this->rawResult[$index]['decoded'] = json_decode($rawResult['body'], true);
        }
    }

    protected function checkError()
    {
        foreach ($this->rawResult as $index => $rawResult) {
            if (empty($rawResult['decoded'])) {
                unset($this->rawResult[$index]);
            } else {
                $error = $this->haveSupplierResponseError($rawResult);
                if (!empty($error)) {
                    unset($this->rawResult[$index]);
                }
            }
        }
    }

    protected function getHotelId($hotelCode, $hotelTitle, $key)
    {
        $hotel = Hotel::firstOrCreate( ///> need optimization
            [
                'city_id' => $this->queryParams['destination_city']['id'],
                'title' => $hotelTitle
            ],
        );
        $this->hotelSuppliers[] = ['hotel_id' => $hotel->id, 'data_reference' => $this->dataReference, 'supplier_key' => $hotelCode];
        return $hotel->id;
    }

    protected function haveSupplierResponseError($rawResult)
    {
        $responseStatus = $rawResult['decoded']['Status'] ?? null;
        if ($responseStatus['Code'] != 200) {
            lugWSResError("TBO New Version response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $rawResult, 'supplier response: ' => $rawResult['body']]);
            return $rawError["Description"] ?? $responseStatus;
        }
        if ($rawResult['http_code'] != 200) {
            lugWSResError("TBO New Version response is invalid ({$this->seller->alias})", ['rawResult' => $rawResult]);
            return 'TBO New Version response is invalid';
        }

        return false;
    }
}
