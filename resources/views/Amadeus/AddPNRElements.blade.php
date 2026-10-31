<PNR_AddMultiElements xmlns="{{ $action }}">
    <pnrActions>
        <optionCode>0</optionCode>
    </pnrActions>
    @php
        $counter = 1;
    @endphp
    @foreach ($passengers as $passengerType => $passengerTypeGroup)
        @foreach ($passengerTypeGroup as $key => $passenger)
            @include('Amadeus.sub.travellerInfo', [
                'passenger' => $passenger,
                'passengerType' => ['adults' => 'ADT', 'children' => 'CHD', 'infants' => 'INF'][$passengerType],
                'counter' => $counter,
            ])
            @php
                $counter++;
            @endphp
        @endforeach
    @endforeach
    <dataElementsMaster>
        <marker1 />
        <dataElementsIndiv>
            <elementManagementData>
                <segmentName>SSR</segmentName>
            </elementManagementData>
            <serviceRequest>
                <ssr>
                    <type>CTCE</type>
                    <freetext>{{ str_replace('@', '//', (empty($passengerContactEmail) ? $passengers['adults'][0]['Email'] : $passengerContactEmail)) }}</freetext>
                </ssr>
            </serviceRequest>
        </dataElementsIndiv>
        <dataElementsIndiv>
            <elementManagementData>
                <segmentName>AP</segmentName>
            </elementManagementData>
            <freetextData>
                <freetextDetail>
                    <subjectQualifier>3</subjectQualifier>
                    <type>P21</type>
                </freetextDetail>
                <longFreetext></longFreetext>
            </freetextData>
        </dataElementsIndiv>
        <dataElementsIndiv>
            <elementManagementData>
                <segmentName>TK</segmentName>
            </elementManagementData>
            <ticketElement>
                <ticket>
                    <indicator>OK</indicator>
                </ticket>
            </ticketElement>
        </dataElementsIndiv>
        <dataElementsIndiv>
            <elementManagementData>
                <segmentName>RF</segmentName>
            </elementManagementData>
            <freetextData>
                <freetextDetail>
                    <subjectQualifier>3</subjectQualifier>
                    <type>P22</type>
                </freetextDetail>
                <longFreetext>AWSUI</longFreetext>
            </freetextData>
        </dataElementsIndiv>
    </dataElementsMaster>
</PNR_AddMultiElements>
