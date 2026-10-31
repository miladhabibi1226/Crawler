{
"OTA_AirLowFareSearchRQ": {
"Version": "2",
"POS": {
"Source": [
{
"PseudoCityCode": "{{ $pcc }}",
"RequestorID": {
"Type": "1",
"ID": "1",
"CompanyName": {
"Code": "TN"
}
}
}
]
},
"Target": "Test",
"OriginDestinationInformation": [
{
"RPH": "1",
"DepartureDateTime": "{{ $departure }}T00:00:00",
"OriginLocation": {
"LocationCode": "{{ $origin['abb'] }}",
"LocationType": "{{ ['false' => 'A', 'true' => 'C'][$origin['isCity']] }}"
},
"DestinationLocation": {
"LocationCode": "{{ $destination['abb'] }}",
"LocationType": "{{ ['false' => 'A', 'true' => 'C'][$destination['isCity']] }}"
},
"TPA_Extensions": {
"CabinPref": {
"Cabin": "Economy"
}
}
}
@if (!empty($return))
    ,
    {
    "RPH": "2",
    "DepartureDateTime": "{{ $return }}T00:00:00",
    "OriginLocation": {
    "LocationCode": "{{ $destination['abb'] }}",
    "LocationType": "{{ ['false' => 'A', 'true' => 'C'][$destination['isCity']] }}"
    },
    "DestinationLocation": {
    "LocationCode": "{{ $origin['abb'] }}",
    "LocationType": "{{ ['false' => 'A', 'true' => 'C'][$origin['isCity']] }}"
    },
    "TPA_Extensions": {
    "CabinPref": {
    "Cabin": "{{ $cabin }}"
    }
    }
    }
@endif
],
"TravelPreferences": {
"TPA_Extensions": {
"NumTrips": {
"Number": 3
},
"DataSources": {
"NDC": "Enable",
"ATPCO": "Enable",
"LCC": "Enable"
},
"PreferNDCSourceOnTie": {
"Value": true
}
},
"ETicketDesired": true
},
"TravelerInfoSummary": {
"AirTravelerAvail": [
{
"PassengerTypeQuantity": [
{
"Code": "ADT",
"Quantity": {{ $adults }}
}
@if (!empty($children))
    ,
    {
    "Code": "CHD",
    "Quantity": {{ $adults }}
    }
@endif
@if (!empty($infants))
    ,{
    "Code": "INF",
    "Quantity": {{ $adults }}
    }

@endif
]
}
]
},
"TPA_Extensions": {
"IntelliSellTransaction": {
"RequestType": {
"Name": "200ITINS"
}
}
}
}
}
