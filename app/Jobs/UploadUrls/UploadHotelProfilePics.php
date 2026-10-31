<?php

namespace App\Jobs\UploadUrls;

use App\Jobs\Job;
use App\Models\CommonHotelProfile;

class UploadHotelProfilePics extends Job
{
    protected $hotelProfileId, $nodeId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($hotelProfileId)
    {
        $this->hotelProfileId = $hotelProfileId;
        $this->nodeId = node()->id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function operate()
    {
        switchNode($this->nodeId);
        // lugInfo('hotel_id: ' . $this->hotelProfileId);
        $hotelProfile = CommonHotelProfile::find($this->hotelProfileId);
        if (empty($hotelProfile->images)) {
            lugInfo('Property [images] does not exist on this collection instance', [$hotelProfile, $this->hotelProfileId]);
            app('log')->info('Property [images] does not exist on this collection instance ' . app('log-system')->getPid());
            return;
        }
        $urls = $hotelProfile->images;
        $count = count($urls);
        // lugInfo("$count Image urls found.", ['hotelProfile' => $hotelProfile, 'requestedUrlArray' => $urls]);
        foreach (array_chunk($urls, 15, true) as $key => $chunkedUrls) {
            lugInfo("$key - Dispatching 'ChunkUpload'job", ['chunk-count' => count($chunkedUrls), 'urls' => $urls]);
            dispatch(new ChunkUpload($chunkedUrls, $hotelProfile));
        }
    }
}
