<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        @php
        use Illuminate\Support\Carbon;
        $traceId = '1G_' . Carbon::now()->format('YmdHisv');
        @endphp
        <air:LowFareSearchReq TargetBranch="{{ $targetBranch }}" SolutionResult="true" PreferCompleteItinerary="true"
            TraceId="{{ $traceId }}" xmlns:air="http://www.travelport.com/schema/air_v52_0"
            xmlns:com="http://www.travelport.com/schema/common_v52_0">
            <com:BillingPointOfSaleInfo OriginApplication="UAPI" />
            @foreach ($legs as $leg)
                <air:SearchAirLeg>
                    <air:SearchOrigin>
                        @if ($leg['origin']['isCity'])
                            <com:City Code="{{ $leg['origin']['abb'] }}" />
                        @else
                            <com:Airport Code="{{ $leg['origin']['abb'] }}" />
                        @endif
                    </air:SearchOrigin>
                    <air:SearchDestination>
                        @if ($leg['destination']['isCity'])
                            <com:City Code="{{ $leg['destination']['abb'] }}" />
                        @else
                            <com:Airport Code="{{ $leg['destination']['abb'] }}" />
                        @endif
                    </air:SearchDestination>
                    <air:SearchDepTime PreferredTime="{{ $leg['departure'] }}" />
                </air:SearchAirLeg>
            @endforeach
            <air:AirSearchModifiers>
                <air:PreferredProviders>
                    <com:Provider Code="1G" />
                </air:PreferredProviders>
            </air:AirSearchModifiers>
            @for ($i = 0; $i < $adults; $i++)
                <com:SearchPassenger Code="ADT"  BookingTravelerRef="{{ $passengerUuids['adults'][$i] }}" />
            @endfor
            @for ($i = 0; $i < $children; $i++)
                <com:SearchPassenger Code="CNN" Age="08"  BookingTravelerRef="{{ $passengerUuids['children'][$i] }}" />
            @endfor
            @for ($i = 0; $i < $infants; $i++)
                <com:SearchPassenger Code="INF" Age="01"   BookingTravelerRef="{{ $passengerUuids['infants'][$i] }}" />
            @endfor
        </air:LowFareSearchReq>
    </soap:Body>
</soap:Envelope>
