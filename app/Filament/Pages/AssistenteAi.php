<?php

namespace App\Filament\Pages;

use App\Mail\AssistantEscalationMail;
use App\Models\User;
use App\Neuron\ManualAssistantAgent;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;
use NeuronAI\Chat\Messages\UserMessage;

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
            $reply = (string) ManualAssistantAgent::make()->chat(new UserMessage($question))->getMessage()->getContent();
        } catch (\Throwable $e) {
            $reply = 'Non riesco a rispondere in questo momento: '.mb_substr($e->getMessage(), 0, 200);
        }

        $this->messages[] = ['role' => 'assistant', 'content' => $reply];
    }

    public function nuovaConversazione(): void
    {
        $this->messages = [];
    }

    /** Inoltra al supporto l'ultima domanda con la risposta ricevuta; l'utente è in copia. */
    public function escalate(): void
    {
        $user = auth()->user();
        $count = count($this->messages);

        if (! $user instanceof User || $count < 2) {
            return;
        }

        $code = now()->format('Y-m-d H:i');

        try {
            Mail::to(config('pec.support_email'))->cc($user->email)->send(new AssistantEscalationMail(
                code: $code,
                user: $user,
                prompt: $this->messages[$count - 2]['content'],
                answer: $this->messages[$count - 1]['content'],
                error: null,
            ));

            Notification::make()->title("Richiesta inoltrata al supporto ({$code})")->body('Riceverai una copia dell\'email.')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Impossibile contattare il supporto')->body(mb_substr($e->getMessage(), 0, 200))->danger()->send();
        }
    }
}
