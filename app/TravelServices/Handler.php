<?php

namespace App\TravelServices;

use App\Models\SearchQuery;
use App\Models\SupplierSession;
use Carbon\Carbon;
use Exception;
use MatinUtils\NodeTracker\Models\Booking;
use MatinUtils\NodeTracker\Models\Seller;
use MatinUtils\NodeTracker\Models\FlightBuffer;
use MatinUtils\SharedLogics\LocalInventory\DirectProfile;
use stdClass;

class Handler
{
    use DirectProfile;
    protected static $type;

    static public function ping($sellerId)
    {
        $seller = Seller::find($sellerId);
        if (empty($seller)) {
            return self::failed('Invalid SellerId Entered');
        }
        $supplierAbstract = "\\App\\TravelServices\\Suppliers\\{$seller->supplierService->supplier}\\Ping";
        lugInfo("{$seller->supplierService->supplier} Ping");
        $supplier = new $supplierAbstract('ping', $seller, $seller);
        return $supplier->ping();
    }

    static public function signIn($seller)
    {
        $supplierName = $seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, 'SignIn', $seller->supplierService->travel_service, $seller->api->configs['version'] ?? '');
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName SignIn$versionForLug", [$seller]);
        $supplier = new $supplierAbstract('signin', $seller, $seller, $seller->supplierService->travel_service);
        return $supplier->signIn();
    }

    static public function customSearch($searchQuery)
    {
        $action = 'customSearch';
        $seller = $searchQuery->seller;
        $supplierName = $searchQuery->seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, $action, $searchQuery->type, $seller->api->configs['version'] ?? '');
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName $searchQuery->type customSearch$versionForLug ({$searchQuery->seller->id} - {$searchQuery->seller->alias})");
        $supplier = new $supplierAbstract($action, $searchQuery->seller, $searchQuery, $searchQuery->type);
        return $supplier->$action();
    }

    static public function targetSearch($buffer)
    {
        $action = 'targetSearch';
        $type = 'flight';
        $seller = $buffer->seller;
        $supplierName = $buffer->seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, $action, $type, $seller->api->configs['version'] ?? '');
        $object = self::fakeSearchQuery($buffer);
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName targetSearch$versionForLug ({$buffer->seller->id} - {$buffer->seller->alias})");
        $supplier = new $supplierAbstract($action, $buffer->seller, $object, $type);
        return $supplier->$action();
    }

    static public function beforeSearch($seller, $searchQuery, $action)
    {
        $supplierName = $seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, $action, $searchQuery->type, $seller->api->configs['version'] ?? '');
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName BeforeSearch : $action$versionForLug ($seller->id- $seller->alias)");
        $supplier = new $supplierAbstract($action, $seller, $searchQuery, $searchQuery->type);
        return $supplier->$action();
    }

    static public function Search($sellerId, $sessionId)
    {
        ///> get provider from modesssss
        $seller = seller::find($sellerId);
        if (empty($seller)) {
            lugError('Invalid SellerId Entered');
            return self::failed('Invalid SellerId Entered');
        }
        $searchQuery = SearchQuery::where('session_id', $sessionId)->first();
        if (empty($searchQuery)) {
            lugError('Invalid SessionId Entered');
            return self::failed('Invalid SessionId Entered');
        }

        $supplierName = $seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, 'Search', $searchQuery->type, $seller->api->configs['version'] ?? '');
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName Search $searchQuery->type$versionForLug ($seller->id- $seller->alias)");
        $supplier = new $supplierAbstract('search', $seller, $searchQuery, $searchQuery->type);

        cacheitem("searchProgress.$sessionId.sellers.$seller->id", 'inProgress');
        app('db')->connection('common')->table('jobs')->insert([
            'queue' => "SP:$searchQuery->id",
            'attempts' => 0,
            'available_at' => Carbon::now()->getTimestamp(),
            'created_at' => Carbon::now()->getTimestamp(),
            'payload' => json_encode([
                'step' => 'start',
                'sellerId' => $seller->id,
                'route' => $searchQuery->searchRoute($searchQuery),
                'travel_service' => types('travelService', $searchQuery->type)
            ])
        ]);

        return $supplier->Search();
    }

    static public function local($travelService)
    {
        ///> right now it is only used for returning flight allocations so we'll hardCode it, later when other actions were needed we'lll generalize this
        $supplierName = 'Local';
        $action = 'allocations';
        $supplierAbstract = self::factory($supplierName, $action, $travelService);
        lugInfo("$supplierName $action $travelService", ['request/object' => request()->all()]);
        $supplier = new $supplierAbstract($action, $supplierName, request()->all(), $travelService);;
        return $supplier->$action();
    }

    static public function changeBook($booking, $action)
    {
        $seller = $booking->seller;
        $action = "{$action}CB";
        $supplierName = $seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, $action, $booking->travel_service, $seller->api->configs['version'] ?? '');
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName ChangeBook , $action$versionForLug", ['booking' => $booking->toArray()]);
        $supplier = new $supplierAbstract($action, $seller, $booking, $booking->travel_service);
        return $supplier->$action();
    }

    static public function afterSearch($referenceId)
    {
        $action = request()->action;
        if (request('cachedProfile', false)) {
            $object = self::getCachedProfile($referenceId, request('travelService'), true);
            if (empty($object)) {
                lugError('Invalid Cached ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
                return self::failed('Invalid ReferenceId Entered');
            }
        } else {
            $object = self::getBuffer(request('travelService'))::with('searchQuery', 'seller')->Where('reference_id', $referenceId)->first();
            if (empty($object)) {
                lugError('Invalid ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
                return self::failed('Invalid ReferenceId Entered');
            }

            if ($object->seller_info['afters'][$action] ?? true) {
                // lugWarning('This method (' . $action . ') has already been called on this referenceId', ['seller_info' => $buffer->seller_info, 'buffer' => $buffer]);
                return self::done('This method (' . $action . ') doesnt exist or has already been called on this referenceId');
            }
        }
        $seller = $object->seller;
        $supplierName = $object->seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, $action, request('travelService'), $seller->api->configs['version'] ?? '');
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName AfterSearch : $action$versionForLug");
        $supplier = new $supplierAbstract($action, $object->seller, $object, request('travelService'));
        return $supplier->$action();
    }

    static public function passengersValidation($referenceId)
    {
        if (request('cachedProfile', false)) {
            $object = self::getCachedProfile($referenceId, request('type'));
            if (empty($object)) {
                lugError('Invalid Cached ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
                return self::failed('Invalid ReferenceId Entered');
            }
            $type = $object->travelService;
        } elseif (!empty(request('type'))) {
            $object = self::getBuffer(request('type'))::with('seller')->Where('reference_id', $referenceId)->first();
            if (empty($object)) {
                lugError('Invalid Buffer ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
                return self::failed('Invalid ReferenceId Entered');
            }
            $type = request('type');
        } else {
            $object = Booking::with('seller')->Where('reference_id', $referenceId)->first();
            if (empty($object)) {
                lugError('Invalid Booking ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
                return self::failed('Invalid ReferenceId Entered');
            }
            $type = $object->travel_service;
        }
        $seller = $object->seller;
        $supplierName = $object->seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, 'PassengersValidation', $type, $seller->api->configs['version'] ?? '');
        $supplier = new $supplierAbstract('passengersValidation', $object->seller, $object, $type);
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName PassengersValidation$versionForLug", ['object' => $object->toArray()]);
        return $supplier->passengersValidation();
    }


    static public function createBook($referenceId)
    {
        if (!empty(request('buffer'))) {
            $object = self::getBuffer(request('travelService'))::with('seller')->Where('reference_id', $referenceId)->first();
            if (empty($object)) {
                lugError('Invalid Buffer ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
                return self::failed('Invalid Buffer ReferenceId Entered');
            }
        } else {
            $object = Booking::with('seller')->Where('reference_id', $referenceId)->first();
            if (empty($object)) {
                lugError('Invalid Booking ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
                return self::failed('Invalid Booking ReferenceId Entered');
            }
        }

        $type = request('travelService');
        $seller = $object->seller;
        $supplierName = $object->seller->supplierService->supplier;
        if ($supplierName == 'Quantum') {
            $action = 'createBook';
        } else {
            $action = request()->action;
            if ($object->seller_info['createBooks'][$action] ?? false) { ///> some of the items are not in seller_info bc they need to start after payment. in sellerInfo there are only those items that get called beore  payment
                // lugWarning('This method (' . $action . ') has already been called on this referenceId', ['seller_info' => $buffer->seller_info, 'buffer' => $buffer]);
                return self::done('This method (' . $action . ') has already been called on this referenceId');
            }
        }
        $supplierAbstract = self::factory($supplierName, $action, $type, $seller->api->configs['version'] ?? '');
        $supplier = new $supplierAbstract($action, $object->seller, $object, $type);
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName CreateBook : $action$versionForLug");
        if ($supplierName == 'Quantum') {
            return $supplier->createBook();
        }
        return $supplier->$action();
    }

    static public function beforeBook($referenceId)
    {
        if (!empty(request('buffer'))) {
            $object = self::getBuffer(request('travelService'))::with('seller')->Where('reference_id', $referenceId)->first();
            if (empty($object)) {
                lugError('Invalid Buffer ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
                return self::failed('Invalid Buffer ReferenceId Entered');
            }
        } else {
            $object = Booking::with('seller')->Where('reference_id', $referenceId)->first();
            if (empty($object)) {
                lugError('Invalid Booking ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
                return self::failed('Invalid Booking ReferenceId Entered');
            }
        }

        $type = request('travelService');
        $seller = $object->seller;
        $supplierName = $object->seller->supplierService->supplier;
        if ($supplierName == 'Quantum') {
            $action = 'beforeBook';
        } else {
            $action = request()->action;
            if ($object->seller_info['beforeBooks'][$action] ?? false) { ///> some of the items are not in seller_info bc they need to start after payment. in sellerInfo there are only those items that get called beore  payment
                // lugWarning('This method (' . $action . ') has already been called on this referenceId', ['seller_info' => $buffer->seller_info, 'buffer' => $buffer]);
                return self::done('This method (' . $action . ') has already been called on this referenceId');
            }
        }
        $seller = $object->seller;
        $supplierName = $object->seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, $action, $type, $seller->api->configs['version'] ?? '');
        $supplier = new $supplierAbstract($action, $object->seller, $object, $type);
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName BeforeBook : $action$versionForLug");
        if ($supplierName == 'Quantum') {
            return $supplier->beforeBook();
        }
        return $supplier->$action();
    }

    static public function bookAndIssue($referenceId)
    {
        $booking = Booking::with('seller')->Where('reference_id', $referenceId)->first();
        if (empty($booking)) {
            lugError('Invalid ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
            return self::failed('Invalid ReferenceId Entered');
        }
        $seller = $booking->seller;
        $supplierName = $booking->seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, 'BookAndIssue', $booking->travel_service, $seller->api->configs['version'] ?? '');
        $supplier = new $supplierAbstract('bookAndIssue', $booking->seller, $booking, $booking->travel_service);
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName BookAndIssue$versionForLug", ['booking' => $booking]);
        return $supplier->bookAndIssue();
    }

    static public function book($referenceId)
    {
        ///> get provider from modesssss
        $booking = Booking::with('seller')->Where('reference_id', $referenceId)->first();
        if (empty($booking)) {
            lugError('Invalid ReferenceId Entered', [$referenceId]);
            return self::failed('Invalid ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
        }
        $seller = $booking->seller;
        $supplierName = $booking->seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, 'Book', $booking->travel_service, $seller->api->configs['version'] ?? '');
        $supplier = new $supplierAbstract('book', $booking->seller, $booking, $booking->travel_service);
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName Book$versionForLug", ['booking' => $booking]);
        return $supplier->book();
    }

    static public function afterBook($referenceId)
    {
        $booking = Booking::with('seller')->Where('reference_id', $referenceId)->first();
        if (empty($booking)) {
            lugError('Invalid ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
            return self::failed('Invalid ReferenceId Entered');
        }
        $seller = $booking->seller;
        $supplierName = $booking->seller->supplierService->supplier;
        $action = request()->action;
        $supplierAbstract = self::factory($supplierName, $action, request()->travelService, $seller->api->configs['version'] ?? '');
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName AfterBook : $action$versionForLug", ['booking' => $booking]);
        $supplier = new $supplierAbstract($action, $booking->seller, $booking, request()->travelService);
        return $supplier->$action();
    }

    static public function issue($referenceId)
    {
        $booking = Booking::with('seller')->Where('reference_id', $referenceId)->first();
        if (empty($booking)) {
            lugError('Invalid ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
            return self::failed('Invalid ReferenceId Entered');
        }
        $seller = $booking->seller;
        $supplierName = $booking->seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, 'Issue', $booking->travel_service, $seller->api->configs['version'] ?? '');
        $supplier = new $supplierAbstract('issue', $booking->seller, $booking, $booking->travel_service);
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName Issue$versionForLug", ['booking' => $booking->toArray(), $booking->travel_service]);
        return $supplier->issue();
    }

    static public function asyncTicketing($referenceId)
    {
        $booking = Booking::with('seller')->Where('reference_id', $referenceId)->first();
        if (empty($booking)) {
            lugError('Invalid ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
            return self::failed('Invalid ReferenceId Entered');
        }
        $supplierName = $booking->seller->supplierService->supplier;
        if ($supplierName == 'Sabre' || $supplierName == 'Kplus') {
            lugError("No Async ticketing for $supplierName", ['referenceId' => $booking->toArray(), 'request' => request()->all()]);
            return self::failed("No Async ticketing for $supplierName");
        }
        $seller = $booking->seller;
        $supplierAbstract = self::factory($supplierName, 'asyncTicketing', $booking->travel_service, $seller->api->configs['version'] ?? '');
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName Async Ticketing$versionForLug", ['seller' => $booking->seller->toArray(), 'booking' => $booking->toArray()]);
        $supplier = new $supplierAbstract('asyncTicketing', $booking->seller, $booking, $booking->travel_service);
        return $supplier->asyncTicketing();
    }

    static public function expire($referenceId)
    {
        $booking = Booking::with('seller')->Where('reference_id', $referenceId)->first();
        if (empty($booking)) {
            lugError('Invalid ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
            return self::failed('Invalid ReferenceId Entered');
        }
        $seller = $booking->seller;
        $supplierName = $booking->seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, 'expire', $booking->travel_service, $seller->api->configs['version'] ?? '');
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName Expire$versionForLug", ['seller' => $booking->seller->toArray(), 'booking' => $booking->toArray()]);
        $supplier = new $supplierAbstract('expire', $booking->seller, $booking, $booking->travel_service);
        return $supplier->expire();
    }

    static public function signOut($sessionsInput)
    {
        ///> temporary dirty bc sometimess there is an error
        $sessionsDecoded = json_decode($sessionsInput);
        if (!is_array($sessionsDecoded)) {
            $sessions = (array) $sessionsDecoded;
        } else {
            $sessions = $sessionsDecoded;
        }

        $supplierSessions = SupplierSession::WhereIn('session', $sessions)->get();
        if ($supplierSessions->count() == 0) {
            lugError('Invalid SessionId Entered', ['sessions' => $sessions, 'sessionsDecoded' => $sessionsDecoded, 'sessionsInput' => $sessionsInput, 'request' => request()->all()]);
            return;
        }
        $seller = $supplierSessions->first()->seller;
        $supplierName = $supplierSessions->first()->seller->supplierService->supplier;
        $action = "signOut";
        $travelService = $supplierSessions->first()->seller->supplierService->travel_service;
        // luginfo(count($supplierSessions) . " Sessions to sign out for " . $supplierName);
        $supplierAbstract = self::factory($supplierName, $action, $travelService, $seller->api->configs['version'] ?? '');
        $supplier = new $supplierAbstract($action, $supplierSessions->first()->seller, $supplierSessions, $travelService);
        // lugInfo('signOut - Supplier: ' . $supplierName, [$supplierSessions]);
        $supplier->$action();
    }

    static public function pay($referenceId)
    {
        $booking = Booking::with('seller')->Where('reference_id', $referenceId)->first();
        if (empty($booking)) {
            lugError('Invalid ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
            return self::failed('Invalid ReferenceId Entered');
        }
        $seller = $booking->seller;
        $supplierName = $booking->seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, 'pay', $booking->travel_service, $seller->api->configs['version'] ?? '');
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName Pay$versionForLug", ['seller' => $booking->seller->toArray(), 'booking' => $booking->toArray()]);
        $supplier = new $supplierAbstract('pay', $booking->seller, $booking, $booking->travel_service);
        return $supplier->pay();
    }

    static public function data($referenceId)
    {
        $booking = Booking::with('seller')->Where('reference_id', $referenceId)->first();
        if (empty($booking)) {
            lugError('Invalid ReferenceId Entered', ['referenceId' => $referenceId, 'request' => request()->all()]);
            return self::failed('Invalid ReferenceId Entered');
        }
        $seller = $booking->seller;
        $supplierName = $booking->seller->supplierService->supplier;
        $supplierAbstract = self::factory($supplierName, 'retrieveBookingData', $booking->travel_service, $seller->api->configs['version'] ?? '');
        $versionForLug = strlen($seller->api->configs['version'] ?? '') < 1 ? '' : " ver {$seller->api->configs['version']}";
        lugInfo(">> Start $supplierName retrieveBookingData$versionForLug", ['booking' => $booking->toArray()]);
        $supplier = new $supplierAbstract('retrieveBookingData', $booking->seller, $booking, $booking->travel_service);

        return $supplier->retrieveBookingData();
    }

    static protected function factory($supplierName, $logic, $type, $supplierVersion = '')
    {
        $type  = ucfirst($type);
        $logic  = ucfirst($logic);
        $version = strlen($supplierVersion) < 1 ? '' : "$supplierVersion\\";

        $className = "$supplierName$supplierVersion$logic$type";
        if (class_exists($className)) {
            return $className;
        }

        if (!trait_exists("\\App\\TravelServices\\Suppliers\\{$supplierName}\\Logics\\$type\\$version$logic")) {
            throw new Exception("$supplierName $type '$logic' Not Available.");
        }

        eval("class $className extends \\App\\TravelServices\\Suppliers\\{$supplierName}\\$type {
            use \\App\\TravelServices\\Suppliers\\{$supplierName}\\Logics\\$type\\$version$logic;}");

        return "$className";
    }

    static protected function getBuffer($service)
    {
        return 'App\Models\\' . ucfirst($service) . 'Buffer';
    }

    static protected function getCachedProfile($id, $type, $withPriceInfo = false)
    {
        ///> for now this is only or local profiles
        $exploded = explode('/', $id);
        return (new CachedBuffer($type, $exploded[0], $exploded[1] ?? null))->getCachedBuffer($withPriceInfo);
    }

    static protected function failed($message = 'failed')
    {
        return ['status' => false, 'message' => $message];
    }

    static protected function done($message = 'failed')
    {
        return ['status' => true, 'message' => $message];
    }

    static protected function fakeSearchQuery($buffer)
    {

        $serviceInfo = $buffer->serviceInfo->data;
        foreach ($serviceInfo['legs'] as $legIndex => $leg) {
            $legs[] = [
                'origin' => [
                    'city' => $leg['info']['departure']['airport']['city'],
                    'airport' => $leg['info']['departure']['airport'],
                    'buffer' => $serviceInfo['query_params']['legs'][$legIndex]['origin']['buffer'],
                    'airportId' => $leg['info']['departure']['airport']['id']
                ],
                'destination' => [
                    'city' => $leg['info']['arrival']['airport']['city'],
                    'airport' => $leg['info']['arrival']['airport'],
                    'buffer' => $serviceInfo['query_params']['legs'][$legIndex]['destination']['buffer'],
                    'airportId' => $leg['info']['arrival']['airport']['id']
                ],
                'departure' => $serviceInfo['query_params']['legs'][$legIndex]['departure'],
                'airlineId' => $leg['info']['airline']['id'],
                'flightInfo' => [
                    'origin' => $serviceInfo['legs'][$legIndex]['info']['departure']['airport']['id'],
                    'destination' => $serviceInfo['legs'][$legIndex]['info']['arrival']['airport']['id'],
                    'airline' => $serviceInfo['legs'][$legIndex]['info']['airline']['id'],
                    'flightNumber' => $serviceInfo['legs'][$legIndex]['info']['flight_number'],
                    'class' => $serviceInfo['legs'][$legIndex]['info']['resBookDesigCode'],
                    'luggage' => $serviceInfo['legs'][$legIndex]['segments'][0]['luggage']
                ]
            ];
        }
        $searchQuery = new stdClass;
        $searchQuery->id = null;
        $searchQuery->buffer = $buffer;
        $searchQuery->bufferReferenceId = $buffer->seller_info['bufferReferenceId'] ?? null; ///> remove this, we added seller_info
        $searchQuery->seller_info = $buffer->seller_info;
        $searchQuery->type = 'flight';
        $searchQuery->searcher_identity = 'targetSearch';
        $searchQuery->buyer_group_id = $buffer->price_info['buyerGroup']['b2b'];
        $searchQuery->seller = $buffer->seller;
        $searchQuery->query_params = [
            'legs' => $legs,
            'buyer_id' => $serviceInfo['query_params']['buyer_id'] ?? null,
            'cabin' =>  $serviceInfo['query_params']['cabin'],
            'tripType' =>  $serviceInfo['query_params']['tripType'],
            'adults' => $serviceInfo['query_params']['adults'],
            'children' => $serviceInfo['query_params']['children'],
            'infants' => $serviceInfo['query_params']['infants'],
            'charterOnly' => 'false',
            'marketplace_currency' => $buffer->price_info['marketplacePrice']['currency'],
            'buyer_currency' => $buffer->price_info['buyerPrice']['currency'],
            // 'current_lang' => $serviceInfo['query_params']['current_lang'],
            "version" => 2,
            "apiVersion" => "v2"
        ];
        return $searchQuery;
    }
}
