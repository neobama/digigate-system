<?php

namespace Database\Seeders;

use App\Models\DeviceType;
use App\Services\DeviceTypeCatalog;
use Illuminate\Database\Seeder;

class DeviceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $devices = [
            [
                'code' => 'Macan',
                'name' => 'DigiGate Macan (i7 11700K)',
                'description' => 'Produk Macan — processor i7 11700K, RAM DDR4',
                'sort_order' => 1,
                'components' => [
                    ['slot_key' => 'chassis', 'component_name' => 'Chassis Macan', 'sort_order' => 1],
                    ['slot_key' => 'processor', 'component_name' => 'Processor i7 11700K', 'sort_order' => 2],
                    ['slot_key' => 'ram_1', 'component_name' => 'RAM DDR4', 'label' => 'RAM DDR4 (Slot 1)', 'sort_order' => 3],
                    ['slot_key' => 'ram_2', 'component_name' => 'RAM DDR4', 'label' => 'RAM DDR4 (Slot 2)', 'sort_order' => 4, 'is_required' => false],
                    ['slot_key' => 'ssd', 'component_name' => 'SSD', 'sort_order' => 5],
                ],
            ],
            [
                'code' => 'Maleo',
                'name' => 'DigiGate Maleo (i7 8700K)',
                'description' => 'Produk Maleo — processor i7 8700K, RAM DDR4',
                'sort_order' => 2,
                'components' => [
                    ['slot_key' => 'chassis', 'component_name' => 'Chassis Maleo', 'sort_order' => 1],
                    ['slot_key' => 'processor', 'component_name' => 'Processor i7 8700K', 'sort_order' => 2],
                    ['slot_key' => 'ram_1', 'component_name' => 'RAM DDR4', 'label' => 'RAM DDR4 (Slot 1)', 'sort_order' => 3],
                    ['slot_key' => 'ram_2', 'component_name' => 'RAM DDR4', 'label' => 'RAM DDR4 (Slot 2)', 'sort_order' => 4, 'is_required' => false],
                    ['slot_key' => 'ssd', 'component_name' => 'SSD', 'sort_order' => 5],
                ],
            ],
            [
                'code' => 'Komodo',
                'name' => 'DigiGate Komodo (i7 14700K)',
                'description' => 'Produk Komodo — processor i7 14700K, RAM DDR5',
                'sort_order' => 3,
                'components' => [
                    ['slot_key' => 'chassis', 'component_name' => 'Chassis Komodo', 'sort_order' => 1],
                    ['slot_key' => 'processor', 'component_name' => 'Processor i7 14700K', 'sort_order' => 2],
                    ['slot_key' => 'ram_1', 'component_name' => 'RAM DDR5', 'label' => 'RAM DDR5 (Slot 1)', 'sort_order' => 3],
                    ['slot_key' => 'ram_2', 'component_name' => 'RAM DDR5', 'label' => 'RAM DDR5 (Slot 2)', 'sort_order' => 4, 'is_required' => false],
                    ['slot_key' => 'ssd', 'component_name' => 'SSD', 'sort_order' => 5],
                ],
            ],
        ];

        foreach ($devices as $deviceData) {
            $components = $deviceData['components'];
            unset($deviceData['components']);

            $device = DeviceType::query()->updateOrCreate(
                ['code' => $deviceData['code']],
                [
                    'name' => $deviceData['name'],
                    'description' => $deviceData['description'],
                    'is_active' => true,
                    'sort_order' => $deviceData['sort_order'],
                ]
            );

            foreach ($components as $component) {
                $device->components()->updateOrCreate(
                    ['slot_key' => $component['slot_key']],
                    [
                        'component_name' => $component['component_name'],
                        'label' => $component['label'] ?? null,
                        'is_required' => $component['is_required'] ?? true,
                        'sort_order' => $component['sort_order'],
                    ]
                );
            }
        }

        DeviceTypeCatalog::forgetCache();
    }
}
