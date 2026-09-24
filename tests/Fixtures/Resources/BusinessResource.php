<?php

namespace Otatechie\FilamentPaystackConnect\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Otatechie\FilamentPaystackConnect\Tests\Fixtures\Business;

class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinesses::route('/'),
            'view' => ViewBusiness::route('/{record}'),
        ];
    }
}
