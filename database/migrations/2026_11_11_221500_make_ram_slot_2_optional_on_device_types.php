<?php

use App\Services\DeviceTypeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('device_type_components')
            ->where('slot_key', 'ram_2')
            ->update(['is_required' => false]);

        DeviceTypeCatalog::forgetCache();
    }

    public function down(): void
    {
        DB::table('device_type_components')
            ->where('slot_key', 'ram_2')
            ->update(['is_required' => true]);

        DeviceTypeCatalog::forgetCache();
    }
};
