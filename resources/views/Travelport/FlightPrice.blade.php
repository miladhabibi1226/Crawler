<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <air:AirPriceReq TargetBranch="{{ $targetBranch }}" TraceId="{{ $traceId }}"
            xmlns:air="http://www.travelport.com/schema/air_v52_0">
            <com:BillingPointOfSaleInfo OriginApplication="UAPI"
                xmlns:com="http://www.travelport.com/schema/common_v52_0" />
            <air:AirItinerary>
                @foreach ($legs as $leg)
                    @foreach ($leg['segments'] as $segIndex => $segment)
                        <air:AirSegment Key="{{ $segment['@attributes']['Key'] }}"
                            Group="{{ $segment['@attributes']['Group'] }}"
                            Carrier="{{ $segment['@attributes']['Carrier'] }}"
                            FlightNumber="{{ $segment['@attributes']['FlightNumber'] }}"
                            Origin="{{ $segment['@attributes']['Origin'] }}"
                            Destination="{{ $segment['@attributes']['Destination'] }}"
                            DepartureTime="{{ $segment['@attributes']['DepartureTime'] }}"
                            ArrivalTime="{{ $segment['@attributes']['ArrivalTime'] }}"
                            FlightTime="{{ $segment['@attributes']['FlightTime'] }}"
                            Distance="{{ $segment['@attributes']['Distance'] ?? '' }}"
                            ETicketability="{{ $segment['@attributes']['ETicketability'] ?? '' }}"
                            Equipment="{{ $segment['@attributes']['Equipment'] ?? '' }}"
                            ChangeOfPlane="{{ $segment['@attributes']['ChangeOfPlane'] ?? '' }}"
                            ParticipantLevel="{{ $segment['@attributes']['ParticipantLevel'] ?? '' }}"
                            PolledAvailabilityOption="{{ $segment['@attributes']['PolledAvailabilityOption'] ?? '' }}"
                            OptionalServicesIndicator="{{ $segment['@attributes']['OptionalServicesIndicator'] ?? '' }}"
                            AvailabilitySource="{{ $segment['@attributes']['AvailabilitySource'] ?? '' }}"
                            AvailabilityDisplayType="{{ $segment['@attributes']['AvailabilityDisplayType'] ?? '' }}"
                            ProviderCode="1G"
                        @if ($segIndex < count($leg['segments']) - 1)
                            >
                            <air:Connection />
                            </air:AirSegment>
                        @else
                            />
                        @endif
                    @endforeach
                @endforeach
            </air:AirItinerary>

            <air:AirPricingModifiers />
            @php
            use Illuminate\Support\Carbon;
            @endphp

            @for ($i = 0; $i < $queryParams['adults']; $i++)
                <com:SearchPassenger Key="{{ $passengerUuids['adults'][$i] }}"  Code="ADT"
                    xmlns:com="http://www.travelport.com/schema/common_v52_0" />
            @endfor
            @for ($i = 0; $i < $queryParams['children']; $i++)
                @php
                    $cAge = Carbon::parse($children[$i]['Date of birth'])->age;
                    $cAge = str_pad($cAge, 2, '0', STR_PAD_LEFT);
                @endphp
                <com:SearchPassenger Key="{{ $passengerUuids['children'][$i] }}" Code="CNN" Age="{{ $cAge }}"
                    xmlns:com="http://www.travelport.com/schema/common_v52_0" />
            @endfor
            @for ($i = 0; $i < $queryParams['infants']; $i++)
               @php
                    $iAge = Carbon::parse($infants[$i]['Date of birth'])->age;
                    $iAge = str_pad($iAge, 2, '0', STR_PAD_LEFT);
                @endphp
                <com:SearchPassenger Key="{{ $passengerUuids['infants'][$i] }}"  Code="INF" Age="{{ $iAge }}"
                    xmlns:com="http://www.travelport.com/schema/common_v52_0" />
            @endfor
            <air:AirPricingCommand />
        </air:AirPriceReq>
    </soap:Body>
</soap:Envelope>
