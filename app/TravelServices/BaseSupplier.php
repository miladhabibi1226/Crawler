<?php

namespace App\TravelServices;

use App\Models\SearchQuery;
use App\Models\SupplierSession;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Schema;
use MatinUtils\NodeTracker\Models\BasicRule;
use MatinUtils\NodeTracker\Models\Booking;
use MatinUtils\NodeTracker\Models\City;
use MatinUtils\NodeTracker\Models\Country;
use MatinUtils\NodeTracker\Models\Landmark;
use MatinUtils\NodeTracker\Models\User;
use MatinUtils\NodeTracker\Supplier;
use stdClass;
use MatinUtils\NodeTracker\Models\Timezone;

abstract class BaseSupplier
{
    use Supplier;
    protected $seller;
    protected $object;
    protected $objectStatus;
    protected $travelService;
    protected $action;
    protected $supplierErrorMessage = null, $errors = [];
    protected $fromFile;
    protected $lugInfo = [];
    protected $items = [];
    protected $sessionLifeTime;
    protected $supplierName;
    protected $dataReference;
    protected $depends;
    protected $bookingType;
    protected $objectIsBooking;
    protected $objectIsBuffer;
    protected $objectIsSearchQuery;
    protected $objectIsFromCachedItem;
    protected $queryParams;
    protected $rawResult = [];
    protected $processStatus = true, $cacheAllowed = true;
    protected $defaultHeader = [];
    protected $call = [];

    public function __construct($action, $seller, $object, $travelService = null)
    {
        $this->fromFile = request()->fromFile;
        $this->seller = $seller;
        $this->object = $object;
        $this->travelService = $travelService;
        $this->action = $action;
        $this->objectIsBooking = $this->isObjectBooking();
        $this->objectIsBuffer = $this->isObjectBuffer();
        $this->objectIsSearchQuery = $this->isObjectSearchQuery();
        $this->objectIsFromCachedItem = $this->isObjectCachedProfile();
        $this->lugInfo = [
            'class' => get_called_class(),
        ];

        if (($this->objectIsBooking || $this->objectIsBuffer) && !empty($this->object->seller_info['bookingType'])) {
            $this->bookingType = $this->object->seller_info['bookingType'];
        }
        $this->retrieveQueryParams();
    }

    protected function isObjectBooking()
    {
        return is_a($this->object, Booking::class);
    }

    protected function isObjectSearchQuery()
    {
        return is_a($this->object, SearchQuery::class);
    }

    protected function isObjectCachedProfile()
    {
        if ($this->isObjectBooking()) {
            return !empty($this->object->seller_info['cachedId']);
        }
        return is_a($this->object, CachedBuffer::class);
    }

    protected function isObjectBuffer()
    {
        $ts = ucfirst($this->travelService);
        return is_a($this->object, "App\Models\\$ts" . "Buffer");
    }

    protected function buyerGroupId()
    {
        if (in_array($this->action, ['search', 'targetSearch', 'customSearch'])) {
            return $this->object->buyer_group_id;
        }
        return $this->object->price_info['buyerGroup']['b2b'];
    }

    protected function retrieveQueryParams()
    {
        if ($this->action != 'signOut') {
            if ($this->objectIsSearchQuery || is_a($this->object, 'stdClass')) {
                $this->queryParams = $this->object->query_params;
            } else {
                $this->queryParams = $this->getServiceInfo()['query_params'] ?? [];
            }
        }
    }

    protected function addError($error, $key = null)
    {
        if (empty($key)) {
            $this->errors[] = $error;
        } else {
            $this->errors[$key] = $error;
        }
    }

    protected function addErrors($message, $errors = [])
    {
        $errors = (array) $errors;
        $this->errors = array_merge($errors, $this->errors);
        lugError($message, $this->lugInfo + ['errors' => $errors]);
    }

    protected function callException($error)
    {
        $this->failed($error);
        throw new Exception($error);
    }

    public function getErrors()
    {
        $object = new stdClass;
        $object->errors = $this->errors;
        return $object;
    }

    protected function repelSingleMode(&$set)
    {
        if (empty($set)) {
            return;
        }
        if (empty($set[0])) {
            $set = [$set];
        }
    }

    protected function checkErrors()
    {
        return !empty($this->errors);
    }

