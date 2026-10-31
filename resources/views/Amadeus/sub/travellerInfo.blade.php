<travellerInfo>
    <elementManagementPassenger>
        <reference>
            <qualifier>PR</qualifier>
            <number>{{ $counter }}</number>
        </reference>
        <segmentName>NM</segmentName>
    </elementManagementPassenger>
    @include('Amadeus.sub.passengerData')
    @includeWhen(($passenger['infantIndicator'] ?? null) == 3, 'Amadeus.sub.passengerData', ['passenger' => ($passenger['accompanyingInfant'] ?? []), 'passengerType'=>'INF'])
</travellerInfo>