<x-mail::message>
# Escalation Assistente AI — {{ $code }}

Un utente non ha trovato soddisfacente la risposta dell'Assistente AI di UnicoPEC e ha richiesto l'intervento del supporto.

**Codice:** {{ $code }}
**Utente:** {{ $user->name }} ({{ $user->email }})

---

**Domanda posta:**

{{ $prompt }}

@if ($answer)
**Risposta dell'assistente:**

{{ $answer }}
@endif

@if ($error)
**Errore restituito dall'assistente:**

{{ $error }}
@endif

---

Rispondere direttamente a questa email per contattare l'utente.
</x-mail::message>
