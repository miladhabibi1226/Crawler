<Ticket_CreateTSTFromPricing xmlns="{{ $action }}" >
    @foreach ($passengersCount as $passengersType => $count)
        @if ($count > 0)
        <psaList>
            <itemReference>
                <referenceType>TST</referenceType>
                <uniqueReference>{{ $loop->iteration }}</uniqueReference>
            </itemReference>
        </psaList>
        @endif
    @endforeach
</Ticket_CreateTSTFromPricing>