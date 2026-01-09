<?php

namespace App\Filament\Resources\AssemblyResource\Pages;

use App\Filament\Resources\AssemblyResource;
use App\Models\Assembly;
use App\Models\Component;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAssembly extends CreateRecord
{
    protected static string $resource = AssemblyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate Serial Number dengan format DG(YYYY)(MM)(XXX)
        $year = date('Y');
        $month = date('m');
        
        // Hitung jumlah assembly di bulan ini
        $countThisMonth = Assembly::whereYear('assembly_date', $year)
            ->whereMonth('assembly_date', (int)$month)
            ->count();
        
        // Nomor urut = count + 1 (karena ini akan jadi assembly berikutnya)
        $sequence = $countThisMonth + 1;
        
        // Format: DG202512001 (DG + YYYY + MM + XXX dengan padding 3 digit)
        $data['serial_number'] = 'DG' . $year . $month . str_pad($sequence, 3, '0', STR_PAD_LEFT);
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Ambil data sn_details dari record yang baru saja dibuat
        $data = $this->record->sn_details;

        // Ambil semua SN yang ada di dalam array sn_details
        // (Chassis, Processor, RAM 1, RAM 2, SSD)
        $serialNumbers = array_values($data);

        // Update status komponen tersebut di database menjadi 'used'
        Component::whereIn('sn', $serialNumbers)
            ->update(['status' => 'used']);
    }
}
