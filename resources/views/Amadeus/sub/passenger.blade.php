<passenger>
    <firstName>{{ $passenger['First_name'] }}</firstName>
    <type>{{ $passengerType }}</type>
    @includeWhen(!empty($passenger['infantIndicator']), 'Amadeus.sub.infantIndicator', ['value' => $passenger['infantIndicator'] ?? 1])
</passenger>