    protected function checkSession($session)
    {
        $this->supplierSession = SupplierSession::where('session', $session)->where('expire_on', '>', Carbon::now())->first();
        if (empty($this->supplierSession)) {
            return false;
        }
        return true;
    }

    protected function done()
    {
        return ['status' => true];
    }

    protected function failed($error = null)
    {
        return ['status' => false];
    }

    public function getBuyerGroupSeller(int $buyerGroupId = null, bool $withBasicRule = true, bool $canBeEmpty = true)
    {
        $buyergroup = $buyerGroupId ?? $this->object->buyer_group_id;
        $nodeId = node()->id;
        $bgs = cachedDatabase("{$nodeId}_buyer_group_seller", [['seller_id', '=', $this->seller->id], ['buyer_group_id', '=', $buyergroup]], [], 1);
        if (!$bgs && !$canBeEmpty) { ///> means cacher has problem
            throw new Exception("Can't get BuyerGroupSeller from cache", 1);
        }
        $bgs = array_values($bgs ?? [])[0] ?? [];
        if ($withBasicRule && !empty($bgs['basic_rule_id'])) {
            $basicRule = cachedDatabase("{$nodeId}_basic_rules", [['id', '=',  $bgs['basic_rule_id']]]);
            $bgs['basic_rule'] = array_values($basicRule)[0] ?? [];
            if (!empty($bgs['basic_rule'])) {
                $exceptions = cachedDatabase("{$nodeId}_pricing_exceptions", [['entity_id', '=', $bgs['basic_rule']['id']], ['entity_type', '=', BasicRule::class]]);
                $bgs['basic_rule']['exceptions'] = $exceptions ?? [];
            }
        }
        return $bgs;
    }

    protected function getCountry($needle)
    {
        if (!is_string($needle) && !is_int($needle)) {
            return null;
        }

        static $countries;
        if (empty($countries[$needle])) {
            $countries[$needle] = Country::where('id', $needle)->orWhere('en', $needle)
                ->first() ?? null;
        }

        return $countries[$needle];
    }

    protected function getLandmark($landmarkId)
    {
        static $landmark;

        if (empty($landmark[$landmarkId])) {
            $landmark[$landmarkId] = Landmark::with('profile')->find($landmarkId)->toArray() ?? null;
        }

        return $landmark[$landmarkId];
    }

    protected function getCityWithCountry($needle)
    {
        static $city;
        if (empty($city[$needle])) {
            $city[$needle] = City::where(function ($query) use ($needle) {
                $query->where('id', $needle)->orWhere('en', $needle)->orWhere('abb', $needle);
            })->where('deleted_at', null)->with('country')->first()->toArray();
        }
        return $city[$needle];
    }

    protected function getUser($userId)
    {
        static $user;
        if (empty($user[$userId])) {
            $user[$userId] = User::find($userId);
        }
        return $user[$userId];
    }

