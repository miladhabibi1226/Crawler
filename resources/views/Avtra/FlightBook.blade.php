<OTA_AirBookRQ xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.opentravel.org/OTA/2003/05OTA_AirBookRQ.xsd" EchoToken="50987"
    TimeStamp="{{ $time }}" Target="Test" Version="2.001" SequenceNmbr="1" PrimaryLangID="En-us">
    <POS>
        <Source AirlineVendorID="{{ $airlineCode }}" ISOCountry="{{ $countryCode }}"
            ISOCurrency="{{ $sellerCurrency }}">
        <RequestorID Type="5" ID="{{ $agentId }}" />
        </Source>
    </POS>
    <AirItinerary DirectionInd="{{ count($sellerInfo['bookingRequestData']) === 1 ? 'OneWay' : 'Return' }}">
        <OriginDestinationOptions>
            @foreach ($sellerInfo['bookingRequestData'] as $leg)
                <OriginDestinationOption>
                    @foreach ($leg as $segment)
                        <FlightSegment FlightNumber="{{ $segment['flightNumber'] }}"
                            DepartureDateTime="{{ $segment['departureDateTime'] }}" RPH="{{ $segment['RPH'] }}"
                            ResBookDesigCode="{{ $segment['ResBookDesigCode'] }}">
                            <DepartureAirport LocationCode="{{ $segment['departureAirport'] }}" />
                            <ArrivalAirport LocationCode="{{ $segment['arrivalAirport'] }}" />
                            <OperatingAirline Code="{{ $segment['OperatingAirline'] }}" />
                        </FlightSegment>
                    @endforeach
                </OriginDestinationOption>
            @endforeach
        </OriginDestinationOptions>
    </AirItinerary>
    <PriceInfo>
        <ItinTotalFare>
            <BaseFare CurrencyCode="USD" DecimalPlaces="2" Amount="{{ $price['baseFare'] }}" />
            <TotalFare CurrencyCode="USD" DecimalPlaces="2" Amount="{{ $price['payable'] }}" />
        </ItinTotalFare>
    </PriceInfo>
    <TravelerInfo>
        @foreach ($passengers as $type => $passengerType)
            @php
                $pt = ['adults' => 'ADT', 'children' => 'CHD', 'infants' => 'INF'][$type];
            @endphp
            @foreach ($passengerType as $index => $passenger)
                @php
                    $ParentRPH = '';
                    $gender = strtoupper($passenger['Gender']) == 'MALE' ? 'M' : 'F';
                    $AccompaniedByInfantInd = $passenger['AccompaniedByInfantInd'] ?? false ? 'true' : 'false';
                @endphp
                <AirTraveler BirthDate="{{ $passenger['Date of birth'] }}" PassengerTypeCode="{{ $pt }}"
                    AccompaniedByInfantInd="{{ $AccompaniedByInfantInd }}" Gender="{{ $gender }}"
                    TravelerNationality="{{ $passenger['CountriesInfo']['Nationality']['abb'] }}">
                    <PersonName>
                        <NamePrefix>{{ $passenger['title'] }}</NamePrefix>
                        <GivenName>{{ $passenger['First_name'] }}</GivenName>
                        <Surname>{{ $passenger['Last_name'] }}</Surname>
                    </PersonName>
                    @if ($type == 'infants')
                        <TravelerRefNumber RPH="{{ $passenger['rph'] }}" ParentRPH="{{ $passenger['parentRPH'] }}" />
                    @else
                        <TravelerRefNumber RPH="{{ $passenger['rph'] }}" />
                    @endif
                    @if (!$sellerInfo['isDomesticFlight'])
                        <Document DocID="{{ $passenger['Passport Number'] }}" DocType="2"
                            ExpireDate="{{ $passenger['Passport Expiry Date'] }}"
                            DocIssueCountry="{{ $passenger['CountriesInfo']['Passport Issuing Country']['abb'] }}"
                            DocHolderNationality="{{ $passenger['CountriesInfo']['Nationality']['abb'] }}" />
                    @endif
                </AirTraveler>
            @endforeach
        @endforeach
    </TravelerInfo>
    <ContactPerson>
        <PersonName>
            <GivenName>{{ $passengers['adults'][0]['First_name'] }}</GivenName>
            <Surname>{{ $passengers['adults'][0]['Last_name'] }}</Surname>
        </PersonName>
        <Telephone
            PhoneNumber="{{$passengerContactPhone ?? '(' . str_replace('+', '', $passengers['adults'][0]['Phone']['countryPhoneCode']) . ')' . $passengers['adults'][0]['Phone']['phone'] }}" />
        <Email>{{$passengerContactEmail ?? $passengers['adults'][0]['Email'] }}</Email>
    </ContactPerson>
    @if (($sellerInfo['bookingType'] ?? 'bookOnly') == 'bookOnly')
        <Ticketing TicketingStatus="1" />
    @else
        <Fulfillment>
            <PaymentDetails>
                <PaymentDetail PaymentType="2">
                    <DirectBill DirectBill_ID="{{ $agentId }}">
                        <CompanyName CompanyShortName="{{ $companyShortName }}" Code="{{ $agentId }}" />
                    </DirectBill>
                    <PaymentAmount CurrencyCode="{{ $sellerCurrency }}" DecimalPlaces="2"
                        Amount="{{ $price['payable'] }}" />
                </PaymentDetail>
            </PaymentDetails>
        </Fulfillment>
    @endif

</OTA_AirBookRQ>
