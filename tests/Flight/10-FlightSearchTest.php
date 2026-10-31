<?php

use App\Models\Seller;

class FlightSearchTest extends TestCase
{
    public function testRequest()
    {
        switchNode(1);
        $adult = 1;
        $child = 0;
        $infant = 0;
        $tehran = 16269; //thr
        $mashhad = 19116; //mhd
        $month = 01;
        $sepehr = Seller::where('alias','Sepehr')->first()->id;
        $data = [
            'adults' => $adult,
            'children' => $child,
            'infants' => $infant,
            'origin' => $tehran,
            'destination' => $mashhad,
            'month' => $month,
            'seller' => $sepehr
        ];
        $response = $this->json('get', '1/fake/flight/search', $data)
            ->seeJson(['status' => true,])
            ->seeJsonStructure(["sessionId"])
            ->response->getContent();
        self::$data = $data + [
            'searchQueryId' => json_decode($response)->sessionId
        ];
    }

    public function testSearch()
    {
        $searchQueryId = self::$data['searchQueryId'];
        $this->get("1/search/$searchQueryId")
            ->seeJson(['status' => true,]);
    }

    public function testResult()
    {
        $searchQueryId = self::$data['searchQueryId'];
        for ($i = 0; $i < 7; $i++) {
            $response = $this->get("1/fake/progress?sessionId=$searchQueryId")
                ->seeStatusCode(200)
                ->response->getContent();

            $progress = ($response == '{"percent":100}');
            if ($progress) break;
            sleep(5);
        }
        $seller =  self::$data['seller'];
        $result = $this->get("1/fake/flight/result?sessionId=$searchQueryId&supplier=$seller")
            ->seeStatusCode(200)
            ->response->getcontent();
        $this->assertNotEmpty(json_decode($result, true));
        self::$data['flightBufferId'] = json_decode($result, true)[0]['reference_id'];
    }
}
