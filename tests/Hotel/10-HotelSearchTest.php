<?php

class HotelSearchTest extends TestCase
{
    public function testRequest()
    {
        $dubai = 623;
        $response = $this->json('get', '1/fake/hotel/search', ['city' => $dubai])
            ->seeJson(['status' => true,])
            ->seeJsonStructure(["sessionId"])
            ->response->getContent();
        self::$data['searchQueryId'] = json_decode($response)->sessionId;
    }

    public function testSearch()
    {
        ///> invalid SearchQuery reference id
        $this->get("1/search/96c44d59b91aa5adc868709")
            ->seeJson(['status' => false,]);

        ///> new SearchQuery reference id
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
        $result = $this->get("1/fake/hotel/results?sessionId=$searchQueryId")
            ->seeStatusCode(200)
            ->response->getcontent();

        $this->assertNotEmpty($result); ///> "[]" is not empty
        self::$data['hotelBufferId'] = json_decode($result, true)[0]['reference_id'];
    }
}
