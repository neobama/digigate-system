<?php

namespace App\Observers;

use App\Models\Assembly;
use App\Models\Component;
use App\Services\ActivityLogService;

class AssemblyObserver
{
    public function created(Assembly $assembly): void
    {
        ActivityLogService::logCreate($assembly);
    }

    public function updated(Assembly $assembly): void
    {
        $oldValues = $assembly->getOriginal();
        $newValues = $assembly->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);
        if (! empty($newValues)) {
            ActivityLogService::logUpdate($assembly, $oldValues, $newValues);
        }

        if (Assembly::$skipComponentStatusSync) {
            return;
        }

        if (! $assembly->wasChanged('sn_details')) {
            return;
        }

        $oldSnDetails = $assembly->getOriginal('sn_details');
        if (is_string($oldSnDetails)) {
            $oldSnDetails = json_decode($oldSnDetails, true) ?? [];
        }
        $newSnDetails = $assembly->sn_details ?? [];

        $oldSerials = array_values(array_filter(is_array($oldSnDetails) ? $oldSnDetails : []));
        $newSerials = array_values(array_filter(is_array($newSnDetails) ? $newSnDetails : []));

        $removed = array_values(array_diff($oldSerials, $newSerials));
        $added = array_values(array_diff($newSerials, $oldSerials));

        // Only return truly removed SNs to available (not ones kept or warranty-replaced)
        if ($removed !== []) {
            Component::query()
                ->whereIn('sn', $removed)
                ->where('status', 'used')
                ->update(['status' => 'available']);
        }

        if ($added !== []) {
            Component::query()
                ->whereIn('sn', $added)
                ->update(['status' => 'used']);
        }
    }

    public function deleted(Assembly $assembly): void
    {
        ActivityLogService::logDelete($assembly);

        if ($assembly->sn_details && is_array($assembly->sn_details)) {
            $serialNumbers = array_values(array_filter($assembly->sn_details));
            Component::query()
                ->whereIn('sn', $serialNumbers)
                ->where('status', 'used')
                ->update(['status' => 'available']);
        }
    }

    public function restored(Assembly $assembly): void
    {
        if ($assembly->sn_details && is_array($assembly->sn_details)) {
            $serialNumbers = array_values(array_filter($assembly->sn_details));
            Component::query()
                ->whereIn('sn', $serialNumbers)
                ->update(['status' => 'used']);
        }
    }

    public function forceDeleted(Assembly $assembly): void
    {
        if ($assembly->sn_details && is_array($assembly->sn_details)) {
            $serialNumbers = array_values(array_filter($assembly->sn_details));
            Component::query()
                ->whereIn('sn', $serialNumbers)
                ->where('status', 'used')
                ->update(['status' => 'available']);
        }
    }
}
