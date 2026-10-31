<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <soapenv:Header>
        <wsse:Security soapenv:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
            <wsse:UsernameToken wsu:Id="UsernameToken-17855236" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
                <wsse:Username>{{$username}}</wsse:Username>
                <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">{{$password}}</wsse:Password>
            </wsse:UsernameToken>
        </wsse:Security>
    </soapenv:Header>
    <soapenv:Body xmlns:ns1="http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05" xmlns:ns2="http://www.opentravel.org/OTA/2003/05">
        <ns2:AA_OTA_AirBaggageDetailsRQ EchoToken="11868765275150-1300257933" SequenceNmbr="1" Target="Test" TimeStamp="2021-07-12T16:16:55+05:30" Version="20061" TransactionIdentifier="{{$TransactionIdentifier}}">
            <ns2:POS>
                <ns2:Source >
                    <ns2:RequestorID ID="{{$username}}" Type="4" />
                    <ns2:BookingChannel Type="12" />
                </ns2:Source>
            </ns2:POS>
                <ns2:BaggageDetailsRequests>
                      @foreach ($OriginDestinationInformation['OriginDestinationOptions']['OriginDestinationOption'] as $segment )
                         <ns2:BaggageDetailsRequest>
                            <ns2:FlightSegmentInfo ArrivalDateTime="{{$segment['FlightSegment']['@attributes']['ArrivalDateTime']}}" DepartureDateTime="{{$segment['FlightSegment']['@attributes']['DepartureDateTime']}}" FlightNumber="{{$segment['FlightSegment']['@attributes']['FlightNumber']}}" JourneyDuration="{{$segment['FlightSegment']['@attributes']['JourneyDuration'] ?? ''}}" RPH="{{$segment['FlightSegment']['@attributes']['RPH']}}" >
                               <ns2:DepartureAirport LocationCode="{{$segment['FlightSegment']['DepartureAirport']['@attributes']['LocationCode']}}"/>
                               <ns2:ArrivalAirport LocationCode="{{$segment['FlightSegment']['ArrivalAirport']['@attributes']['LocationCode']}}"/>
                               <ns2:OperatingAirline Code="{{$airlineCode}}"/>
                            </ns2:FlightSegmentInfo>
                         </ns2:BaggageDetailsRequest>
                      @endforeach
                </ns2:BaggageDetailsRequests>
        </ns2:AA_OTA_AirBaggageDetailsRQ>
    </soapenv:Body>
</soapenv:Envelope>
