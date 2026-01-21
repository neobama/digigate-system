<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('calendar')
                ->label('Kalender')
                ->icon('heroicon-o-calendar-days')
                ->url(fn () => TaskResource::getUrl('calendar')),
            Actions\CreateAction::make(),
        ];
    }
}
