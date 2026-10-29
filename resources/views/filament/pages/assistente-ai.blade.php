<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Chiedi come si usa UnicoPEC, come funziona lo smistamento o cosa significa un'anomalia. L'assistente conosce i manuali e il riepilogo della società aperta, ma non legge il testo delle email.
        </p>

        <div class="space-y-3">
            @forelse ($messages as $message)
                <div @class(['flex', 'justify-end' => $message['role'] === 'user'])>
                    <div @class([
                        'max-w-3xl whitespace-pre-line rounded-xl px-4 py-3 text-sm',
                        'bg-primary-600 text-white' => $message['role'] === 'user',
                        'bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10' => $message['role'] !== 'user',
                    ])>{{ $message['content'] }}</div>
                </div>
            @empty
                <div class="rounded-xl bg-white p-4 text-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    Esempi: «Perché una PEC è senza referente?», «Come collego una casella Microsoft 365?», «Cosa fa la soglia di affidabilità?»
                </div>
            @endforelse

            <div wire:loading wire:target="ask" class="text-sm text-gray-500">L'assistente sta scrivendo…</div>
        </div>

        <form wire:submit="ask" class="flex gap-2">
            <x-filament::input.wrapper class="flex-1">
                <x-filament::input type="text" wire:model="question" placeholder="Scrivi la tua domanda…" autofocus />
            </x-filament::input.wrapper>
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="ask">Invia</x-filament::button>
            @if ($messages)
                <x-filament::button color="gray" wire:click="nuovaConversazione">Nuova conversazione</x-filament::button>
            @endif
        </form>
    </div>
</x-filament-panels::page>
