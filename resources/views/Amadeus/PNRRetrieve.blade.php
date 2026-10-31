<PNR_Retrieve xmlns="{{ $action }}" >
    <retrievalFacts>
        <retrieve>
            <type>2</type>
        </retrieve>
        <reservationOrProfileIdentifier>
            <reservation>
                <companyId>{{$bookingResult['companyId']}}</companyId>
                <controlNumber>{{$bookingResult['controlNumber']}}</controlNumber>
            </reservation>
        </reservationOrProfileIdentifier>
    </retrievalFacts>
</PNR_Retrieve>