<?php

namespace Tests\Unit;

use App\Services\Mail\ReplyTrimmer;
use PHPUnit\Framework\TestCase;

class ReplyTrimmerTest extends TestCase
{
    public function test_toglie_intestazione_di_inoltro_e_catena(): void
    {
        $t = "From: a@pec.itSent: 11/19/2026To: b@pec.itSubject: R: X\n----- Messaggio inoltrato -----\nDa: a@pec.it\nOggetto: R: X\nBuongiorno,\nnuova risposta.\nSaluti\nDa: b@pec.it <b@pec.it>\nInviato: giovedì 19 novembre\nA: a@pec.it\nOggetto: RE: X\nVecchio testo";
        $this->assertSame("Buongiorno,\nnuova risposta.\nSaluti", ReplyTrimmer::latest($t));
    }

    public function test_riga_da_isolata_non_taglia(): void
    {
        $this->assertSame("Ciao\nDa: oggi in poi\nfine", ReplyTrimmer::latest("Ciao\nDa: oggi in poi\nfine"));
    }

    public function test_citazione_e_separatore(): void
    {
        $this->assertSame('Ok', ReplyTrimmer::latest("Ok\n\nIl 19/11/2026 10:00 Mario ha scritto:\n> vecchio"));
        $this->assertSame('Testo intero senza catena', ReplyTrimmer::latest('Testo intero senza catena'));
    }
}
