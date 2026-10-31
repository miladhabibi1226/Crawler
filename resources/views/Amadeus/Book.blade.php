<PNR_AddMultiElements xmlns="{{ $action }}">
    <pnrActions>
        <optionCode>10</optionCode>
        <optionCode>30</optionCode>
    </pnrActions>
    @if($percentage > 0)
    <dataElementsMaster>
        <marker1 />
        <dataElementsIndiv>
            <elementManagementData>
                <reference>
                    <qualifier>OT</qualifier>
                    <number>20</number>
                </reference>
                <segmentName>FM</segmentName>
            </elementManagementData>
            <commission>
                <indicator>M</indicator>
                <commissionInfo>
                    <percentage>{{ $percentage }}</percentage>
                </commissionInfo>
            </commission>
        </dataElementsIndiv>
    </dataElementsMaster>
    @endIf
</PNR_AddMultiElements>
