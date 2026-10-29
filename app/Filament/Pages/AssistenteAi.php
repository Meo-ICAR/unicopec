<?php

namespace App\Filament\Pages;

use App\Services\Assistant\PecAssistant;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class AssistenteAi extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Manuali';

    protected static ?string $navigationLabel = 'Assistente AI';

    protected static ?string $title = 'Assistente AI';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.assistente-ai';

    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];

    public string $question = '';

    public function ask(): void
    {
        $question = trim($this->question);

        if ($question === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => mb_substr($question, 0, 2000)];
        $this->question = '';

        try {
            $reply = app(PecAssistant::class)->answer($this->messages, Filament::getTenant());
        } catch (\Throwable $e) {
            $reply = 'Non riesco a rispondere in questo momento: '.mb_substr($e->getMessage(), 0, 200);
        }

        $this->messages[] = ['role' => 'assistant', 'content' => $reply];
    }

    public function nuovaConversazione(): void
    {
        $this->messages = [];
    }
}
