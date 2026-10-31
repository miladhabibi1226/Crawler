{
    "UserName": "{{ $username }}",
    "Password": "{{ $password }}",
    "OriginIataCode": "{{ $legs[0]['origin']['abb'] }}",
    "DestinationIataCode": "{{ $legs[0]['destination']['abb'] }}",
    "DepartureDate": "{{$legs[0]['departure']}}",
    "DepartureDateWindow": 0,
    "FetchSupplierWebserviceFlights": true,
    "Language": "EN"
}