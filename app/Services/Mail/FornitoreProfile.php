<?php

namespace App\Services\Mail;

use Unico\Core\Models\Fornitore;

/**
 * Confronta i dati del cedente letti da una fattura (vedi InvoiceProformaChecker::readDocument) con la scheda del produttore (`fornitoris`).
 * Per ogni campo dice se è già uguale, se manca nella scheda (si può compilare) o se è diverso (lo decide una persona: non si sovrascrive mai).
 * L'IBAN si compila solo se la scheda non ne ha uno e resta «non verificato» (`iban_verified_at` nullo): un IBAN diverso non lo sostituisce mai,
 * perché un cambio di IBAN va verificato da una persona (richiamata e documento della banca) prima di essere usato per i pagamenti.
 */
class FornitoreProfile
{
    /** campo della fattura (`cedente`) => colonna di `fornitoris` */
    public const FIELDS = [
        'vat' => 'piva',
        'tax_code' => 'cf',
        'name' => 'nomefattura',
        'address' => 'indirizzo',
        'zip' => 'cap',
        'city' => 'comune',
        'province' => 'prov',
        'phone' => 'tel',
        'pec' => 'pec',
        'iban' => 'iban',
        'iban_holder' => 'iban_holder',
        'tax_regime' => 'tax_regime',
        'withholding_percent' => 'withholding_percent',
    ];

    public const ACTION_SAME = 'uguale';

    public const ACTION_FILL = 'da_compilare';

    public const ACTION_CONFLICT = 'diverso';

    /** @param array<string, mixed> $cedente @return array<int, array{campo: string, colonna: string, attuale: ?string, fattura: string, azione: string}> */
    public function compare(Fornitore $producer, array $cedente): array
    {
        $rows = [];

        foreach (self::FIELDS as $field => $column) {
            $found = $this->clean($field, $cedente[$field] ?? null);

            if ($found === null) {
                continue;
            }

            $current = filled($producer->{$column}) ? (string) $producer->{$column} : null;
            $action = $current === null ? self::ACTION_FILL : ($this->key($field, $current) === $this->key($field, $found) ? self::ACTION_SAME : self::ACTION_CONFLICT);

            $rows[] = ['campo' => $field, 'colonna' => $column, 'attuale' => $current, 'fattura' => $found, 'azione' => $action];
        }

        return $rows;
    }

    /** Compila solo i campi vuoti della scheda. @param array<int, array<string, mixed>> $rows da compare() @return array<int, string> colonne compilate */
    public function fillEmpty(Fornitore $producer, array $rows): array
    {
        $fill = collect($rows)->where('azione', self::ACTION_FILL)->pluck('fattura', 'colonna')->all();

        if ($fill !== []) {
            if (isset($fill['iban'])) {
                $fill['iban_updated_at'] = now();
                $fill['iban_verified_at'] = null;
            }

            $producer->forceFill($fill + ['invoice_data_at' => now()])->save();
        }

        return array_values(array_diff(array_keys($fill), ['iban_updated_at', 'iban_verified_at', 'invoice_data_at']));
    }

    /** Valore ripulito e plausibile, oppure null (un dato dubbio non entra nella scheda). */
    private function clean(string $field, mixed $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        if ($value === '') {
            return null;
        }

        return match ($field) {
            'vat' => preg_match('/^\d{11}$/', $digits = preg_replace('/\D/', '', preg_replace('/^IT/i', '', $value) ?? '') ?? '') ? $digits : null,
            'tax_code' => preg_match('/^([A-Z0-9]{16}|\d{11})$/', $cf = strtoupper(str_replace(' ', '', $value))) ? $cf : null,
            'zip' => preg_match('/^\d{5}$/', $value) ? $value : null,
            'province' => preg_match('/^[A-Za-z]{2}$/', $value) ? strtoupper($value) : null,
            'phone' => strlen(preg_replace('/\D/', '', $value) ?? '') >= 6 ? $value : null,
            'pec' => filter_var($value, FILTER_VALIDATE_EMAIL) ? mb_strtolower($value) : null,
            'iban' => EmailIntentExtractor::validIban($iban = strtoupper(str_replace(' ', '', $value))) ? $iban : null,
            'tax_regime' => in_array($regime = mb_strtolower($value), ['ordinario', 'forfettario', 'altro'], true) ? $regime : null,
            'withholding_percent' => is_numeric($percent = str_replace(',', '.', rtrim($value, '% '))) && $percent >= 0 && $percent <= 50 ? number_format((float) $percent, 2, '.', '') : null,
            default => mb_substr($value, 0, 255),
        };
    }

    private function key(string $field, string $value): string
    {
        return match ($field) {
            'vat', 'phone' => preg_replace('/\D/', '', preg_replace('/^IT/i', '', $value) ?? '') ?? '',
            'iban' => strtoupper(str_replace(' ', '', $value)),
            'withholding_percent' => number_format((float) $value, 2, '.', ''),
            default => mb_strtolower(preg_replace('/[^\p{L}\p{N}]/u', '', $value) ?? ''),
        };
    }
}
