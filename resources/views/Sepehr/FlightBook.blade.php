{
  "UserName": "{{ $username }}",
  "Password": "{{ $password }}",
  "DepartureSegment": {
    "FlightNumber": "{{ $bookingRequestData['FlightNumber'] }}",
    "DepartureDateTime": "{{ $bookingRequestData['FlightDate'] }}",
    "OriginIataCode": "{{ $bookingRequestData['OriginIataCode'] }}",
    "DestinationIataCode": "{{ $bookingRequestData['DestinationIataCode'] }}",
    "FareName": "{{ $bookingRequestData['FareName'] }}",
    "LockId": null
  },
  "ReturningSegment": null,
  "AdultPassengers": [
    @foreach ($passengers['adults'] as $passenger)
      {{ $loop->first ? '' : ',' }}
      {
        "Title": "{{ $passenger['title'] }}",
        "FirstName": "{{ $passenger['First_name'] }}",
        "LastName": "{{ $passenger['Last_name'] }}",
        "BirthDate": "{{ $passenger['Date of birth'] }}"
        @if(!empty($passenger['National Id']))
          ,
          "IranianCartMelli": {
            "CodeMelli": "{{ $passenger['National Id'] }}"
          }
        @endif
        @if($passenger['Passport Number'])
        ,
        "Passport": {
          "Number": "{{ $passenger['Passport Number'] }}",
          "ExpiryDate": "{{ $passenger['Passport Expiry Date'] }}",
          "NationalityCountryCode": "{{ $passenger['CountriesInfo']['Nationality']['abb']}}",
          "PlaceOfIssueCountryCode": "{{ $passenger['CountriesInfo']['Passport Issuing Country']['abb'] }}"
        }
        @endif
      }
    @endforeach
  ],
  @isset($passengers['children'])
  "ChildPassengers": [
    @foreach ($passengers['children'] as $passenger)
    {{ $loop->first ? '' : ',' }}
      {
        "Title": "{{ $passenger['title'] }}",
        "FirstName": "{{ $passenger['First_name'] }}",
        "LastName": "{{ $passenger['Last_name'] }}",
        "BirthDate": "{{ $passenger['Date of birth'] }}"
        @if(!empty($passenger['National Id']))
        ,
        "IranianCartMelli": {
          "CodeMelli": "{{ $passenger['National Id'] }}"
        }
        @endif
        @if(!empty($passenger['Passport Number']))
        ,
        "Passport": {
          "Number": "{{ $passenger['Passport Number'] }}",
          "ExpiryDate": "{{ $passenger['Passport Expiry Date'] }}",
          "NationalityCountryCode": "{{ $passenger['CountriesInfo']['Nationality']['abb'] }}",
          "PlaceOfIssueCountryCode": "{{ $passenger['CountriesInfo']['Passport Issuing Country']['abb'] }}"
        }
        @endif
      }
  @endforeach
  ],
  @endisset
  @isset($passengers['infants'])
  "InfantPassengers": [
    @foreach ($passengers['infants'] as $passenger)
    {{ $loop->first ? '' : ',' }}
      {
        "Title": "{{ $passenger['title'] }}",
        "FirstName": "{{ $passenger['First_name'] }}",
        "LastName": "{{ $passenger['Last_name'] }}",
        "BirthDate": "{{ $passenger['Date of birth'] }}"
        @if(!empty($passenger['National Id']))
        ,
        "IranianCartMelli": {
          "CodeMelli": "{{ $passenger['National Id'] }}"
        }
        @endif
        @if(!empty($passenger['Passport Number']))
        ,
        "Passport": {
          "Number": "{{ $passenger['Passport Number'] }}",
          "ExpiryDate": "{{ $passenger['Passport Expiry Date'] }}",
          "NationalityCountryCode": "{{ $passenger['CountriesInfo']['Nationality']['abb'] }}",
          "PlaceOfIssueCountryCode": "{{ $passenger['CountriesInfo']['Passport Issuing Country']['abb'] }}"
        }
        @endif
      }
    @endforeach
  ],
  @endisset
  "MobileNumber": "{{empty($passengerContactPhone) ? $passengers['adults'][0]['Phone']['countryPhoneCode'] . $passengers['adults'][0]['Phone']['phone'] : $passengerContactPhone }}",
  "Email": "{{ empty($passengerContactEmail) ? $passengers['adults'][0]['Email'] : $passengerContactEmail }}",
  "TotalPayable": {{ $payable }},
  "YourLocalInventoryPnr": "{{ $referenceId }}"
}
