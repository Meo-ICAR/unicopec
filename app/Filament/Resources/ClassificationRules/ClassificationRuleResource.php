<?php

namespace App\Filament\Resources\ClassificationRules;

use App\Filament\Resources\ClassificationRules\Pages\CreateClassificationRule;
use App\Filament\Resources\ClassificationRules\Pages\EditClassificationRule;
use App\Filament\Resources\ClassificationRules\Pages\ListClassificationRules;
use App\Filament\Resources\ClassificationRules\Schemas\ClassificationRuleForm;
use App\Filament\Resources\ClassificationRules\Tables\ClassificationRulesTable;
use App\Models\ClassificationRule;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Regole di smistamento della società aperta, più quelle globali (società vuota). Le globali le modifica solo il super admin. */
class ClassificationRuleResource extends Resource
{
    protected static ?string $model = ClassificationRule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $navigationLabel = 'Regole di smistamento';

    protected static ?string $modelLabel = 'Regola';

    protected static ?string $pluralModelLabel = 'Regole di smistamento';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 4;

    // Le regole globali non hanno società: lo scoping automatico del tenant le nasconderebbe, quindi il filtro è esplicito.
    protected static bool $isScopedToTenant = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canManageEmails();
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return parent::getEloquentQuery()->when($tenant, fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('company_id')->orWhere('company_id', $tenant->getKey())));
    }

    public static function canEdit(Model $record): bool
    {
        return $record->company_id !== null || (bool) auth()->user()?->isSuperAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return ClassificationRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassificationRulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassificationRules::route('/'),
            'create' => CreateClassificationRule::route('/create'),
            'edit' => EditClassificationRule::route('/{record}/edit'),
        ];
    }
}
