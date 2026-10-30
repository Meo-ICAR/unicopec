<!doctype html>
<html lang="it">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Presa in carico — UnicoPEC</title></head>
<body style="font-family:Arial,sans-serif; background:#fffbeb; margin:0; padding:40px 16px; color:#1c1917;">
<div style="max-width:520px; margin:0 auto; background:#fff; border:1px solid #e7e5e4; border-radius:10px; padding:24px;">
    <h1 style="font-size:20px; margin:0 0 12px;">Presa in carico</h1>
    <p style="margin:0 0 4px;"><strong>{{ $email->subject }}</strong></p>
    <p style="margin:0 0 16px; color:#78716c; font-size:14px;">Da {{ $email->from_name ?: $email->from_email }} · ricevuta il {{ $email->received_at?->format('d/m/Y H:i') }}</p>

    @if ($result === null)
        @if ($email->taken_at)
            <p>Già presa in carico da <strong>{{ $email->takenBy?->name }}</strong> il {{ $email->taken_at->format('d/m/Y H:i') }}.</p>
        @else
            @if ($registersInUse && $registers->isEmpty())
                <p style="color:#b45309;">Nessun registro di protocollo è disponibile per la tua funzione: rivolgiti all'amministratore.</p>
            @else
                <p>{{ $employee->name }}, confermi di prendere in carico questa richiesta?</p>
                <form method="POST" action="{{ request()->fullUrl() }}">
                    @csrf
                    @if ($registersInUse)
                        <label for="register_id" style="display:block; font-size:14px; margin:0 0 6px;">Registro di protocollo</label>
                        <select id="register_id" name="register_id" required style="padding:8px; margin:0 0 16px; min-width:260px;">
                            @if ($registers->count() > 1)<option value="">— scegli —</option>@endif
                            @foreach ($registers as $register)
                                <option value="{{ $register->id }}" @selected($selected === $register->id)>{{ $register->code }} · {{ $register->name }}</option>
                            @endforeach
                        </select><br>
                    @endif
                    <button type="submit" style="padding:10px 18px; background:#d97706; color:#fff; border:0; border-radius:6px; font-weight:bold; cursor:pointer;">Prendo in carico</button>
                </form>
            @endif
        @endif
    @elseif ($result['error'])
        <p style="color:#b45309;">{{ $result['error'] }}</p>
        <p><a href="{{ request()->fullUrl() }}">Torna indietro</a></p>
    @elseif ($result['taken'])
        <p style="color:#15803d;"><strong>Fatto.</strong> La richiesta è assegnata a te ({{ $employee->name }}).@if ($result['protocol']) Protocollo: <strong>{{ $result['protocol']->identifier }}</strong>.@endif</p>
    @else
        <p style="color:#b45309;">Questa richiesta è stata già presa in carico da <strong>{{ $result['by']?->name }}</strong>. Non devi fare altro.</p>
    @endif
</div>
</body>
</html>
