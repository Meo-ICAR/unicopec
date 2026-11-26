<?php

namespace Tests\Unit;

use App\Models\SenderBlacklist;
use PHPUnit\Framework\TestCase;

class SenderBlacklistSubjectTest extends TestCase
{
    public function test_l_oggetto_contiene_il_testo_senza_distinguere_le_maiuscole(): void
    {
        $patterns = collect([SenderBlacklist::normalizeSubject('  Autenticazione a MULTI fattore ')]);

        $this->assertTrue(SenderBlacklist::matchesSubject($patterns, 'Autenticazione a multi fattore'));
        $this->assertTrue(SenderBlacklist::matchesSubject($patterns, 'RE: AUTENTICAZIONE A MULTI FATTORE - codice'));
        $this->assertFalse(SenderBlacklist::matchesSubject($patterns, 'Fattura marzo'));
    }

    public function test_con_l_asterisco_il_testo_e_un_modello(): void
    {
        $patterns = collect(['codice * accesso']);

        $this->assertTrue(SenderBlacklist::matchesSubject($patterns, 'Il tuo codice di oggi per accesso'));
        $this->assertFalse(SenderBlacklist::matchesSubject($patterns, 'Il tuo accesso con codice'));
    }

    public function test_senza_oggetto_o_senza_regole_non_esclude_nulla(): void
    {
        $this->assertFalse(SenderBlacklist::matchesSubject(collect(['x']), null));
        $this->assertFalse(SenderBlacklist::matchesSubject(collect(), 'qualsiasi'));
    }
}
