<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Argomento di un'email/PEC, scelto dalla classificazione. Ogni categoria indica il ruolo aziendale competente
 * (`role`, il nome in employee_types) e, se manca chi lo ricopre, i ruoli di ripiego (`fallbackRoles`).
 * Le categorie senza ruolo (`role() === null`) non vengono smistate a nessuno.
 */
enum EmailCategory: string implements HasColor, HasLabel
{
    case DpoPrivacy = 'dpo_privacy';
    case Fiscale = 'fiscale';
    case PrevidenzaLavoro = 'previdenza_lavoro';
    case Legale = 'legale';
    case Vigilanza = 'vigilanza';
    case Antiriciclaggio = 'antiriciclaggio';
    case PraticheClienti = 'pratiche_clienti';
    case AmministrativoContabile = 'amministrativo_contabile';
    case SocietarioBandi = 'societario_bandi';
    case ItSicurezza = 'it_sicurezza';
    case Whistleblowing = 'whistleblowing';
    case Altro = 'altro';
    case ProviderNotification = 'provider_notification';
    case NonRilevante = 'non_rilevante';

    /** Descrizione per l'AI: quando scegliere questa categoria. */
    public function description(): string
    {
        return match ($this) {
            self::DpoPrivacy => 'Privacy e protezione dati: comunicazioni del Garante, RPD/DPO, richieste degli interessati (accesso, cancellazione...), data breach, reclami privacy.',
            self::Fiscale => 'Fisco e tributi: Agenzia delle Entrate, Agenzia delle Entrate-Riscossione, cartelle di pagamento, avvisi, F24, accertamenti, certificazioni.',
            self::PrevidenzaLavoro => 'Lavoro e previdenza: INPS, INAIL, ispettorato, disoccupazione, contributi, contratti e risorse umane.',
            self::Legale => 'Legale e contenzioso: diffide, atti giudiziari, notifiche di tribunali, avvocati, contratti da revisionare, reclami non privacy.',
            self::Vigilanza => 'Vigilanza e organismi di settore: OAM, Banca d\'Italia, IVASS, ABI, ordini professionali, camere di commercio per adempimenti di settore.',
            self::Antiriciclaggio => 'Antiriciclaggio: UIF, segnalazioni di operazioni sospette, adeguata verifica, richieste di autorità su AML.',
            self::PraticheClienti => 'Pratiche e clienti: richieste su pratiche di finanziamento, istruttorie, banche e finanziarie, documenti dei clienti, delibere.',
            self::AmministrativoContabile => 'Amministrazione e contabilità: fatture, pagamenti, solleciti di pagamento, fornitori, banche per conti, bilanci.',
            self::SocietarioBandi => 'Affari societari e bandi: assemblee, verbali, CCIAA, bandi e gare, provvedimenti di enti pubblici su partecipazioni, partnership.',
            self::ItSicurezza => 'IT e sicurezza informatica: avvisi di sicurezza, scadenza di domini o servizi, provider PEC e hosting, incidenti informatici.',
            self::Whistleblowing => 'Segnalazioni di illeciti (whistleblowing).',
            self::Altro => 'Niente di quanto sopra, ma serve una persona che la legga.',
            self::ProviderNotification => 'Ricevute e notifiche automatiche del gestore PEC (accettazione, consegna, errori).',
            self::NonRilevante => 'Pubblicità, newsletter, spam o messaggi senza alcun seguito.',
        };
    }

    /** Ruolo (employee_types.name) a cui va smistata; null = nessuno. */
    public function role(): ?string
    {
        return match ($this) {
            self::DpoPrivacy => 'dpo',
            self::Fiscale => 'contabile responsabile',
            self::PrevidenzaLavoro => 'organizzazione e risorse umane',
            self::Legale => 'legale',
            self::Vigilanza => 'compliance',
            self::Antiriciclaggio => 'antiriciclaggio',
            self::PraticheClienti => 'istruttore',
            self::AmministrativoContabile => 'contabile responsabile',
            self::SocietarioBandi => 'affari societari e contabilità',
            self::ItSicurezza => 'it',
            self::Whistleblowing => 'responsabile whistleblowing',
            self::Altro => 'segreteria amministrativa',
            self::ProviderNotification, self::NonRilevante => null,
        };
    }

    /**
     * Ruoli da provare, in ordine, se nessuno ricopre `role()`: prima quelli equivalenti, poi (via gerarchia in employee_types.managed_by_code)
     * chi gestisce il ruolo, e in fondo CdA e segreteria, così una PEC non resta mai senza referente.
     *
     * @return array<int, string>
     */
    public function fallbackRoles(): array
    {
        return match ($this) {
            self::Fiscale, self::AmministrativoContabile => ['contabile', 'affari societari e contabilità', 'amministratore delegato', 'cda', 'segreteria amministrativa'],
            self::SocietarioBandi => ['amministratore delegato', 'cda', 'segreteria amministrativa'],
            self::Vigilanza => ['controllo compliance', 'antiriciclaggio', 'cda', 'segreteria amministrativa'],
            default => ['cda', 'segreteria amministrativa'],
        };
    }

    public function isRoutable(): bool
    {
        return $this->role() !== null;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::DpoPrivacy => 'Privacy / DPO',
            self::Fiscale => 'Fiscale e tributi',
            self::PrevidenzaLavoro => 'Lavoro e previdenza',
            self::Legale => 'Legale',
            self::Vigilanza => 'Vigilanza e organismi',
            self::Antiriciclaggio => 'Antiriciclaggio',
            self::PraticheClienti => 'Pratiche e clienti',
            self::AmministrativoContabile => 'Amministrativo e contabile',
            self::SocietarioBandi => 'Societario e bandi',
            self::ItSicurezza => 'IT e sicurezza',
            self::Whistleblowing => 'Whistleblowing',
            self::Altro => 'Altro',
            self::ProviderNotification => 'Notifica del gestore PEC',
            self::NonRilevante => 'Non rilevante',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DpoPrivacy, self::Antiriciclaggio, self::Whistleblowing => 'danger',
            self::Fiscale, self::Legale, self::Vigilanza => 'warning',
            self::ProviderNotification, self::NonRilevante, self::Altro => 'gray',
            default => 'info',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->getLabel()])->all();
    }
}
