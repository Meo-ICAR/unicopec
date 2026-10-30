<?php

namespace Tests\Feature;

use App\Filament\Resources\ClassificationRules\Pages\CreateClassificationRule;
use App\Filament\Resources\ClassificationRules\Pages\ListClassificationRules;
use App\Models\ClassificationRule;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\UsesAppSchema;
use Tests\TestCase;

class ClassificationRuleResourceTest extends TestCase
{
    use UsesAppSchema;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppSchema();
        ClassificationRule::query()->delete();

        $this->company = Company::create(['name' => 'Alfa']);
        $admin = new User(['name' => 'A', 'email' => 'a@a.it', 'password' => 'x', 'role' => 'admin']);
        $admin->forceFill(['role' => 'admin', 'company_id' => $this->company->id])->save();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->company);
    }

    private function create(array $data)
    {
        return Livewire::test(CreateClassificationRule::class)->fillForm($data + ['name' => 'R', 'priority' => 100, 'is_active' => true, 'category' => 'legale', 'operator' => 'or'])->call('create');
    }

    public function test_create_rejects_invalid_regex(): void
    {
        $this->create(['subject_pattern' => '(aperta'])->assertHasFormErrors(['subject_pattern']);
    }

    public function test_create_rejects_rule_without_conditions(): void
    {
        $this->create([])->assertHasFormErrors(['sender_pattern']);
    }

    public function test_create_saves_valid_rule_for_the_open_company(): void
    {
        $this->create(['subject_pattern' => 'cartella'])->assertHasNoFormErrors();

        $this->assertSame($this->company->id, ClassificationRule::first()->company_id);
    }

    public function test_list_shows_company_and_global_rules_but_not_other_companies(): void
    {
        $mine = ClassificationRule::create(['company_id' => $this->company->id, 'name' => 'Mia', 'category' => 'legale', 'subject_pattern' => 'a']);
        $global = ClassificationRule::create(['company_id' => null, 'name' => 'Globale', 'category' => 'legale', 'subject_pattern' => 'a']);
        $other = ClassificationRule::create(['company_id' => 999, 'name' => 'Altrui', 'category' => 'legale', 'subject_pattern' => 'a']);

        Livewire::test(ListClassificationRules::class)->assertCanSeeTableRecords([$mine, $global])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_plain_user_cannot_access_the_resource(): void
    {
        $user = new User(['name' => 'U', 'email' => 'u@a.it', 'password' => 'x']);
        $user->forceFill(['role' => 'user', 'company_id' => $this->company->id])->save();
        $this->actingAs($user);

        $this->assertFalse(\App\Filament\Resources\ClassificationRules\ClassificationRuleResource::canAccess());
    }
}
