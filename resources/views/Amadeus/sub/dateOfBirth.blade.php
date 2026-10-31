
@php
    $date = $passenger['Date of birth'] ?? '';
    if(empty($date)){
        return;
    }
    $birthdate = Carbon\Carbon::create($date)->format('dMy');
@endphp
<dateOfBirth>
    <dateAndTimeDetails>
        <qualifier>706</qualifier>
        <date>{{ $birthdate }}</date>
    </dateAndTimeDetails>
</dateOfBirth>