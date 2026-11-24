<?php

namespace Tests\Unit;

use App\Services\Mail\PecEnvelope;
use PHPUnit\Framework\TestCase;

class PecEnvelopePlainTest extends TestCase
{
    public function test_html_nel_testo_diventa_testo_semplice_senza_css(): void
    {
        $t = '<html><head><style>body { margin: 0; }</style></head><body><p>Ciao</p><span style="x">mondo</span><br>fine</body></html>';
        $out = PecEnvelope::plain($t);
        $this->assertStringNotContainsString('margin', $out);
        $this->assertStringNotContainsString('<', $out);
        $this->assertStringContainsString('Ciao', $out);
        $this->assertStringContainsString('fine', $out);
    }

    public function test_testo_semplice_resta_invariato(): void
    {
        $this->assertSame("Riga 1\nA < B e C > D", PecEnvelope::plain("Riga 1\nA < B e C > D"));
        $this->assertNull(PecEnvelope::plain(null));
    }
}
