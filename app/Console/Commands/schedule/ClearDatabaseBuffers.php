<?php

namespace App\Console\Commands\schedule;

use Carbon\Carbon;
use Illuminate\Console\Command;
use MatinUtils\NodeTracker\Models\EventBuffer;
use MatinUtils\NodeTracker\Models\FlightBuffer;
use MatinUtils\NodeTracker\Models\HotelBuffer;
use MatinUtils\NodeTracker\Models\Node;
use MatinUtils\NodeTracker\Models\PackageBuffer;
use MatinUtils\NodeTracker\Models\ServiceInfo;
use MatinUtils\NodeTracker\Models\TourBuffer;
use MatinUtils\NodeTracker\Models\VisaBuffer;

class ClearDatabaseBuffers extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'clear-buffers';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Clears Old hotel, flight and package buffers';

  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct();
  }

  /**
   * Execute the console command.
   *
   * @return mixed
   */
  public function handle()
  {
    $pid = 'clearBuffers-' . uniqid();
    app('log-system')->setPid($pid);
    // app('log')->info("clearBuffers start $pid");
    $nodeIds = Node::all()->pluck('id');
    foreach ($nodeIds as $id) {
      // app('log')->info("node:  $id");
      switchNode($id);
      $hotelBuffers = FlightBuffer::where('created_at', '<', Carbon::now()->subMinutes(45))->delete();
      if ($hotelBuffers != 0) {
        // app('log')->info("Deleted $hotelBuffers Flight Buffers for Node $id");
        \DB::select("OPTIMIZE TABLE n{$id}_hotel_buffers");
      }

      $flightBuffers = HotelBuffer::where('created_at', '<', Carbon::now()->subMinutes(45))->delete();
      if ($flightBuffers != 0) {
        // app('log')->info("Deleted $flightBuffers hotel Buffers for Node $id");
        \DB::select("OPTIMIZE TABLE n{$id}_hotel_buffers");
      }

      $packageBuffers = PackageBuffer::where('created_at', '<', Carbon::now()->subMinutes(45))->delete();
      if ($packageBuffers != 0) {
        // app('log')->info("Deleted $packageBuffers package Buffers for Node $id");
        \DB::select("OPTIMIZE TABLE n{$id}_package_buffers");
      }

      $TourBuffers = TourBuffer::where('created_at', '<', Carbon::now()->subMinutes(45))->delete();
      if ($TourBuffers != 0) {
        // app('log')->info("Deleted $TourBuffers Tour Bufferss for Node $id");
        \DB::select("OPTIMIZE TABLE n{$id}_Tour_buffers");
      }

      $EventBuffers = EventBuffer::where('created_at', '<', Carbon::now()->subMinutes(45))->delete();
      if ($EventBuffers != 0) {
        // app('log')->info("Deleted $EventBuffers Event Buffers for Node $id");
        \DB::select("OPTIMIZE TABLE n{$id}_Event_buffers");
      }

      $visaBuffers = VisaBuffer::where('created_at', '<', Carbon::now()->subMinutes(45))->delete();
      if ($visaBuffers != 0) {
        // app('log')->info("Deleted $visaBuffers Visa Buffers for Node $id");
        \DB::select("OPTIMIZE TABLE n{$id}_visa_buffers");
      }
    }
    $ServiceInfo = ServiceInfo::where('created_at', '<', Carbon::now()->subMinutes(50))->delete();
    if ($ServiceInfo != 0) {
      // app('log')->info("Deleted $hotelBuffers Flight Buffers for Node $id");
      \DB::select("OPTIMIZE TABLE service_info");
    }
    app('log-system')->send();
    // app('log')->info("clearBuffers end $pid");
  }
}
