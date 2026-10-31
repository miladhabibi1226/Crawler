<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
   <soap:Header>
      <wsse:Security soap:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
         <wsse:UsernameToken wsu:Id="UsernameToken22326874" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
            <wsse:Username>{{$username}}</wsse:Username>
            <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">{{$password}}</wsse:Password>
         </wsse:UsernameToken>
      </wsse:Security>
   </soap:Header>
   <soap:Body xmlns:ns1="http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05" xmlns:ns2="http://www.opentravel.org/OTA/2003/05">
      <ns2:OTA_AirBookRQ EchoToken="11835513596097885191423" PrimaryLangID="en-us" SequenceNmbr="1" TimeStamp="2012-08-27T03:01:41" Version="20061.00" TransactionIdentifier="{{$TransactionIdentifier}}">
         <ns2:POS>
            <ns2:Source>
               <ns2:RequestorID ID="{{$username}}" Type="4"/>
               <ns2:BookingChannel Type="12"/>
            </ns2:Source>
         </ns2:POS>
         <ns2:AirItinerary>
            {!!$rawODO!!}
         </ns2:AirItinerary>
         <ns2:TravelerInfo>
            @php
               $counter = $adultWithInfant = 1;
            @endphp
             @foreach ($passengers as $type => $passengerTypeGroup)
                @foreach ($passengerTypeGroup as $passenger)
                     @php
                        $rph = ['adults'=>'A','children'=>'C','infants'=>'I'][$type].$counter;
                        if ($type == 'infants') {
                           $rph = "$rph/A$adultWithInfant";
                           $adultWithInfant += 1;
                        }
                     @endphp
                    <ns2:AirTraveler BirthDate="{{$passenger['Date of birth']}}T00:00:00" PassengerTypeCode="{{['adults'=>'ADT','children'=>'CHD','infants'=>'INF'][$type]}}">
                        <ns2:PersonName>
                            <ns2:GivenName>{{$passenger['First_name']}}</ns2:GivenName>
                            <ns2:Surname>{{$passenger['Last_name']}}</ns2:Surname>
                            <ns2:NameTitle>{{$passenger['title']}}</ns2:NameTitle>
                        </ns2:PersonName>
                        <ns2:Document DocHolderNationality="{{$passenger['CountriesInfo']['Nationality']['abb']}}" DocType="PSPT" DocID="{{$passenger['Passport Number']}}" DocIssueCountry="{{$passenger['CountriesInfo']['Passport Issuing Country']['abb']}}" ExpireDate="{{$passenger['Passport Expiry Date']}}"/>
                            <ns2:DocHolderName>{{$passenger['First_name'] . ' ' . $passenger['Last_name']}}</ns2:DocHolderName>
                        <ns2:TravelerRefNumber RPH="{{$rph}}"/>
                    </ns2:AirTraveler>
                    @php
                        $counter ++;
                    @endphp
                @endforeach
             @endforeach
         </ns2:TravelerInfo>
         <ns2:Fulfillment>
            <ns2:PaymentDetails>
               <ns2:PaymentDetail>
                  <ns2:DirectBill>
                     <ns2:CompanyName Code="{{$GSA_ID}}"/>
                  </ns2:DirectBill>
                  <ns2:PaymentAmount Amount="{{$payable}}" CurrencyCode="{{$currency}}" DecimalPlaces="2"/>
               </ns2:PaymentDetail>
            </ns2:PaymentDetails>
         </ns2:Fulfillment>
      </ns2:OTA_AirBookRQ>
      <AAAirBookRQExt xmlns="http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05">
         <ContactInfo>
            <PersonName>
               <Title>{{strtoupper($passengers['adults'][0]['title'])}}</Title>
               <FirstName>{{$passengers['adults'][0]['First_name']}}</FirstName>
               <LastName>{{$passengers['adults'][0]['Last_name']}}</LastName>
            </PersonName>
            <Mobile>
               <CountryCode>{{empty($passengerContactPhoneCode) ? $passengers['adults'][0]['Phone']['countryPhoneCode'] : $passengerContactPhoneCode}}</CountryCode>
               <PhoneNumber>{{empty($passengerContactPhoneNumber) ? $passengers['adults'][0]['Phone']['phone'] : $passengerContactPhoneNumber}}</PhoneNumber>
            </Mobile>
            <Telephone>
               <CountryCode>{{empty($passengerContactPhoneCode) ? $passengers['adults'][0]['Phone']['countryPhoneCode'] : $passengerContactPhoneCode}}</CountryCode>
               <PhoneNumber>{{empty($passengerContactPhoneNumber) ? $passengers['adults'][0]['Phone']['phone'] : $passengerContactPhoneNumber}}</PhoneNumber>
            </Telephone>
            <Email>{{empty($passengerContactEmail) ? $passengers['adults'][0]['Email'] : $passengerContactEmail}}</Email>
            <Address>
               <CountryName>
                  <CountryCode>{{$passengers['adults'][0]['cityInfo']['country']['abb']}}</CountryCode>
               </CountryName>
               <ns1:CityName>{{$passengers['adults'][0]['cityInfo']['abb'] ?? $passengers['adults'][0]['cityInfo']['en']}}</ns1:CityName>
               <ns1:AddressLine1>{{$passengers['adults'][0]['cityInfo']['en'] ?? $passengers['adults'][0]['cityInfo']['abb']}}</ns1:AddressLine1>
            </Address>
         </ContactInfo>
      </AAAirBookRQExt>
   </soap:Body>
</soap:Envelope>
