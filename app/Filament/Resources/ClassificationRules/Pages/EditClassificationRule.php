<?php

namespace App\Filament\Resources\ClassificationRules\Pages;

use App\Filament\Resources\ClassificationRules\ClassificationRuleResource;
use App\Models\ClassificationRule;
use App\Services\Mail\RuleTester;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditClassificationRule extends EditRecord
{
    protected static string $resource = ClassificationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prova')->label('Prova la regola')->icon('heroicon-o-beaker')->iconButton()->tooltip('Prova la regola')->color('gray')
                ->modalHeading('Email che corrispondono (con i valori nel modulo, non ancora salvati)')
                ->modalSubmitAction(false)->modalCancelActionLabel('Chiudi')
                ->modalContent(function () {
                    $rule = new ClassificationRule($this->form->getState() + ['company_id' => $this->record->company_id]);
                    $found = app(RuleTester::class)->sample($rule);

                    return new HtmlString($found->isEmpty()
                        ? '<p>Nessuna email recente corrisponde.</p>'
                        : '<ul>'.$found->map(fn ($e) => '<li>'.e($e->received_at?->format('d/m/Y').' · '.$e->from_email.' · '.mb_substr((string) $e->subject, 0, 80)).'</li>')->implode('').'</ul>');
                }),
            DeleteAction::make(),
        ];
    }
}
