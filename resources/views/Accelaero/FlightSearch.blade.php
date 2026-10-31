<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Header>
        <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
            soap:mustUnderstand="1">
            <wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" wsu:Id="UsernameToken-17855236">
                <wsse:Username>{{$username}}</wsse:Username>
                <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">{{$password}}</wsse:Password>
            </wsse:UsernameToken>
        </wsse:Security>
    </soap:Header>
    <soap:Body xmlns:ns="http://www.opentravel.org/OTA/2003/05"
        xmlns:ns1="http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05">
        <ns:OTA_AirAvailRQ Version="20061.00" TimeStamp="2021-07-13T17:01:41" Target="Test" SequenceNmbr="1"
            PrimaryLangID="en-us" EchoToken="11868765275150-1300257933">
            <ns:POS>
                <ns:Source>
                    <ns:RequestorID Type="4" ID="{{$username}}" />
                    <ns:BookingChannel Type="12" />
                </ns:Source>
            </ns:POS>
            @foreach ($legs as $leg)
                <ns:OriginDestinationInformation>
                    <ns:DepartureDateTime>{{ $leg['departure'] }}T00:00:00</ns:DepartureDateTime>
                    <ns:OriginLocation LocationCode="{{ $leg['origin']['abb'] }}" />
                    <ns:DestinationLocation LocationCode="{{ $leg['destination']['abb'] }}" />
                    <ns:TravelPreferences>
                        <ns:CabinPref PreferLevel="Preferred"
                            Cabin="{{ ['Economy' => 'Y', 'First' => 'F', 'Business' => 'C'][$cabin] }}" />
                    </ns:TravelPreferences>
                </ns:OriginDestinationInformation>
            @endforeach
            <ns:TravelerInfoSummary>
                <ns:AirTravelerAvail>
                    <ns:PassengerTypeQuantity Code="ADT" Quantity="{{ $adults }}" />
                    @if ($children > 0)
                        <ns:PassengerTypeQuantity Code="CHD" Quantity="{{ $children }}" />
                    @endif
                    @if ($infants > 0)
                        <ns:PassengerTypeQuantity Code="INF" Quantity="{{ $infants }}" />
                    @endif
                </ns:AirTravelerAvail>
            </ns:TravelerInfoSummary>
        </ns:OTA_AirAvailRQ>
    </soap:Body>
</soap:Envelope>
