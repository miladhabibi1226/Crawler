<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Header />
    <soapenv:Body>
        <air:AirFareRulesReq TargetBranch="{{ $targetBranch }}" TraceId="{{ $traceId }}" FareRuleType="short"
            xmlns:air="http://www.travelport.com/schema/air_v52_0"
            xmlns:com="http://www.travelport.com/schema/common_v52_0">
            <com:BillingPointOfSaleInfo OriginApplication="UAPI" xmlns="http://www.travelport.com/schema/common_v52_0" />
            <air:FareRuleKey FareInfoRef="{{ $fareInfo['@attributes']['Key'] }}" ProviderCode="1G">{{ $fareInfo['FareRuleKey'] }}</air:FareRuleKey>
        </air:AirFareRulesReq>
    </soapenv:Body>
</soapenv:Envelope>
