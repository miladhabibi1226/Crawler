<?php

use App\Models\Booking;

class FlightBookTest extends TestCase
{
    public function testRequest()
    {
        $flightBufferId = self::$data['flightBufferId'];
        $response = $this->get("1/fake/flight/book?referenceId=$flightBufferId")
            ->seeStatusCode(200)
            ->response->getcontent();
        self::$data['flightBookingId'] = json_decode($response, true)['reference_id'];
    }

    public function testPassengersForm()
    {
        $flightBookingId = self::$data['flightBookingId'];
        $response = $this->get("1/validate/form/$flightBookingId")
            ->seeStatusCode(200)
            ->seeJson(['status' => true,])
            ->response->getcontent();
    }

    public function testPassengersValiadtion()
    {
        switchNode(1);
        $flightBookingId = self::$data['flightBookingId'];
        $booking = Booking::where('reference_id', $flightBookingId)->first();
        $passengersForm = $booking->seller_info['passengersForm'];
        for ($i = 1; $i <= self::$data['adults']; $i++) {
            $adult = [];
            foreach ($passengersForm['all'] as $key => $value) {
                $adult[$key] = str_replace('_',' ',$value['caption']);
                if ($key == 'phone') {
                    $adult[$key] = '9111111111';
                }
                if ($key == 'birthday') {
                    $adult[$key] = '1998-01-0'.$i;
                }
                if ($key == 'email') {
                    $adult[$key] = 'test@test.test';
                }
                if ($key == 'title') {
                    $child[$key] = 'MR';
                }
            }
            $adults[$i] = $adult;
        }

        $adult = [];
        foreach ($passengersForm['lead'] as $key => $value) {
            $adult[$key] = $value['caption'];
            if ($key == 'phone') {
                $adult[$key] = '9111111111';
            }
            if ($key == 'email') {
                $adult[$key] = 'test@test.test';
            }
            if ($key == 'title') {
                $child[$key] = 'MR';
            }
        }
        $adults[1] = $adults[1] + $adult;

        for ($i = 1; $i <= self::$data['children']; $i++) {
            $child = [];
            foreach ($passengersForm['all'] as $key => $value) {
                $child[$key] = $value['caption'];
                if ($key == 'phone') {
                    $child[$key] = '9111111111';
                }
                if ($key == 'birthday') {
                    $adult[$key] = '2012-01-0'.$i;
                }
                if ($key == 'email') {
                    $adult[$key] = 'test@test.test';
                }
                if ($key == 'title') {
                    $child[$key] = 'MR';
                }
            }
            $children[$i] = $child;
        }
        for ($i = 1; $i <= self::$data['infants']; $i++) {
            $infant = [];
            foreach ($passengersForm['all'] as $key => $value) {
                $infant[$key] = $value['caption'];
                if ($key == 'phone') {
                    $infant[$key] = '9111111111';
                }
                if ($key == 'birthday') {
                    $adult[$key] = '2021-01-0'.$i;
                }
                if ($key == 'email') {
                    $adult[$key] = 'test@test.test';
                }
                if ($key == 'title') {
                    $child[$key] = 'MR';
                }
            }
            $infants[$i] = $infant;
        }

        $passengersInfo['adults'] = $adults;
        if (count($children??[]) > 0) {
            $passengersInfo['children'] = $adults;
        }
        if (count($infants ?? []) > 0) {
            $passengersInfo['infants'] = $adults;
        }

        $booking->passenger_info = $passengersInfo;
        $booking->update();
        $response = $this->get("1/validate/passengers/$flightBookingId")
            ->seeStatusCode(200)
            ->seeJson(['status' => true,])
            ->response->getcontent();
        
            $booking->status = 'book';
            $booking->update();
    }

    public function testBookAndIssue()
    {
        // dump('BookAndIssue');
        // $flightBookingId = self::$data['flightBookingId'];
        // $this->get("1/bookAndIssue/$flightBookingId?sync=?")
        //     ->seeStatusCode(200)
        //     ->seeJson(['status' => true,]);
    }
}
