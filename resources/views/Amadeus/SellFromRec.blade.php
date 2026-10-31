<Air_SellFromRecommendation xmlns="{{ $action }}">
    <messageActionDetails>
        <messageFunctionDetails>
            <messageFunction>183</messageFunction>
            <additionalMessageFunction>M1</additionalMessageFunction>
        </messageFunctionDetails>
    </messageActionDetails>
    @foreach ($legs as $legIndex => $leg)
        <itineraryDetails>
            <originDestinationDetails>
                <origin>{{ $leg['info']['org'] }}</origin>
                <destination>{{ $leg['info']['dst'] }}</destination>
            </originDestinationDetails>
            <message>
                <messageFunctionDetails>
                    <messageFunction>183</messageFunction>
                </messageFunctionDetails>
            </message>
            @foreach ($leg['segments'] as $segIndex => $segment)
                <segmentInformation>
                    <travelProductInformation>
                        <flightDate>
                            <departureDate>{{ $rawTimes[$legIndex][$segIndex]['dateOfDeparture'] }}</departureDate>
                        </flightDate>
                        <boardPointDetails>
                            <trueLocationId>{{ $segment['departure']['airport']['abb'] }}</trueLocationId>
                        </boardPointDetails>
                        <offpointDetails>
                            <trueLocationId>{{ $segment['arrival']['airport']['abb'] }}</trueLocationId>
                        </offpointDetails>
                        <companyDetails>
                            <marketingCompany>{{ $segment['airline']['supplier_key'] }}</marketingCompany>
                        </companyDetails>
                        <flightIdentification>
                            <flightNumber>{{ $segment['flight_number'] }}</flightNumber>
                            <bookingClass>{{ $segment['resBookDesigCode'] }}</bookingClass>
                        </flightIdentification>
                    </travelProductInformation>
                    <relatedproductInformation>
                        <quantity>{{ $adults + ($children ?? 0) }}</quantity>
                        <statusCode>NN</statusCode>
                    </relatedproductInformation>
                </segmentInformation>
            @endforeach
        </itineraryDetails>
    @endforeach
</Air_SellFromRecommendation>
