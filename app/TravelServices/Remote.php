<?php

namespace App\TravelServices;

use Carbon\Carbon;
use DOMDocument;
use MatinUtils\NodeTracker\Models\Booking;

abstract class Remote extends BaseSupplier
{
    protected $multi = false;
    protected $supplierSession, $latestSession;
    protected $supplierConvo;
    protected $supplierConnection;
    public $LugTheRequest = true, $LugTheResponse = true;

    public function action()
    {
        if ($this->multi) {
            return $this->multi();
        } else {
            return $this->single();
        }
    }

    protected function multi()
    {
        $curls = [];
        $multiCurl = curl_multi_init();
        $lugDataRequest = [];
        if ($this->LugTheRequest) {
            $lugDataRequest = ['fields' => $this->call ?? null, 'defaultHeader' => $this->defaultHeader];
        }
        lugInfo('Supplier Request - ' . $this->lugInfo['class'] . ' - ' . $this->seller->alias . ' - Multiple Curl', $lugDataRequest);

        foreach ($this->call as $key => $call) {
            $options = [
                CURLOPT_URL => $call['url'] ?? $this->callException('URL Expected'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 1,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $call['method'] ?? "POST",
                CURLOPT_POSTFIELDS => $call['fields'] ?? $this->callException('Fields Expected'),
                CURLOPT_HTTPHEADER => array_merge(($call['headers'] ?? []), $this->defaultHeader),
                CURLOPT_TIMEOUT => $call['timeout'] ?? 20,
                CURLOPT_HEADER => true,
            ];
            if (!empty($call['cookies'])) {
                $options[CURLOPT_COOKIE] = $call['cookies'];
            }
            if (!empty(env('CURL_INTERFACE'))) {
                $options[CURLOPT_INTERFACE] = env('CURL_INTERFACE');
            }

            ///> TEMP
            if (in_array($this->supplierName, ['CityNet', 'CloudFares'])) {
                $options[CURLOPT_SSL_VERIFYPEER] = 0;
            }

            $curls[$key] = curl_init();
            curl_setopt_array($curls[$key], $options);
            curl_multi_add_handle($multiCurl, $curls[$key]);
        }
        $running = null;
        $executionStart = microtime(true);
        do {
            curl_multi_exec($multiCurl, $running);
        } while ($running);
        $executionEnd = microtime(true);
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
        $this->saveLogs();
        if (count(array_unique($httpCodes ?? [])) == 1 && $httpCodes[0] == 0) {
            lugWarning("MULTI CURL Failed", ['url' => $call['url']]);
            $this->processStatus = false;
            sendSupplierConnectionErrorNotification($call['url'], $this->object, $this->seller, "Connection error (multiCURL)", $this->supplierName, $this->supplierConnection['method'] ?? '-');
            return false;
        }

        $this->supplierConnection = [
            'supplier_service_id' => $this->seller->supplier_service_id,
            'pid' => app('log-system')->getPid(),
            'method' => ($this->call[0]['apiMethod'] ?? $this->call['ADT']['apiMethod'] ?? '') . '(multi-curls)',
            'start_time' => $executionStart,
            'total_time' => $executionEnd - $executionStart
        ];

        $lugDataResponse = [];
        if ($this->LugTheResponse) {
            $lugDataResponse = ['curlResponse' => $this->rawResult];
        }
        lugInfo('Supplier Response - ' . $this->lugInfo['class'] . ' - ' . $this->seller->alias . ' - Multiple Curl', ['curl info (only the last curl)' => empty($curl) ? '-' : curl_getinfo($curl)] +  $lugDataResponse);
        return;
    }

    protected function single()
    {
        $requestHeaders = array_merge(($this->call['headers'] ?? []), $this->defaultHeader);
        $this->LugTheRequest($this->LugTheRequest, $requestHeaders);
        // />    Commented lines are for debuging
        // $fp = fopen('/tmp/file.txt', 'w');
        // if (strtolower($this->call['apiMethod'] ?? 'search') != 'search') {
        //     $id = $this->object->id ?? uniqid();
        //     if (!file_exists(storage_path('tmp/' .  node()->id .  '/' . $id))) {
        //         mkdir(storage_path('tmp/' .  node()->id .  '/' . $id), 0777, true);
        //     }
        //     $verbose = fopen(storage_path('tmp/' .  node()->id .  '/' . $id . '/' . str_replace(' ', '_', $this->call['apiMethod'] ?? '')), 'w+'); //> for log
        // }
        $options = [
            CURLOPT_URL => $this->call['url'] ?? $this->callException('URL Expected'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $this->call['timeout'] ?? 0,
            // CURLOPT_VERBOSE => true, //> for log
            // CURLOPT_STDERR => $verbose, //> for log
            // CURLINFO_HEADER_OUT => true,//> for request headers
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $this->call['method'] ?? "POST",
            CURLOPT_POSTFIELDS => $this->call['fields'] ?? $this->callException('Fields Expected'),
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADER => true,
        ];

        // if (strtolower($this->call['apiMethod'] ?? 'search') != 'search') {
        //     if (!empty($this->call['cookies'])) {
        //         $options[CURLOPT_VERBOSE] = true;
        //         $options[CURLOPT_STDERR] = $verbose;
        //     }
        // }

        if (!empty($this->call['cookies'])) {
            $options[CURLOPT_COOKIE] = $this->call['cookies'];
        }
        if (!empty(env('CURL_INTERFACE'))) {
            $options[CURLOPT_INTERFACE] = env('CURL_INTERFACE');
        }

        ///> TEMP
            if (in_array($this->supplierName, ['CityNet', 'CloudFares'])) {
            $options[CURLOPT_SSL_VERIFYPEER] = 0;
        }

        $curl = curl_init();
        curl_setopt_array($curl, $options);

        $counter = 1;
        do {
            $executionStart = microtime(true);
            $response = curl_exec($curl);
            $executionEnd = microtime(true);

            // rewind($verbose); ///> for log
            // dd(stream_get_contents($verbose)); ///> for log
            // dd(curl_getinfo($curl)['request_header']);  ///> for request headers

            $err = curl_error($curl);
            if ($err) {
                lugWarning("CURL Error (Try #$counter): $err", ['url' => $this->call['url'], curl_getinfo($curl)]);
                // sleep(2);
            }
            $counter++;
        } while ($counter <= 1 && !empty($err));

        if (!empty($err)) {
            $this->processStatus = false;
            // sendSupplierConnectionErrorNotification($this->call['url'], $this->object, $this->seller, "Connection error: $err", $this->supplierName, $this->supplierConnection['method'] ?? '-');
            return false;
        }
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($response, 0, $headerSize);

        preg_match('/(?<=set-cookie:).*/i', $responseHeaders, $responseCookies);
        $responseCookies = array_map('trim', $responseCookies, array_fill(0, count($responseCookies), "\r"));

        $this->rawResult = [
            'http_code' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'headers' => explode("\r\n", $responseHeaders),
            'cookie' => $responseCookies,
            'body' => $responseBody = substr($response, $headerSize),
        ];

        // fwrite($fp, $responseBody);

        $lugDataResponse = [];
        if ($this->LugTheResponse) {
            $lugDataResponse = ['curlResponse' => $this->rawResult];
        }
        $curlInfo = curl_getinfo($curl);
        lugInfo('Supplier Response - ' . $this->lugInfo['class'] . ' - ' . $this->seller->alias, $lugDataResponse + ['curl info' => $curlInfo]);

        if ($this->objectIsBooking || env('STORE_LOGS')) {
            $depends = $curlInfo + ['start_time' => Carbon::createFromTimestamp($executionStart), 'method' => $this->call['method'] ?? 'POST',  'response_body' => $responseBody, 'response_headers' => $responseHeaders, 'request_body' => $this->call['fields'], 'request_headers' => implode(', ', $requestHeaders)];
            $this->supplierConvo = [
                'entity_id' => $this->object->id,
                'entity_type' => Booking::class,
                'url' => $this->call['url'],
                'request_body' => '',
                'request_headers' => '',
                'response_body' => view('curlLog', $depends)->render(),
                'response_headers' => $responseHeaders,
                'method' => $this->call['apiMethod'] ?? ''
            ];
        }
        $this->saveLogs();
        $this->supplierConnection = [
            'supplier_service_id' => $this->seller->supplier_service_id,
            'pid' => app('log-system')->getPid(),
            'method' => $this->call['apiMethod'] ?? '',
            'start_time' => $executionStart,
            'total_time' => $executionEnd - $executionStart
        ];
        return $this->rawResult['http_code'];
    }

    protected function insertSupplierConncetionb($errorMessages)
    {
        try {
            $data = $this->supplierConnection + [
                'status' => empty($errorMessages) ? 'success' : 'error',
                'description' => strlen($errorMessages) > 255 ? 'Error message too long. Please look at Pid' : $errorMessages,
            ];
            app('db')->connection('common')->table('supplier_connections')->insert($data);
        } catch (\Throwable $th) {
            lugWarning('Unable to insert supplier connection. ' . $th->getMessage(), [$data ?? []]);
        }
    }

    protected function supplierConversaion($errorMessages = '')
    {
        if (is_array($errorMessages)) {
            $errorMessages = json_encode($errorMessages);
        }

        $this->insertSupplierConversation($errorMessages);
        $this->insertSupplierConncetionb($errorMessages);

        if (!empty($errorMessages)) {
            $this->returnBookingErrorToMarketplace($errorMessages);
            $this->sendSupplierErrorEmail($errorMessages);
        }
    }

    protected function minifyXML($indentedXml)
    {
        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($indentedXml);
        return $dom->saveXML();
    }

    // protected function saveLogs()
    // {
    //     if (env('STORE_LOGS')) {
    //         $method = $this->call['apiMethod'] ?? end($this->call)['apiMethod'];
    //         $filepath = base_path("logs/{$this->seller->alias}/$method");
    //         if (!file_exists($filepath)) {
    //             mkdir($filepath, 0777, true);
    //         }
    //         if ($this->multi) {
    //             $calls = $this->call;
    //             foreach ($calls as $key => $call) {
    //                 if ($this->multi) {
    //                     if (strlen($key) < 10) {
    //                         $filename = $this->multi ? str_replace('/', '_', $key) : Carbon::now()->format('His');
    //                     } else {
    //                         $filename = Carbon::now()->format('His');
    //                     }
    //                     $data = $call['fields'];
    //                     $ext = ($data[0] ?? '') == '<' ? 'xml' : 'json';
    //                     file_put_contents("$filepath/req_$filename.$ext", $data);
    //                     $rawResults = $this->rawResult;
    //                     if (!$this->multi) $this->repelSingleMode($rawResults);
    //                     foreach ($rawResults as $key => $rawResult) {
    //                         if (strlen($key) < 10) {
    //                             $filename = $this->multi ? str_replace('/', '_', $key) : Carbon::now()->format('His');
    //                         } else {
    //                             $filename = Carbon::now()->format('His');
    //                         }
    //                         $data = $rawResult['body'];
    //                         $ext = ($data[0] ?? '') == '<' ? 'xml' : 'json';
    //                         file_put_contents("$filepath/res$filename.$ext", $rawResult['body']);
    //                     }
    //                 }
    //             }
    //         } else {
    //             $filename = Carbon::now()->format('His');
    //             $data = $this->supplierConvo['response_body'];
    //             $ext = ($data[0] ?? '') == '<' ? 'xml' : 'txt';
    //             file_put_contents("$filepath/$filename.$ext", $data);
    //         }
    //     }
    // }
    protected function saveLogs()
    {
        if (env('STORE_LOGS')) {
            $method = $this->call['apiMethod'] ?? end($this->call)['apiMethod'];
            $filepath = base_path("logs/{$this->seller->alias}/$method");
            if (!file_exists($filepath)) {
                mkdir($filepath, 0777, true);
            }
            $calls = $this->call;
            if (!$this->multi) $this->repelSingleMode($calls);
            $c = 0;
            foreach ($calls as $key => $call) {
                $filename = $c;
                $data = $call['fields'];
                $ext = ($data[0] ?? '') == '<' ? 'xml' : 'json';
                file_put_contents("$filepath/req$filename.$ext", $data);
                $c++;
            }
            $rawResults = $this->rawResult;
            if (!$this->multi) $this->repelSingleMode($rawResults);
            $c = 0;
            foreach ($rawResults as $key => $rawResult) {
                $filename = $c;
                $data = $rawResult['body'];
                // lugWarning($data[0]);
                $ext = ($data[0] ?? '') == '<' ? 'xml' : 'json';
                file_put_contents("$filepath/res$filename.$ext", $rawResult['body']);
                $c++;
            }
        }
    }
}