    public function getExchanges()
    {
        $exchanges = app('db')->table('exchanges')->select('id', 'source_currency', 'destination_currency', 'source_rate', 'destination_rate')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('source_currency', $this->seller->currency_id)->where('destination_currency', node()->currency_id); ///> seller to node
                })->orWhere(function ($q) {
                    $q->where('source_currency', node()->currency_id)->where('destination_currency', $this->queryParams['buyer_currency']['id']); ///> node to buyer
                })->orWhere(function ($q) {
                    $q->where('source_currency', node()->currency_id)->where('destination_currency', $this->queryParams['marketplace_currency']['id']); ///> node to marketplace
                })->orWhere(function ($q) {
                    $q->where('source_currency', node()->currency_id)->where('destination_currency', $this->seller->currency_id); ///> node to seller 
                });
            })->where('deleted_at', null)->get();
        foreach ($exchanges as $exchange) {
            $data[$exchange->source_currency][$exchange->destination_currency] = $exchange;
        }
        return $data ?? [];
    }

    public function checkExpiryTime($supplierTime)
    {
        $buyerGroupSeller = $this->getBuyerGroupSeller($this->object->price_info['buyerGroup']['b2b'], false);
        if (!empty($buyerGroupSeller['expiry_limit'])) {
            $adminTime = Carbon::now()->addMinutes($buyerGroupSeller['expiry_limit']);
            lugInfo('Admin Set Expiry Time', [
                'BuyerGroupSeller Time' => $buyerGroupSeller['expiry_limit'],
                'Admin time' => $adminTime,
                'Supplier Time' => $supplierTime,
                'Is admin time choosen?' => ($adminTime < $supplierTime),
                'BuyerGroupSeller' => $buyerGroupSeller
            ]);
            if ($adminTime < $supplierTime) {
                return $adminTime;
            } else {
                return $supplierTime;
            }
        }
        return $supplierTime;
    }

    protected function isOnholdable()
    {
        $supplierService = $this->seller->supplierService->is_onholdable;
        $buyerGroupSeller = $this->getBuyerGroupSeller(null, false) ?? false;
        // lugInfo('Main onholdable: ' . $this->seller->alias, ['supplierService' => $supplierService, 'buyerGroupSeller' => $buyerGroupSeller['is_onholdable'], $buyerGroupSeller]);
        if (!$supplierService || !$buyerGroupSeller['is_onholdable']) {
            return false;
        }
        return true;
    }

    public function sellerTransaction()
    {
        $type = $this->bookingType == 'bookAndIssue' ? 'dt-issue' : 'm-issue';
        $this->insertSellerTransaction($this->seller, $this->object, $type);
    }

    public function findOrCreateSupplierSession()
    {
        $activeSession = SupplierSession::where('seller_id', $this->seller->id)->first();
        if (!empty($activeSession)) {
            // lugInfo('old session');
            $this->supplierSession = $activeSession;
            $this->latestSession = $this->supplierSession->session;
        } else {
            // lugInfo('new session');
            $signIn = Handler::signIn($this->seller);
            if (!$signIn['status'] ?? false) {
                return false;
            }
            $this->supplierSession = $signIn['supplierSession'];
            $this->latestSession = $this->supplierSession->session;
        }
        return true;
    }

    public function hookUrl()
    {
        return 'https://marketplace.' . node()->domain . '/v1/hook/' . $this->object->reference_id;
    }

    public function callSignOut($session)
    {
        shell_exec(base_path() . "/artisan signout " . node()->id . " '" . json_encode([$session]) . "' -p " . app('log-system')->getpid() . "  > /dev/null 2>/dev/null &");
    }

    public function failBook()
    {
        if ($this->objectIsBooking) {
            $this->object->status = 'failed';
            $this->object->update();
        }
    }

    public function contactAdminStatus()
    {
        if ($this->objectIsBooking) {
            $this->object->status = 'contact_admin';
            $this->object->update();
        }
    }

    public function addErrorToBook($error, $errorClass = 'customer')
    {
        if ($this->objectIsBooking) { ////> we used to only add non-'technical' errors to the booking but the po asked to just save everything. #TS1534
            $error = str_replace('; ', '', $error); ///> temporary. until i fix city net errors. no other supplier has this
            $bookingInfo = $this->object->booking_info;
            $bookingInfo['error'] = [
                'class' => $errorClass,
                'code' => $this->getErrorCode($error),
                'message' => $error
            ];
            $this->object->booking_info = $bookingInfo;
            $this->object->update();
        }
    }

    protected function itemIssued($pnr)
    {
        if (empty($pnr) || empty($pnr[0])) {
            return;
        }
        $invoiceItem = $this->object->invoiceItems->first();
        $description = $invoiceItem->description;
        if ($this->travelService == 'flight') {
            $pnr = implode(',', (array) $pnr);
            $description .= ", PNR:$pnr";
        }
        if ($this->travelService == 'hotel') {
            $pnr = implode(',', (array) $pnr);
            $description .= ", Confirmation No:$pnr";
        }

        $passengers = $this->{$this->travelService . 'Pax'}();
        $description .= " <br>$passengers";
        $invoiceItem->description = $description;
        $invoiceItem->update();
    }

    protected function flightPax()
    {
        $passengers = [];
        $firstNameCaption = $this->getPassengerFormCaption('first_name');
        $lastNameCaption = $this->getPassengerFormCaption('last_name');
        foreach ($this->object->booking_info['tickets'] as $passenger) {
            $et = implode(',', array_map(fn($arr) => implode(',', (array) $arr), $passenger['ticket_number']));
            $firstNmae = strtoupper($this->object->passenger_info[$passenger['passengerType']][$passenger['passengerIndex']][$firstNameCaption]);
            $lastNmae = strtoupper($this->object->passenger_info[$passenger['passengerType']][$passenger['passengerIndex']][$lastNameCaption]);
            $passengers[] =  "$lastNmae $firstNmae(et.$et)";
        }
        $pax = "{$this->object->service_info["passengersCount"]["adults"]}ADT {$this->object->service_info["passengersCount"]["children"]}CHD {$this->object->service_info["passengersCount"]["infants"]}INF";
        $passengers = implode(', ', $passengers);
        return "$pax , $passengers";
    }

    protected function hotelPax()
    {
        $passengers = [];
        $num = 0;
        $firstNameCaption = $this->getPassengerFormCaption('first_name');
        $lastNameCaption = $this->getPassengerFormCaption('last_name');
        foreach ($this->object->passenger_info as $roomPassengers) {
            foreach ($roomPassengers as $passengerTypeGroup) {
                foreach ($passengerTypeGroup as $passenger) {
                    $num++;
                    $passengers[] = $passenger[$firstNameCaption] . ' ' . $passenger[$lastNameCaption];
                }
            }
        }
        $passengers = implode(' - ', $passengers);
        return "for {$num}PAX: $passengers.";
    }

    protected function packagePax()
    {
        $passengers = [];
        $num = 0;
        $firstNameCaption = $this->getPassengerFormCaption('first_name');
        $lastNameCaption = $this->getPassengerFormCaption('last_name');
        foreach ($this->object->passenger_info as $roomTypePassengers) {
            foreach ($roomTypePassengers as $roomPassengers) {
                foreach ($roomPassengers as $passengerTypeGroup) {
                    foreach ($passengerTypeGroup as $passenger) {
                        $num++;
                        $passengers[] = $passenger[$firstNameCaption] . ' ' . $passenger[$lastNameCaption];
                    }
                }
            }
        }
        $passengers = implode(' - ', $passengers);
        return "for {$num}PAX: $passengers.";
    }

    protected function visaPax()
    {
        $passengers = [];
        $num = 0;
        $firstNameCaption = $this->getPassengerFormCaption('first_name');
        $lastNameCaption = $this->getPassengerFormCaption('last_name');
        foreach ($this->object->passenger_info as $passenger) {
            $num++;
            $passengers[] = $passenger[$firstNameCaption] . ' ' . $passenger[$lastNameCaption];
        }
        $passengers = implode(' - ', $passengers);
        return "for {$num}PAX: $passengers.";
    }

    protected function eventPax()
    {
        $passengers = [];
        $num = 0;
        $firstNameCaption = $this->getPassengerFormCaption('first_name');
        $lastNameCaption = $this->getPassengerFormCaption('last_name');
        foreach ($this->object->passenger_info as $passengerTypeGroup) {
            foreach ($passengerTypeGroup as $passenger) {
                $num++;
                $passengers[] = $passenger[$firstNameCaption] . ' ' . $passenger[$lastNameCaption];
            }
        }
        $passengers = implode(' - ', $passengers);
        return "for {$num}PAX: $passengers.";
    }

    protected function tourPax()
    {
        self::eventPax();
    }

    protected function flightTicketInfo($passengers) ///> re-written for parto flights
    {
        $tickets = [];
        foreach ($this->object->passenger_info as $passengerType => $typePassengers) {
            foreach ($typePassengers as $passengerIndex => $passenger) {
                $tickets[] = [
                    'passengerType' => $passengerType,
                    'passengerIndex' => $passengerIndex,
                    'ticket_number' => $tk = (array) ($passengers[strtoupper(str_replace(' ', '', $passenger['Last_name'] . $passenger['First_name']))] ?? [])
                ];
                if (empty($tk[0])) {
                    $this->objectStatus = 'waiting_issue';
                }
            }
        }
        return $tickets;
    }

    protected function getLegSegments()
    {
        foreach ($this->getServiceInfo()['legs'] as $legIndex => $leg) {
            foreach ($leg['segments'] as $segmentIndex => $segment) {
                $data[$segment['departure']['airport']['abb'] . '/' . $segment['arrival']['airport']['abb']] = ['legIndex' => $legIndex, 'segmentIndex' => $segmentIndex];
            }
        }
        return $data;
    }

    protected function getPassengerFormCaption($type, $allOrLead = 'all', $itemNumber = 0)
    {
        return $this->object->seller_info['passengersForm'][$allOrLead][$type][$itemNumber]['caption'] ?? null;
        ///> smal modification in parto
    }

    protected function destinationCountries()
    {
        $serviceInfo = $this->getServiceInfo();
        switch (strtoupper($this->travelService)) {
            case 'FLIGHT':
                $countries = $serviceInfo['legs'][0]['info']['arrival']['airport']['city']['country'];
                break;
            case 'HOTEL':
                $countries = $serviceInfo['query_params']['destination_city']['country']; ///> have to put it into service info later
                break;
            case 'EVENT':
                $countries = $serviceInfo['city']['country'];
                break;
            case 'VISA':
                $countries = $serviceInfo['visa']['country'];
                break;
            case 'PACKAGE':
                $countries = array_column(array_column($serviceInfo['package']['places'], 'city') ?? [], 'country') ?? [];
                break;
            case 'TOUR':
                $countries = array_column($serviceInfo['places'], 'country') ?? [];
                break;
        }

        $this->repelSingleMode($countries);
        foreach ($countries as $country) { ///> as in array_unique
            $uniqueCountries[$country['id']] = $country;
        }
        return array_values($uniqueCountries);
    }

    protected function lugTheRequest($LugTheRequest, $requestHeaders) ///> temporarily re-written in Internal for lug
    {
        $lugDataRequest = [];
        if ($LugTheRequest) {
            $lugDataRequest = ['body' => $this->call['fields'] ?? [], 'headers' => $requestHeaders] + $this->call;
            unset($lugDataRequest['fields']);
            $lugDataRequest = ['request' => $lugDataRequest, 'callHeaders' => $this->call['headers'] ?? [], 'defaultHeader' => $this->defaultHeader ?? []];
        }
        lugInfo('Supplier Request - ' . $this->lugInfo['class'] . ' - ' . $this->seller->alias, $lugDataRequest);
    }

    protected function flightOriginCountry($item = null)
    {
        $country = $this->getServiceInfo()['legs'][0]['info']['departure']['airport']['city']['country'];
        if (empty($item)) {
            return $country;
        }
        return $country[$item];
    }

    protected function cleanUrlSlash($url)
    {
        if (substr($url, -1) === '/') {
            $url = rtrim($url, '/');
        }
        return $url;
    }

    protected function getErrorClass($errorMessage)
    {
        foreach ($this->errorPatterns() as $errorPattern => $errorInfo) {
            $errorPattern = str_replace('/', '-', $errorPattern);
            if (preg_match("/$errorPattern/", $errorMessage)) {
                $possibleClasses[] = $errorInfo['class'];
            }
        }
        if (empty($possibleClasses)) {
            return 'technical';
        }
        if (in_array('node', $possibleClasses)) {
            return 'node';
        }
        return 'customer';
    }

    protected function getErrorCode($errorMessage)
    {
        $groups = [
            'expired' => 680,
            'unavailableOrPriceChange' => 681,
            'technical' => 682,
            'supplier' => 683,
        ];
        foreach ($this->errorPatterns() as $errorPattern => $errorInfo) {
            $errorPattern = str_replace('/', '-', $errorPattern);
            if (preg_match("/$errorPattern/", $errorMessage)) {
                $group = $errorInfo['group'];
                break;
            }
        }
        if (empty($group)) {
            return $groups['technical'];
        }
        return $groups[$group];
    }

    protected function errorPatterns()
    {
        return [];
    }

    protected function sendSupplierErrorEmail($errorMessages)
    {
        sendSupplierErrorNotification($this->object, $this->seller, $errorMessages, $this->supplierName, $this->supplierConnection['method'] ?? '-');
        if (in_array($this->action, ['book', 'issue', 'bookAndIssue'])) {
            sendSupplierIssueErrorNotification($this->object, $this->seller, $errorMessages, $this->supplierName, $this->supplierConnection['method'] ?? '-');
        }
    }

    protected function returnBookingErrorToMarketplace($errorMessages)
    {
        $errorClass = $this->getErrorClass($errorMessages);
        $this->addErrorToBook($errorMessages, $errorClass);
    }

    protected function insertSupplierConversation($errorMessages)
    {
        if ($this->objectIsBooking) {
            try {
                app('db')->table('remote_conversations')->insert(($this->supplierConvo ?? []) + ['error_messages' =>  $errorMessages]);
            } catch (\Throwable $th) {
                lugWarning('Unable to insert supplier conversation. ' . $th->getMessage(), ($this->supplierConvo ?? []) + ['error_messages' =>  $errorMessages]);
            }
        }
    }

    protected function getServiceInfo()
    {
        return is_a($this->object, "App\Models\\FlightBuffer") ? $this->object->serviceInfo->data : $this->object->service_info ?? [];
    }

    protected function getTimezones($countryCode)
    {
        static $timezonez;
        if (empty($timezonez[$countryCode])) {
            $timezonez[$countryCode] = Timezone::where('country_code', $countryCode)
                ->where('status', 'Canonical')
                ->get();
        }
        return $timezonez[$countryCode];
    }

    public function passengerModificationsRule()
    {
        $passengerCount = $this->queryParams['adults'] . ($this->queryParams['children'] ?? 0) . ($this->queryParams['infants'] ?? 0);
        return match ($passengerCount) {
            '100' => "101",
            '101' => "101",
            default => "111"
        };
    }

    public function supplierSupportsPassengerCountModification()
    {
        ///> local and quantum suppliers don't support it
        ///> from 3rd party suppliers, only suppliers that have availableSeats
        ///> MystiFly, CloudFares will be skipped bc we're not caching them
        return !in_array($this->supplierName, ['Local', 'Quantum', 'Avtra', 'Amadeus', 'Accelaero', 'MystiFly', 'CloudFares']);
    }

    protected function expireSupplierCache()
    {
        ///> #TS1621
        ///> here we will try to find the key of the cached supplier response
        try {
            $supplierVersion = $this->seller->api->configs['version'] ?? 'NAN';
            $text = '';
            foreach ($this->queryParams['legs'] as $leg) {
                $text .= "{$leg['origin']['buffer']['id']}_{$leg['destination']['buffer']['id']}_{$leg['departure']}_";
            }
            $cab = $this->queryParams['cabin'][0];
            $modifiedPassengerCount = $this->passengerModificationsRule();
            $modifiedPassengersTypeCount = [
                'adults' => $this->supplierSupportsPassengerCountModification() ?  $modifiedPassengerCount[0] : $this->queryParams['adults'],
                'children' => $this->supplierSupportsPassengerCountModification() ?  $modifiedPassengerCount[1] : $this->queryParams['children'] ?? 0,
                'infants' => $this->supplierSupportsPassengerCountModification() ?  $modifiedPassengerCount[2] : $this->queryParams['infants'] ?? 0
            ];
            $supplierCacheKey =  "{$supplierVersion}_$text{$cab}_{$modifiedPassengersTypeCount['adults']}_{$modifiedPassengersTypeCount['children']}_{$modifiedPassengersTypeCount['infants']}";

            ///> no we'll try to find the cache record from db and delete it
            $date = Carbon::now()->format('y-m-d');
            if (!Schema::connection('supplierCache')->hasTable("{$date}_{$this->supplierName}")) return false; ///> if table doesn't exists it means nothing has been cached yet for today. won't really happen, just to be sure
            $fromDB = app('db')->connection('supplierCache')->table("{$date}_{$this->supplierName}")->where('key', $supplierCacheKey)->where('node_id', node()->id)
                ->where('seller_id', $this->seller->id)->delete();
            lugWarning('supplier cache deleted', [$fromDB, $supplierCacheKey]);
        } catch (\Throwable $th) {
            lugWarning('can not delete supplier cache', [$th->getMessage(), $th->getTraceAsString()]);
        }
    }

    public function setSupplierErrorMessage(string $errorMessage = '')
    {
        if (empty(!$this->supplierErrorMessage)) { ///> shouldn't realy happen but just in case
            $errorMessage = "$this->supplierErrorMessage. $errorMessage";
        }
        return $this->supplierErrorMessage = $errorMessage;
    }

    public function getSupplierErrorMessage()
    {
        return $this->supplierErrorMessage;
    }
}
