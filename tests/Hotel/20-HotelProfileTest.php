<?php

class HotelProfileTest extends TestCase
{
    public function testDetailes()
    {
        $hotelBufferId = self::$data['hotelBufferId'];
        $this->get("1/aftersearch/hotel/$hotelBufferId/detail")
            ->seeJson(['status' => true,]);
    }

    public function testAvailableRooms()
    {
        ///> expired session
        $this->get("1/aftersearch/hotel/d1aa953bf0c554f2ba3e3741085fc35a5f44c98eaf904/availableRooms")
            ->seeJson(['status' => false,]);

        ///> new search
        $hotelBufferId = self::$data['hotelBufferId'];
        $this->get("1/aftersearch/hotel/$hotelBufferId/availableRooms")
            ->seeJson(['status' => true,]);
    }
}