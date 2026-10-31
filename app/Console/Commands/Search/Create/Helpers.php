<?php

namespace App\Console\Commands\Search\Create;

use Exception;

trait Helpers
{
    protected function handleNode()
    {
        switchNode($node = $this->argument('nodeId') ?? 1);
        if (empty(node())) {
            throw new Exception("Invalid node entered: $node", 1);
        }
        $this->info("switched to node $node");
    }

    protected function findSellers($searchQuery)
    {
        $sellers = $searchQuery->buyergroup->sellers()
            ->whereExists(function ($supplierServiceQuery) {
                $supplierServiceQuery->from(commonConnectionDBName() . '.supplier_service')
                    ->whereColumn('supplier_service_id', 'id')
                    ->whereIn('usability', [types('supplierUsability', 'open'), types('supplierUsability', 'search')]);
            })
            ->wherePivot('travel_service', types('travelService', 'flight'))
            ->select('alias', 'is_active', 'supplier_service_id', 'sellers.id')
            ->with('supplierService', function ($supplierServiceQuery) {
                $supplierServiceQuery->select('supplier', 'id');
            })->get()->toarray();
        foreach ($sellers as $seller) {
            $activity = $seller['is_active'] ? 'active' : 'inactive';
            $data[] = [$seller['id'], $seller['alias'], types('supplierBrand', $seller['supplier_service']['supplier']), $activity];
        }
        if (empty($data)) {
            return $this->error('No available sellers');
        }
        $this->info("Available sellers");
        $this->table(['id', 'alias', 'vendor', 'is_active'], $data);
        return $data ?? [];
    }
}
