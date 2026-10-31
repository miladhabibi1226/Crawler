<?php

namespace App\Console\Commands\Search\Create;

use App\Models\SearchQuery;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use MatinUtils\NodeTracker\Models\AirportBuffer;
use MatinUtils\NodeTracker\Models\Buyer;

class Flight extends Command
{
    use Helpers;
    protected $signature = 'createSearch:flight {nodeId? : NodeId}';

    public function handle()
    {
        $this->handleNode();
        $data = $this->collectData();
        foreach ($data as $key => $value) {
            $array[] = [$key, $value];
        }
        $this->table(['item', 'value'], $array);
        if (!$this->confirm('Pllease confirm the search parameters', 1)) {
            $this->error('aborted.');
            return;
        }
        $searchQuery = $this->createSearchQuery($data);
        $this->info("Seession Id $searchQuery->session_id");
        $this->findSellers($searchQuery);
    }

    protected function createSearchQuery($data)
    {
        $buyer = Buyer::find($this->extractId($data['buyer']));
        $origin = AirportBuffer::find($this->extractId($data['origin']));
        $destination = AirportBuffer::find($this->extractId($data['destination']));
        $searchQuery = SearchQuery::create([
            'session_id' => createReferenceId(),
            'type' => 'flight',
            'searcher_identity' => $searcherIdentity ?? null,
            'buyer_group_id' => $buyer->buyer_group_id,
            'query_params' => [
                'departure' => $data['departure'],
                'return' => $data['return'],
                'cabin' => 'economy',
                'adults' => $data['adults'],
                'children' => $data['children'],
                'infants' => $data['infants'],
                'origin' => [
                    'city' => $origin->city->toarray(),
                    'airport' => empty($origin->airport) ? null : $origin->airport->toarray(),
                    'buffer' => $origin
                ],
                'destination' => [
                    'city' => $destination->city->toarray(),
                    'airport' => empty($destination->airport) ? null : $destination->airport->toarray(),
                    'buffer' => $destination
                ],
                'buyer_id' => $buyer->id,
                'charterOnly' => 'false',
                'marketplace_currency' => nodeCurrency(),
                'buyer_currency' => nodeCurrency(),
                'current_lang' => 'en',
            ],
        ]);
        return $searchQuery;
    }

    protected function collectData()
    {
        $tripType = $this->choice("chose trip type", ['oneWay', 'roundTrip'], 0);
        $buyer = $this->choseBuyer();
        $origin = $this->choseAirport('origin');
        $destination = $this->choseAirport('destination');
        $adults = $this->passengers('adults', 1, 9);
        $children = $this->passengers('children', 0, 9 - $adults);
        $infants = $this->passengers('infants', 0, $adults);
        $departure = $this->choseDate('departure');
        $return = $tripType == 'oneWay' ? null : $this->choseDate('return');
        $data = compact('buyer', 'origin', 'destination', 'adults', 'children', 'infants', 'tripType', 'departure', 'return');
        return $data;
    }

    protected function choseDate($type, $min = 'today')
    {
        $min = $min == 'today' ? Carbon::today() : Carbon::create($min);
        $def = $type == 'departure' ? Carbon::today()->addWeeks(3)->format('Y-m-d') : Carbon::today()->addWeeks(4)->format('Y-m-d');
        do {
            $value = $this->ask("Please enter the $type date", $def);
            preg_match("/\d{4}+-+\d{2}+-+\d{2}/", $value, $d);
            if (empty($d[0])) {
                $this->error("value '$value' format is invalid");
                continue;
            }
            try {
                $date = Carbon::create($value);
            } catch (\Throwable $th) {
                $this->error("value '$value' is invalid");
                continue;
            }
            if ($date < $min) {
                $this->error("$type value '$value' must be a date after $min");
                continue;
            }
            if ($date > Carbon::today()->addYear()) {
                $this->error("$type value '$value' must be a date before " . Carbon::today()->addYear()->format('Y-m-d'));
                continue;
            }
            return $value;
        } while (true);
    }

    protected function passengers($type, $min, $max)
    {
        do {
            $value = $this->ask("Please enter the $type Number", $min);
            if (preg_match('/\D/', $value, $output_array)) {
                $this->error("value '$value' is invalid");
                continue;
            }
            if ($value < $min) {
                $this->error("$type value '$value' should be more than $min");
                continue;
            }
            if ($value > $max) {
                $this->error("$type value '$value' should be less than $max");
                continue;
            }
            return $value;
        } while (true);
    }

    protected function choseAirport($type)
    {
        do {
            $value = $this->ask("Please enter the $type");
            if (empty($value)) {
                continue;
            }
            $conditions = [['language', ':', ['en']]];
            if (strlen($value) == 3) {
                $conditions[] = ['text', '~', "($value)"];
            } else {
                foreach (explode(' ', $value) as $val) {
                    $conditions[] = ['text', '~', $val];
                }
            }
            $airportBuffers = cachedDatabase('airport_buffers', $conditions, ['title', 'id']);
            $data = [];
            foreach ($airportBuffers ?? [] as $id => $title) {
                $data[] = "$id- $title";
            }
            if (empty($data)) {
                $this->error('not found.');
                continue;
            }
            return $this->choice("chose an item", $data);
        } while (true);
    }

    protected function choseBuyer()
    {
        do {
            $value = $this->ask("Please enter a part of name");
            if (empty($value)) {
                continue;
            }
            $Buyer = Buyer::where('alias', 'like', "%$value%")->pluck('alias', 'id')->toArray();
            $data = [];
            foreach ($Buyer ?? [] as $id => $alias) {
                $data[] = "$id- $alias";
            }
            if (empty($data)) {
                $this->error('not found.');
                continue;
            }
            return $this->choice("chose an item", $data);
        } while (true);
    }

    protected function extractId($input)
    {
        return explode('-', $input)[0] ?? null;
    }
}
