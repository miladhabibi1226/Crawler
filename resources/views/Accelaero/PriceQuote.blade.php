<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <soap:Header>
        <wsse:Security soap:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
            <wsse:UsernameToken wsu:Id="UsernameToken-17855236" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
                <wsse:Username>{{$username}}</wsse:Username>
                <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">{{$password}}</wsse:Password>
            </wsse:UsernameToken>
        </wsse:Security>
    </soap:Header>
    <soap:Body xmlns:ns2="http://www.opentravel.org/OTA/2003/05">
        <ns2:OTA_AirPriceRQ EchoToken="11868765275150-1300257933" SequenceNmbr="1" Target="Test" TimeStamp="2021-07-12T16:16:55+05:30" Version="20061" TransactionIdentifier="{{$TransactionIdentifier}}">
            <ns2:POS>
                <ns2:Source >
                    <ns2:RequestorID ID="{{$username}}" Type="4" />
                    <ns2:BookingChannel Type="12" />
                </ns2:Source>
            </ns2:POS>
            <ns2:AirItinerary DirectionInd="{{$tripType}}">
                @if(empty($OriginDestinationOptions))
                    {!!$rawODO!!}
                @else
                <ns2:OriginDestinationOptions>
                      @foreach ($OriginDestinationOptions['OriginDestinationOption'] as $segment )
                         <ns2:OriginDestinationOption>
                            <ns2:FlightSegment ArrivalDateTime="{{$segment['FlightSegment']['@attributes']['ArrivalDateTime']}}" DepartureDateTime="{{$segment['FlightSegment']['@attributes']['DepartureDateTime']}}" FlightNumber="{{$segment['FlightSegment']['@attributes']['FlightNumber']}}" JourneyDuration="{{$segment['FlightSegment']['@attributes']['JourneyDuration']}}" RPH="{{$segment['FlightSegment']['@attributes']['RPH']}}" ResCabinClass="{{['Economy' => 'Y', 'First' => 'F', 'Business' => 'C'][$queryParams['cabin']]}}" >
                               <ns2:DepartureAirport LocationCode="{{$segment['FlightSegment']['DepartureAirport']['@attributes']['LocationCode']}}"/>
                               <ns2:ArrivalAirport LocationCode="{{$segment['FlightSegment']['ArrivalAirport']['@attributes']['LocationCode']}}"/>
                            </ns2:FlightSegment>
                         </ns2:OriginDestinationOption>                    
                      @endforeach
                </ns2:OriginDestinationOptions>
                @endif
            </ns2:AirItinerary>
            <ns2:TravelerInfoSummary>
                <ns2:AirTravelerAvail>
                    <ns2:PassengerTypeQuantity  Code="ADT" Quantity="{{$queryParams['adults']}}"/>
                    @if ($queryParams['children']>0)
                    <ns2:PassengerTypeQuantity  Code="CHD" Quantity="{{$queryParams['children']}}" />
                    @endif
                    @if ($queryParams['infants']>0)
                    <ns2:PassengerTypeQuantity  Code="INF" Quantity="{{$queryParams['infants']}}" />
                    @endif
                </ns2:AirTravelerAvail>
                @if (!empty($baggageResult['requestData']))
                    <ns2:SpecialReqDetails>
                        <ns2:BaggageRequests> 
                        @foreach ($baggageResult['requestData'] as $segment )
                            <ns2:BaggageRequest baggageCode="{{$segment['baggageCode']}}" TravelerRefNumberRPHList="{{$segment['TravelerRefNumber']}}" FlightRefNumberRPHList="{{$segment['FlightRPH']}}" DepartureDate="{{$segment['Departure']}}" FlightNumber="{{$segment['FlightNumber']}}"/>
                        @endforeach
                        </ns2:BaggageRequests>
                    </ns2:SpecialReqDetails>
                @else
                <ns2:SpecialReqDetails d3p1:nil="true" xmlns:d3p1="http://www.w3.org/2001/XMLSchema-instance" />
                @endif
            </ns2:TravelerInfoSummary>
            <ns2:OriginDestinationOptions d2p1:nil="true" xmlns:d2p1="http://www.w3.org/2001/XMLSchema-instance" />
        </ns2:OTA_AirPriceRQ>
    </soap:Body>
</soap:Envelope>