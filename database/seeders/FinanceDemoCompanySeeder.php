<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Società dimostrativa «Finance Demo S.r.l.» con organigramma di prova: solo per sviluppo, mai in produzione. */
class FinanceDemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        (new FinanceCompanySeeder)->demo();
    }
}
