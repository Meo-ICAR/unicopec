<?php

namespace Tests\Feature;

use App\Models\IncomingEmail;
use App\Models\Protocol;
use App\Models\ProtocolRegister;
use App\Services\Protocol\ProtocolNumberer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;

class ProtocolNumbererTest extends TestCase
{
    use UsesAppSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppSchema();
        Carbon::setTestNow('2026-06-15 10:00:00');
    }

    private function register(string $code = 'FIS', int $company = 1, bool $active = true, array $roles = []): ProtocolRegister
    {
        $register = ProtocolRegister::create(['company_id' => $company, 'code' => $code, 'name' => "Registro $code", 'is_active' => $active]);
        $register->syncRoles($roles);

        return $register;
    }

    private function email(): IncomingEmail
    {
        return IncomingEmail::create(['company_id' => 1, 'subject' => 'x', 'from_email' => 'a@b.it']);
    }

    private function assign(ProtocolRegister $register, ?IncomingEmail $email = null, string $type = Protocol::SMISTAMENTO): Protocol
    {
        return app(ProtocolNumberer::class)->assign($register, $email ?? $this->email(), $type, 'contabile responsabile', null);
    }

    public function test_numbers_are_sequential_per_register_and_year(): void
    {
        $register = $this->register();

        $this->assertSame([1, 2, 3], [$this->assign($register)->number, $this->assign($register)->number, $this->assign($register)->number]);
    }

    public function test_new_year_restarts_at_one(): void
    {
        $register = $this->register();
        $this->assign($register);
        $this->assign($register);

        Carbon::setTestNow('2027-01-02 09:00:00');
        $protocol = $this->assign($register);

        $this->assertSame(2027, $protocol->year);
        $this->assertSame(1, $protocol->number);
    }

    public function test_identifier_format_is_code_year_six_digits(): void
    {
        $this->assertSame('FIS/2026/000001', $this->assign($this->register())->identifier);
    }

    public function test_two_registers_have_independent_counters(): void
    {
        $a = $this->register('FIS');
        $b = $this->register('GEN');
        $this->assign($a);
        $this->assign($a);

        $this->assertSame(1, $this->assign($b)->number);
    }

    public function test_second_protocol_of_same_type_for_same_email_throws_and_consumes_no_number(): void
    {
        $register = $this->register();
        $email = $this->email();
        $this->assign($register, $email);

        try {
            $this->assign($register, $email);
            $this->fail('Atteso un errore di vincolo.');
        } catch (QueryException) {
        }

        $this->assertSame(2, $this->assign($register)->number);
    }

    public function test_protocol_cannot_be_updated_or_deleted(): void
    {
        $protocol = $this->assign($this->register());

        $this->expectException(\LogicException::class);
        $protocol->update(['role' => 'altro']);
    }

    public function test_protocol_cannot_be_deleted(): void
    {
        $protocol = $this->assign($this->register());

        $this->expectException(\LogicException::class);
        $protocol->delete();
    }

    public function test_for_roles_returns_only_active_registers_of_the_company_sorted_by_code(): void
    {
        $this->register('ZZZ', 1, true, ['legale']);
        $this->register('AAA', 1, true, ['legale', 'it']);
        $this->register('OFF', 1, false, ['legale']);
        $this->register('ALT', 2, true, ['legale']);
        $this->register('XXX', 1, true, ['it']);

        $this->assertSame(['AAA', 'ZZZ'], ProtocolRegister::forRoles(1, ['legale'])->pluck('code')->all());
        $this->assertSame(['AAA', 'XXX', 'ZZZ'], ProtocolRegister::forRoles(1, ['legale', 'it'])->pluck('code')->all());
        $this->assertTrue(ProtocolRegister::forRoles(1, [])->isEmpty());
    }

    public function test_sync_roles_replaces_the_links(): void
    {
        $register = $this->register('FIS', 1, true, ['legale', 'it']);
        $register->syncRoles(['it', 'dpo']);

        $this->assertEqualsCanonicalizing(['it', 'dpo'], $register->roleNames());
    }

    public function test_year_follows_italian_time_at_new_year(): void
    {
        $register = $this->register();

        // 31/12 23:30 UTC sono già le 00:30 del 1° gennaio in Italia.
        Carbon::setTestNow('2026-12-31 23:30:00');
        $protocol = $this->assign($register);

        $this->assertSame(2027, $protocol->year);
        $this->assertSame('FIS/2027/000001', $protocol->identifier);
    }

    public function test_register_code_cannot_change_once_it_has_protocols(): void
    {
        $register = $this->register();
        $register->update(['name' => 'Rinominato']); // il nome si può cambiare
        $this->assign($register);

        $this->expectException(\LogicException::class);
        $register->update(['code' => 'ALTRO']);
    }

    public function test_register_code_can_change_before_the_first_protocol(): void
    {
        $register = $this->register();
        $register->update(['code' => 'NUOVO']);

        $this->assertSame('NUOVO', $register->fresh()->code);
    }
}
