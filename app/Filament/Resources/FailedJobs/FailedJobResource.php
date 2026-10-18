<?php

namespace App\Filament\Resources\FailedJobs;

use App\Filament\Resources\FailedJobs\Pages\ListFailedJobs;
use App\Filament\Resources\FailedJobs\Pages\ViewFailedJob;
use App\Models\FailedJob;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

/**
 * Background jobs that gave up after every try: try them again once the
 * cause is fixed, or clear them.
 */
class FailedJobResource extends Resource
{
    protected static ?string $model = FailedJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Failed jobs';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = FailedJob::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function retry(FailedJob $job): void
    {
        Artisan::call('queue:retry', ['id' => [$job->uuid]]);
    }

    public static function forget(FailedJob $job): void
    {
        Artisan::call('queue:forget', ['id' => $job->uuid]);
    }

    /**
     * @param  Collection<int, Model>  $records
     * @param  callable(FailedJob): void  $do
     */
    private static function each(Collection $records, callable $do): void
    {
        foreach ($records as $record) {
            if ($record instanceof FailedJob) {
                $do($record);
            }
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('failed_at', 'desc')
            ->columns([
                TextColumn::make('job')->state(fn (FailedJob $record) => $record->name()),
                TextColumn::make('reason')->label('What went wrong')->state(fn (FailedJob $record) => $record->reason())->limit(80)->wrap(),
                TextColumn::make('queue')->badge()->color('gray'),
                TextColumn::make('failed_at')->label('Gave up')->since()->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Try again')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->action(function (FailedJob $record) {
                        self::retry($record);
                        Notification::make()->title('Sent back to the queue.')->success()->send();
                    }),
                Action::make('forget')
                    ->label('Clear')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (FailedJob $record) {
                        self::forget($record);
                        Notification::make()->title('Cleared.')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('retry')
                        ->label('Try these again')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => self::each($records, self::retry(...))),
                    BulkAction::make('forget')
                        ->label('Clear these')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => self::each($records, self::forget(...))),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The job')->columns(3)->schema([
                TextEntry::make('job')->state(fn (FailedJob $record) => $record->name()),
                TextEntry::make('queue'),
                TextEntry::make('failed_at')->label('Gave up')->dateTime('j M Y, H:i:s'),
                TextEntry::make('uuid')->label('ID')->copyable()->columnSpanFull(),
            ]),
            Section::make('What went wrong')->schema([
                TextEntry::make('exception')->hiddenLabel()->fontFamily('mono')->size('xs'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFailedJobs::route('/'),
            'view' => ViewFailedJob::route('/{record}'),
        ];
    }
}
