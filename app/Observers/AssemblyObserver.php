<?php

namespace App\Observers;

use App\Models\Assembly;
use App\Models\Component;
use App\Services\ActivityLogService;

class AssemblyObserver
{
    /**
     * Handle the Assembly "created" event.
     */
    public function created(Assembly $assembly): void
    {
        // Log activity
        ActivityLogService::logCreate($assembly);
    }

    /**
     * Handle the Assembly "updated" event.
     */
    public function updated(Assembly $assembly): void
    {
        // Log activity
        $oldValues = $assembly->getOriginal();
        $newValues = $assembly->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);
        if (!empty($newValues)) {
            ActivityLogService::logUpdate($assembly, $oldValues, $newValues);
        }
        
        // If sn_details changed, update component statuses
        if ($assembly->wasChanged('sn_details')) {
            $oldSnDetails = $assembly->getOriginal('sn_details');
            $newSnDetails = $assembly->sn_details;
            
            // Get old serial numbers (if any)
            if ($oldSnDetails && is_array($oldSnDetails)) {
                $oldSerialNumbers = array_values($oldSnDetails);
                // Return old components to available
                Component::whereIn('sn', $oldSerialNumbers)
                    ->update(['status' => 'available']);
            }
            
            // Mark new components as used
            if ($newSnDetails && is_array($newSnDetails)) {
                $newSerialNumbers = array_values($newSnDetails);
                Component::whereIn('sn', $newSerialNumbers)
                    ->update(['status' => 'used']);
            }
        }
    }

    /**
     * Handle the Assembly "deleted" event.
     */
    public function deleted(Assembly $assembly): void
    {
        // Log activity
        ActivityLogService::logDelete($assembly);
        
        // Return all components used in this assembly back to 'available'
        if ($assembly->sn_details && is_array($assembly->sn_details)) {
            $serialNumbers = array_values($assembly->sn_details);
            
            // Update component status back to 'available'
            Component::whereIn('sn', $serialNumbers)
                ->update(['status' => 'available']);
        }
    }

    /**
     * Handle the Assembly "restored" event.
     */
    public function restored(Assembly $assembly): void
    {
        // When assembly is restored, mark components as used again
        if ($assembly->sn_details && is_array($assembly->sn_details)) {
            $serialNumbers = array_values($assembly->sn_details);
            Component::whereIn('sn', $serialNumbers)
                ->update(['status' => 'used']);
        }
    }

    /**
     * Handle the Assembly "force deleted" event.
     */
    public function forceDeleted(Assembly $assembly): void
    {
        // Same as deleted - return components to available
        if ($assembly->sn_details && is_array($assembly->sn_details)) {
            $serialNumbers = array_values($assembly->sn_details);
            Component::whereIn('sn', $serialNumbers)
                ->update(['status' => 'available']);
        }
    }
}
