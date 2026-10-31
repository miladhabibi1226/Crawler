<?php

namespace App\TravelServices\Suppliers\Local\Logics\Flight;

trait Allocations
{
    protected function getContent()
    {
        $conditionGroup = [
            'departure' => [
                'date' => $this->object['startDate'],
                'org' => $this->object['startDeparture'],
                'dst' => $this->object['startArrival']
            ],
            'return' => [
                'date' => $this->object['returnDate'],
                'org' => $this->object['returnDeparture'],
                'dst' => $this->object['returnArrival']
            ]
        ];
        foreach ($conditionGroup as $key => $conditions) {
            $allocationsTable = 'flight_allocations';
            $availabilitiesTable = 'flight_availabilities';
            $profilesTable = 'flight_profiles';
            $this->rawResult[$key] = app('db')->table($allocationsTable)
                ->select(app('db')->raw("$allocationsTable.id,$allocationsTable.title,$availabilitiesTable.flight_number,$availabilitiesTable.departure,$allocationsTable.capacity,$profilesTable.alias as flight,orgAirport.abb as origin,dstAirport.abb as destination"))
                ->join($availabilitiesTable, "$availabilitiesTable.id", "$allocationsTable.flight_availability_id")
                ->join($profilesTable, "$profilesTable.id", "$availabilitiesTable.flight_profile_id")
                ->join('airports as orgAirport', "orgAirport.id", "$profilesTable.origin_airport_id")
                ->join('airports as dstAirport', "dstAirport.id", "$profilesTable.destination_airport_id")
                ->whereDate("$availabilitiesTable.departure", $conditions['date'])
                ->where("$profilesTable.origin_airport_id", $conditions['org'])
                ->where("$profilesTable.destination_airport_id", $conditions['dst'])
                ->whereNull("$profilesTable.deleted_at")
                ->whereNull("$availabilitiesTable.deleted_at")
                ->whereNull("$allocationsTable.deleted_at")
                ->get()->toArray();
        }
    }

    protected function done()
    {
        return ['status' => true, 'allocations' => $this->rawResult];
    }
}
