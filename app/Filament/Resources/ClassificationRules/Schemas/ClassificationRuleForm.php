<?php

namespace App\Filament\Resources\ClassificationRules\Schemas;

use App\Enums\EmailCategory;
use App\Services\Mail\RuleMatcher;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClassificationRuleForm
{
    /** @return array<string, string> */
    public static function categories(): array
    {
        return collect(EmailCategory::options())->except([EmailCategory::ProviderNotification->value])->all();
    }

    /** Il pattern deve essere un'espressione regolare valida e non troppo lunga; almeno una delle tre condizioni deve essere compilata. */
    private static function pattern(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->maxLength(RuleMatcher::MAX_PATTERN)
            ->rules([fn (): Closure => function (string $attribute, $value, Closure $fail) {
                if (filled($value) && ($error = (new RuleMatcher)->validatePattern((string) $value))) {
                    $fail($error);
                }
            }]);
    }

    public static function configure(Schema $schema): Schema
    {
        $atLeastOne = fn (string $name) => fn ($get) => blank($get('sender_pattern')) && blank($get('subject_pattern')) && blank($get('body_pattern')) && $name === 'sender_pattern';

        return $schema->components([
            Section::make('Regola')->columns(2)->schema([
                TextInput::make('name')->label('Nome')->required()->maxLength(255),
                TextInput::make('priority')->label('Priorità')->numeric()->minValue(0)->default(100)->required()->helperText('Il numero più basso vince.'),
                Select::make('category')->label('Categoria assegnata')->options(fn () => self::categories())->required()
                    ->helperText('La funzione competente segue la categoria; il referente lo sceglie l\'organigramma.'),
                Toggle::make('is_active')->label('Attiva')->default(true),
            ]),
            Section::make('Condizioni')->description('Espressioni regolari, senza distinzione tra maiuscole e minuscole. Compila almeno una condizione.')->columns(1)->schema([
                self::pattern('sender_pattern', 'Mittente')->placeholder('agenziaentrate\.gov\.it$')
                    ->required($atLeastOne('sender_pattern'))->validationMessages(['required' => 'Compila almeno una condizione.']),
                self::pattern('subject_pattern', 'Oggetto'),
                self::pattern('body_pattern', 'Corpo'),
                Select::make('operator')->label('Quando scatta')->options(['or' => 'Basta una condizione (OR)', 'and' => 'Servono tutte quelle compilate (AND)'])->default('or')->required(),
            ]),
        ]);
    }
}
