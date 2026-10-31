<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <soap:Header>
        <wsse:Security soap:mustUnderstand="1"
            xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
            <wsse:UsernameToken wsu:Id="UsernameToken-32124385"
                xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
                <wsse:Username>{{ $username }}</wsse:Username>
                <wsse:Password
                    Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">{{$password}}</wsse:Password>
            </wsse:UsernameToken>
        </wsse:Security>
    </soap:Header>
    <soap:Body xmlns:ns1="http://www.opentravel.org/OTA/2003/05">
        <ns1:OTA_AirPriceRQ EchoToken="12662148060105253838426" PrimaryLangID="en-us" SequenceNmbr="1"
            TimeStamp="2010-02-15T10:20:06" Version="20061.00">
            <ns1:POS>
                <ns1:Source TerminalID="TestUser/Test Runner">
                    <ns1:RequestorID ID="{{ $username }}" Type="4" />
                    <ns1:BookingChannel Type="12" />
                </ns1:Source>
            </ns1:POS>
            <ns1:AirItinerary DirectionInd="{{ $tripType }}">
                @if (empty($legs))
                    {!! $rawODO !!}
                @elseif ($tripType == 'MultiCity')
                    <ns1:OriginDestinationOptions>
                        @foreach ($legs as $segments)
                                @foreach ($segments as $index => $segment)
                                <ns1:OriginDestinationOption>
                                    <ns1:FlightSegment ArrivalDateTime="{{ $segment['arrivalDateTimeLocal'] }}"
                                        DepartureDateTime="{{ $segment['departureDateTimeLocal'] }}"
                                        FlightNumber="{{ $segment['flightNumber'] }}" ResCabinClass="{{['Economy' => 'Y', 'First' => 'F', 'Business' => 'C'][$queryParams['cabin']]}}">
                                        <ns1:DepartureAirport LocationCode="{{ $segment['origin']['airportCode'] }}" />
                                        <ns1:ArrivalAirport
                                            LocationCode="{{ $segment['destination']['airportCode'] }}" />
                                        <ns1:OperatingAirline Code="{{ substr($segment['flightNumber'], 0, 2) }}" />
                                    </ns1:FlightSegment>
                                </ns1:OriginDestinationOption>
                                @endforeach
                        @endforeach
                    </ns1:OriginDestinationOptions>
                @else
                    <ns1:OriginDestinationOptions>
                        @foreach ($legs as $segments)
                            <ns1:OriginDestinationOption>
                                @foreach ($segments as $index => $segment)
                                    <ns1:FlightSegment ArrivalDateTime="{{ $segment['arrivalDateTimeLocal'] }}"
                                        DepartureDateTime="{{ $segment['departureDateTimeLocal'] }}"
                                        FlightNumber="{{ $segment['flightNumber'] }}" ResCabinClass="{{['Economy' => 'Y', 'First' => 'F', 'Business' => 'C'][$queryParams['cabin']]}}">
                                        <ns1:DepartureAirport LocationCode="{{ $segment['origin']['airportCode'] }}" />
                                        <ns1:ArrivalAirport
                                            LocationCode="{{ $segment['destination']['airportCode'] }}" />
                                        <ns1:OperatingAirline Code="{{ substr($segment['flightNumber'], 0, 2) }}" />
                                    </ns1:FlightSegment>
                                @endforeach
                            </ns1:OriginDestinationOption>
                        @endforeach
                    </ns1:OriginDestinationOptions>
                @endif
            </ns1:AirItinerary>
            <ns1:TravelerInfoSummary>
                <ns1:AirTravelerAvail>
                    <ns1:PassengerTypeQuantity Code="ADT" Quantity="{{ $queryParams['adults'] }}" />
                    @if ($queryParams['children'] > 0)
                        <ns1:PassengerTypeQuantity Code="CHD" Quantity="{{ $queryParams['children'] }}" />
                    @endif
                    @if ($queryParams['infants'] > 0)
                        <ns1:PassengerTypeQuantity Code="INF" Quantity="{{ $queryParams['infants'] }}" />
                    @endif
                </ns1:AirTravelerAvail>
                @if (!empty($baggageResult['requestData']))
                  <ns1:SpecialReqDetails>
                     <ns1:BaggageRequests>
                        @foreach ($baggageResult['requestData'] as $segment )
                            <ns1:BaggageRequest baggageCode="{{$segment['baggageCode']}}" TravelerRefNumberRPHList="{{$segment['TravelerRefNumber']}}" FlightRefNumberRPHList="{{$segment['FlightRPH']}}" DepartureDate="{{$segment['Departure']}}" FlightNumber="{{$segment['FlightNumber']}}"/>
                        @endforeach
                     </ns1:BaggageRequests>
                     <ns1:SSRRequests></ns1:SSRRequests>
                  </ns1:SpecialReqDetails>
                @else
                  <ns1:SpecialReqDetails d3p1:nil="true" xmlns:d3p1="http://www.w3.org/2001/XMLSchema-instance" />
                @endif
            </ns1:TravelerInfoSummary>
        </ns1:OTA_AirPriceRQ>
    </soap:Body>
</soap:Envelope>
