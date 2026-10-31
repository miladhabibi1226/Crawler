<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <air:AirTicketingReq TargetBranch="{{ $targetBranch }}" TraceId="a9710fc2-c393-4a50-aaae-790a6c86891d"
            AuthorizedBy="test" ReturnInfoOnFail="true" BulkTicket="false"
            xmlns:air="http://www.travelport.com/schema/air_v51_0">
            <com:BillingPointOfSaleInfo OriginApplication="UAPI"
                xmlns:com="http://www.travelport.com/schema/common_v51_0" />
            <air:AirReservationLocatorCode>{{ $bookingResult['TravelPortBookId'] }}</air:AirReservationLocatorCode>
            {{--  <air:commission></air:commission>  --}}
        </air:AirTicketingReq>
    </soap:Body>
</soap:Envelope>
