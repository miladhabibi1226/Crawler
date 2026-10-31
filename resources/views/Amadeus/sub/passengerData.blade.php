<passengerData>
    <travellerInformation>
        <traveller>
            <surname>{{ $passenger['Last_name'] }}</surname>
            @includeWhen($passengerType != 'INF', 'Amadeus.sub.quantity', ['value' => empty($passenger['infantIndicator']) ? 1 : 2])
        </traveller>
        @include('Amadeus.sub.passenger')
        @includeWhen(($passenger['infantIndicator'] ?? null) == 2, 'Amadeus.sub.passenger', ['passenger' => $passenger['accompanyingInfant'] ?? [], 'passengerType' => 'INF'])
    </travellerInformation>
    @includeWhen(in_array($passengerType, ['CHD', 'INF']) || ($passenger['infantIndicator'] ?? null) == 2, 'Amadeus.sub.dateOfBirth', ['passenger' => $passenger['accompanyingInfant'] ?? []])
</passengerData>
