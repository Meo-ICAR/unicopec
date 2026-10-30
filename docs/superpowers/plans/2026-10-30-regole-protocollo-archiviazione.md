# Regole, protocollo, archiviazione ed email da unico-core: piano di implementazione

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendere configurabili le regole di classificazione, dare a ogni PEC due numeri di protocollo in registri legati alle funzioni, inviare i protocollati a un servizio di archiviazione (finto per ora) e spedire le email di inoltro con `unico-core`.

**Architecture:** Nuove tabelle e servizi in `app/Services/Protocol` e `app/Services/Archive`, agganciati a `EmailPipeline::routed` e a `EmailHandover::take`. Le regole sostituiscono `EmailClassifier::RULES`. Filament per regole, registri e protocolli. L'invio passa da `Unico\Core\Mail\OutgoingMailer` con modelli `Unico\Core\Models\EmailTemplate`.

**Tech Stack:** Laravel 13, Filament 5, spatie/laravel-medialibrary, PHPUnit su sqlite in memoria (`phpunit.xml`), `meo-icar/unico-core` via repository `path`.

**Spec:** `docs/superpowers/specs/2026-10-30-regole-protocollo-archiviazione-design.md` (incluso «Decisioni prese dopo il controllo del costruttore»).

## Global Constraints

- Comandi artisan e tinker come `sudo -u www-data` sul server; i test con `php artisan test`.
- Tutto rispetta la visibilità per società già presente (`Company`, `RememberLastTenant`).
- Numeri di protocollo: assegnati in transazione con `lockForUpdate` sul contatore; mai modificabili né cancellabili dall'interfaccia; univoci su registro+anno+numero.
- Protocollo di smistamento unico per mail (vincolo email+tipo); un nuovo smistamento lo riusa.
- Pattern regex: massimo 500 caratteri, devono compilare; case-insensitive; corpo = testo senza tag, primi 20.000 caratteri; errore del motore regex = «non corrisponde» più voce di registro.
- Una regola senza condizioni non è valida. Regole di società e globali (`company_id` nullo) si mescolano per priorità (numero più basso vince), a parità vince quella di società.
- Il GET del link di presa in carico non ha effetti; protocolla e conferma solo il POST.
- Niente chiavi nel codice. Con l'organigramma di ripiego (simulazione) non si scrive ai destinatari veri.
- Config: `pec.archive.max_wait_days` default 7; `pec.archive.driver` default `fake`.

## Review Focus

- Regex invalida o catastrofica salvata o eseguita: non deve bloccare la classificazione né farla fallire.
- Due richieste di presa in carico insieme: un solo secondo protocollo, un solo numero consumato.
- Smistamento ripetuto della stessa mail (`mail:route --reassign`): nessun secondo protocollo di smistamento.
- Funzione senza registro (smistamento) o persona senza registri (presa in carico): anomalia e messaggio chiaro, nessun numero consumato.
- Presa in carico dopo che l'archiviazione è già partita: parte l'integrazione, non un duplicato.
- Anno nuovo: la numerazione riparte da 1 per il registro.

---

### Task 1: Dipendenza unico-core

**Files:**
- Modify: `composer.json`
- Test: `tests/Feature/UnicoCoreAvailableTest.php`

**Interfaces:** Produces: le classi `Unico\Core\Mail\OutgoingMailer`, `Unico\Core\Mail\EmailTemplateResolver` e `Unico\Core\Models\{EmailTemplate,MailAccount}` caricabili dall'app.

- [ ] **Step 1: Test** `test_unico_core_classes_are_loadable`: `assertTrue(class_exists(\Unico\Core\Mail\OutgoingMailer::class))` e lo stesso per `EmailTemplateResolver`, `EmailTemplate`, `MailAccount`.
- [ ] **Step 2:** Eseguire `php artisan test --filter=UnicoCoreAvailableTest`. Atteso: FAIL (classe mancante).
- [ ] **Step 3:** Aggiungere in `composer.json` un repository `{"type":"path","url":"../unico-core","options":{"symlink":true}}` e `require` `meo-icar/unico-core: "*@dev"`; `composer update meo-icar/unico-core`. Se il service provider di `unico-core` si auto-registra, verificare con `php artisan about` che non rompa il pannello e che non carichi migrazioni duplicate (tabelle già esistenti: nel caso, disabilitare la pubblicazione/caricamento delle sue migrazioni).
- [ ] **Step 4:** Eseguire l'intera suite `php artisan test`. Atteso: PASS.
- [ ] **Step 5:** Commit `composer.json composer.lock tests/Feature/UnicoCoreAvailableTest.php` con messaggio «Aggiunge unico-core come dipendenza».

---

### Task 2: Regole di classificazione (modello e motore)

**Files:**
- Create: `database/migrations/2026_10_30_100000_create_classification_rules_table.php`, `app/Models/ClassificationRule.php`, `app/Services/Mail/RuleMatcher.php`
- Modify: `app/Services/Mail/EmailClassifier.php`, `app/Services/Mail/Classification.php` (aggiunge `?int $ruleId`), `app/Models/IncomingEmail.php`
- Test: `tests/Unit/RuleMatcherTest.php`, `tests/Feature/EmailClassifierRulesTest.php`

**Interfaces:**
- Produces: tabella `classification_rules` (`id`, `company_id` nullable, `name`, `priority` int default 100, `is_active`, `category` string, `sender_pattern`/`subject_pattern`/`body_pattern` nullable, `operator` `and|or` default `or`, timestamps); colonna `incoming_emails.classification_rule_id` nullable.
- Produces: `ClassificationRule::matchesFor(?int $companyId): Builder` (attive, della società o globali, ordinate per priorità e poi società prima di globali); `RuleMatcher::matches(ClassificationRule $rule, string $from, string $subject, string $body): bool`; `RuleMatcher::firstMatch(IncomingEmail $email): ?ClassificationRule`; `RuleMatcher::validatePattern(string $pattern): ?string` (messaggio d'errore o null).
- `Classification` espone `public ?int $ruleId`.

- [ ] **Step 1: Test unit `RuleMatcherTest`** (senza DB, regole costruite in memoria): `test_or_matches_when_any_condition_matches`, `test_and_requires_all_filled_conditions`, `test_case_insensitive`, `test_empty_rule_never_matches`, `test_invalid_pattern_is_no_match_and_does_not_throw`, `test_validate_pattern_rejects_over_500_chars_and_bad_regex`, `test_body_is_cut_at_20000_chars`.
- [ ] **Step 2: Test feature `EmailClassifierRulesTest`**: `test_company_rule_beats_global_with_same_priority`, `test_lower_priority_number_wins`, `test_inactive_rule_is_skipped`, `test_rule_match_sets_rule_id_and_source_rules` (con `config('pec.ai.key')` vuoto, quindi `enrich` ripiega su urgenza «normale»), `test_no_rule_falls_back_to_ai_path` (con `Http::fake()` e chiave valorizzata), `test_provider_notification_still_wins_first`.
- [ ] **Step 3:** Eseguire i test, atteso FAIL.
- [ ] **Step 4:** Migrazione, modello (`category` castata con `EmailCategory`), `RuleMatcher` (preg con delimitatore sicuro `~...~iu`, `@preg_match` e controllo `preg_last_error`; l'errore viene registrato con `Log::warning` e conta come non corrispondenza), e migrazione dei dati che inserisce le 3 regole attuali (DPO su `@pec\.gpdp\.it$` o testo `\bRPD\b|\bDPO\b|garante per la protezione dei dati`; Fiscale su `(agenziariscossione|agenziaentrate|finanze)\.gov\.it$`; Previdenza su `(postacert\.)?inps\.gov\.it$|inail\.it$`), tutte globali con operatore `or` e priorità 10/20/30.
- [ ] **Step 5:** `EmailClassifier::byRules` usa `RuleMatcher::firstMatch` al posto di `RULES` (si rimuove la costante); `EmailPipeline::classified` salva `classification_rule_id` e il nome della regola nel messaggio dell'evento e in `meta`.
- [ ] **Step 6:** `php artisan test`. Atteso: PASS. Commit.

---

### Task 3: Regole in Filament con «prova la regola»

**Files:**
- Create: `app/Filament/Resources/ClassificationRules/ClassificationRuleResource.php` e le cartelle `Pages`, `Schemas`, `Tables` (stessa struttura di `Users`), `app/Services/Mail/RuleTester.php`
- Test: `tests/Feature/RuleTesterTest.php`, `tests/Feature/ClassificationRuleResourceTest.php`

**Interfaces:**
- Consumes: `RuleMatcher`, `ClassificationRule`.
- Produces: `RuleTester::sample(ClassificationRule $rule, int $limit = 20): Collection<IncomingEmail>` (applica la regola, anche non salvata, alle mail della sua società o a tutte se globale, dalla più recente; esclude le ricevute del gestore).

- [ ] **Step 1: Test** `RuleTesterTest`: `test_returns_only_matching_emails_of_the_rule_company`, `test_does_not_write_anything`, `test_respects_limit`. `ClassificationRuleResourceTest` (Livewire/Filament test): `test_create_rejects_invalid_regex`, `test_create_rejects_rule_without_conditions`, `test_non_admin_sees_only_own_company_rules`.
- [ ] **Step 2:** Eseguire, atteso FAIL.
- [ ] **Step 3:** `RuleTester`; risorsa con campi nome, società (opzionale), priorità, attiva, categoria (`EmailCategory::options()`, escluse `provider_notification`), tre pattern con `rules` che usano `RuleMatcher::validatePattern`, operatore. Validazione «almeno una condizione». Azione di pagina «Prova la regola» che mostra in una modale oggetto, mittente e data delle prime corrispondenze. Voce di menu «Regole di smistamento». Visibilità per società con lo stesso meccanismo delle altre risorse (copiare l'approccio di `IncomingEmailResource`).
- [ ] **Step 4:** `php artisan test`. Atteso PASS. Commit.

---

### Task 4: Registri di protocollo e numerazione

**Files:**
- Create: `database/migrations/2026_10_30_110000_create_protocol_tables.php`, `app/Models/ProtocolRegister.php`, `app/Models/Protocol.php`, `app/Services/Protocol/ProtocolNumberer.php`
- Modify: `app/Models/Employee.php` (relazione), `app/Models/IncomingEmail.php` (`protocols()`)
- Test: `tests/Feature/ProtocolNumbererTest.php`

**Interfaces:**
- Tabelle: `protocol_registers` (`id`, `company_id`, `code` string 10, `name`, `is_active`, unico su company+code); `protocol_register_counters` (`protocol_register_id`, `year`, `last_number`, unico su registro+anno); `protocol_register_roles` (`protocol_register_id`, `role` string = nome in `employee_types`, unico su registro+ruolo); `protocols` (`id`, `protocol_register_id`, `year`, `number`, `identifier`, `incoming_email_id`, `type` `smistamento|presa_in_carico`, `role` nullable, `employee_id` nullable, `parent_protocol_id` nullable, `created_by` nullable, `protocolled_at`, timestamps; unico su registro+anno+numero e su email+tipo).
- Produces: `ProtocolRegister::forRoles(int $companyId, array $roles): Collection` (registri attivi della società collegati ad almeno uno dei ruoli, ordinati per `code`); `ProtocolNumberer::assign(ProtocolRegister $register, IncomingEmail $email, string $type, ?string $role, ?Employee $employee, ?Protocol $parent = null, ?int $userId = null): Protocol` che crea il protocollo con `identifier` `CODICE/ANNO/NNNNNN` (6 cifre), in transazione con blocco sul contatore. `Protocol` è in sola lettura: `updating` e `deleting` lanciano `LogicException`.
- Nota: il collegamento n:m è con il nome del ruolo (come `employees.employee_roles`, che è una colonna JSON di nomi), non con un id.

- [ ] **Step 1: Test** `ProtocolNumbererTest`: `test_numbers_are_sequential_per_register_and_year`, `test_new_year_restarts_at_one` (`Carbon::setTestNow`), `test_identifier_format_is_code_year_six_digits`, `test_two_registers_have_independent_counters`, `test_second_protocol_of_same_type_for_same_email_throws_and_consumes_no_number`, `test_protocol_cannot_be_updated_or_deleted`, `test_for_roles_returns_only_active_registers_of_the_company`.
- [ ] **Step 2:** Eseguire, atteso FAIL.
- [ ] **Step 3:** Migrazione, modelli, `ProtocolNumberer`. Il blocco usa `lockForUpdate` sul contatore (creato con `firstOrCreate` prima); l'eccezione per il vincolo email+tipo annulla la transazione, quindi il numero non si consuma.
- [ ] **Step 4:** `php artisan test`. Atteso PASS. Commit.

---

### Task 5: Protocollo di smistamento e recupero

**Files:**
- Create: `app/Services/Protocol/SmistamentoProtocoller.php`, `app/Console/Commands/ProtocolBackfillCommand.php`
- Modify: `app/Services/Mail/EmailPipeline.php`, `app/Services/Mail/EmailAnomalies.php`, `app/Models/EmailEvent.php` (costante `PROTOCOLLED`)
- Test: `tests/Feature/SmistamentoProtocolTest.php`

**Interfaces:**
- Consumes: `ProtocolNumberer::assign`, `ProtocolRegister::forRoles`, `EmailCategory::role()`.
- Produces: `SmistamentoProtocoller::protocol(IncomingEmail $email): ?Protocol` (idempotente: se esiste già lo restituisce; categoria senza ruolo = null; ruolo senza registro = null più `EmailEvent::ANOMALY` «Nessun registro di protocollo per la funzione X»); comando `protocol:backfill {--limit=200}` per le mail smistate (con `related_id`) e senza protocollo di smistamento.

- [ ] **Step 1: Test:** `test_routed_email_gets_smistamento_protocol_in_first_register_of_the_function`, `test_second_routing_reuses_the_protocol`, `test_category_without_role_gets_no_protocol`, `test_function_without_register_records_anomaly_and_no_number`, `test_backfill_protocols_already_routed_emails`, `test_anomaly_scope_includes_routed_email_without_protocol`.
- [ ] **Step 2:** Eseguire, atteso FAIL.
- [ ] **Step 3:** Implementare e agganciare in `EmailPipeline::routed` subito dopo la scrittura del referente e prima dell'inoltro/evento BPM; un'eccezione del protocollo si cattura, si registra come anomalia e non blocca lo smistamento. Aggiornare `EmailAnomalies::for/scope` con «smistata ma senza protocollo» (solo per categorie con ruolo).
- [ ] **Step 4:** `php artisan test`. Atteso PASS. Commit.

---

### Task 6: Protocollo di presa in carico

**Files:**
- Create: `app/Services/Protocol/PresaInCaricoProtocoller.php`
- Modify: `app/Services/Mail/EmailHandover.php` (`take`), `app/Http/Controllers/TakeEmailController.php`, `resources/views/pec/take.blade.php`, `app/Filament/Resources/IncomingEmails/Tables/IncomingEmailsTable.php` (azione `presa_in_carico`)
- Test: `tests/Feature/PresaInCaricoProtocolTest.php`

**Interfaces:**
- Produces: `PresaInCaricoProtocoller::registersFor(IncomingEmail $email, Employee $employee): Collection` (unione dei registri attivi della società della mail collegati ai ruoli in `employee_roles` della persona); `EmailHandover::take(IncomingEmail $email, Employee $employee, string $via = 'link', ?int $registerId = null, ?int $userId = null): array` ora restituisce anche `protocol` (`?Protocol`) e `error` (`?string`). Il registro è obbligatorio se `registersFor` non è vuoto per il link; per il pannello (amministratore) vale qualsiasi registro attivo della società.
- Il secondo protocollo ha `parent_protocol_id` = quello di smistamento (se c'è) e `employee_id`/`role` della persona.

- [ ] **Step 1: Test:** `test_post_with_chosen_register_takes_charge_and_creates_second_protocol`, `test_get_does_not_take_charge_or_consume_number`, `test_single_register_is_preselected_in_view`, `test_register_not_allowed_for_the_person_is_rejected`, `test_person_without_registers_gets_message_and_nothing_changes` (nessun `taken_at`, nessun numero), `test_concurrent_second_take_returns_taken_by_other_and_one_protocol_only`, `test_admin_from_panel_can_choose_any_company_register`.
- [ ] **Step 2:** Eseguire, atteso FAIL.
- [ ] **Step 3:** Implementare; il protocollo si crea nella stessa transazione di `take` (dopo il controllo `taken_at`). La vista mostra un `<select name="register_id">` per il POST. Mantenere il GET senza effetti.
- [ ] **Step 4:** `php artisan test`. Atteso PASS. Commit.

---

### Task 7: Registri e protocolli in Filament

**Files:**
- Create: `app/Filament/Resources/ProtocolRegisters/ProtocolRegisterResource.php` (+ `Pages`, `Schemas`, `Tables`), `app/Filament/Resources/Protocols/ProtocolResource.php` (sola consultazione)
- Test: `tests/Feature/ProtocolRegisterResourceTest.php`

- [ ] **Step 1: Test:** `test_register_form_requires_code_name_company_and_unique_code_per_company`, `test_register_links_roles_from_employee_types`, `test_protocol_list_is_read_only_and_company_scoped`.
- [ ] **Step 2:** Eseguire, atteso FAIL.
- [ ] **Step 3:** Risorsa registri con campo multi-selezione dei ruoli (nomi da `employee_types`, per la società d'organigramma) salvati in `protocol_register_roles`; risorsa protocolli senza create/edit/delete, con filtri per registro, anno, tipo. Nel dettaglio di una mail, sezione con i suoi protocolli (relation manager sola lettura, accanto a `EventsRelationManager`).
- [ ] **Step 4:** `php artisan test`. Atteso PASS. Commit.

---

### Task 8: Archiviazione a norma

**Files:**
- Create: `database/migrations/2026_10_30_120000_add_archive_to_incoming_emails.php`, `app/Services/Archive/ArchiveService.php` (interfaccia), `app/Services/Archive/FakeArchiveService.php`, `app/Services/Archive/ArchivePackage.php`, `app/Jobs/ArchiveEmail.php`, `app/Console/Commands/ProtocolArchivePendingCommand.php`
- Modify: `config/pec.php` (blocco `archive`), `app/Providers/AppServiceProvider.php` (binding per driver), `routes/console.php` (schedule giornaliero), `app/Services/Protocol/PresaInCaricoProtocoller.php` o `EmailHandover::take` (dispatch), `app/Services/Mail/EmailAnomalies.php`, `app/Models/EmailEvent.php` (`ARCHIVED`)
- Test: `tests/Feature/ArchiveTest.php`

**Interfaces:**
- Colonne su `incoming_emails`: `archive_status` (`da_archiviare|archiviata|errore`, nullo = non applicabile), `archive_reference`, `archived_at`, `archive_attempts` int default 0.
- `interface ArchiveService { public function send(ArchivePackage $package): string; }` (restituisce l'identificativo; lancia in caso di errore). `ArchivePackage`: `emailId`, `files` (array di `name`, `sha256`, `path`), `metadata` (array con i protocolli, mittente, oggetto, date, `eml_available` bool), `supplement` bool; costruito da `ArchivePackage::for(IncomingEmail $email, bool $supplement): self`.
- `ArchiveEmail` (job, `tries` 5, attesa crescente): se `archive_status` è `archiviata` e non c'è un protocollo di presa in carico nuovo, non fa nulla; altrimenti invia e aggiorna lo stato; l'integrazione si invia con `supplement: true` quando l'email è già archiviata e compare il protocollo di presa in carico. Dopo l'ultimo fallimento: stato `errore` e `EmailEvent::ANOMALY`.
- `protocol:archive-pending {--days=}` archivia le mail con il solo protocollo di smistamento più vecchie di `pec.archive.max_wait_days`.

- [ ] **Step 1: Test:** `test_package_contains_sha256_of_every_attachment`, `test_package_declares_missing_eml_without_failing`, `test_take_dispatches_archive_job_and_marks_archiviata_with_reference`, `test_failure_after_max_tries_sets_errore_and_records_anomaly`, `test_pending_command_archives_old_smistamento_only_emails`, `test_late_take_after_archive_sends_supplement_once`, `test_fake_service_sends_nothing_outside_the_app` (`Http::fake()` + `Http::assertNothingSent()`).
- [ ] **Step 2:** Eseguire, atteso FAIL.
- [ ] **Step 3:** Implementare; il binding sceglie la classe da `config('pec.archive.driver')`, con `fake` unico driver esistente. Pianificare il comando una volta al giorno.
- [ ] **Step 4:** `php artisan test`. Atteso PASS. Commit.

---

### Task 9: Inoltro con modelli e invio da unico-core

**Files:**
- Create: `database/migrations/2026_10_30_130000_seed_forward_email_template.php`, `app/Services/Mail/ForwardMessageFactory.php`
- Modify: `app/Services/Mail/EmailForwarder.php`, `app/Services/Mail/EmailHandover.php`, `app/Mail/ForwardedPec.php` (accetta oggetto e corpo già risolti, mantiene gli allegati)
- Test: `tests/Feature/ForwardWithTemplateTest.php`

**Interfaces:**
- Consumes: `EmailTemplateResolver::resolve(string $code, $companyId, ?string $app, $type, ?string $severity)`, `OutgoingMailer::send(Unico\Core\Models\MailAccount $account, string $to, Mailable $mailable, ?string $fromName)`.
- Produces: `ForwardMessageFactory::make(IncomingEmail $email, Employee $employee, ?string $banner, ?string $role, ?string $takeUrl, ?string $note): ForwardedPec` che risolve il template con codice `pec.inoltro`, `app_identifier` `unicopec`, e sostituisce i segnaposto `{oggetto}`, `{mittente}`, `{categoria}`, `{ruolo}`, `{protocollo}`, `{link_presa_in_carico}`, `{nota}`, `{banner}`. Senza template o senza casella con SMTP: ripiego sul testo e sull'invio attuali (`Mail::to`).
- La casella di invio è quella della società con SMTP configurato, caricata come `Unico\Core\Models\MailAccount` dallo stesso id; la simulazione continua a decidere il destinatario prima dell'invio.

- [ ] **Step 1: Test:** `test_uses_active_template_with_placeholders_replaced` (incluso il numero di protocollo), `test_falls_back_to_default_text_without_template`, `test_company_template_beats_global`, `test_simulation_never_writes_to_real_referent` (destinatario = `pec.forward.simulation_to`), `test_sends_through_core_mailer_when_company_has_smtp_account` (mock di `OutgoingMailer`), `test_attachments_still_follow_size_limit`.
- [ ] **Step 2:** Eseguire, atteso FAIL. Per i test servono le tabelle `email_templates`/`mail_accounts` di `unico-core` in sqlite: creare un helper di test che le crea con le colonne minime usate dal resolver.
- [ ] **Step 3:** Implementare `ForwardMessageFactory` e usarlo in `EmailForwarder::forward` e `EmailHandover::send`; la migrazione inserisce il modello `pec.inoltro` con `app_identifier` `unicopec`, `company_id` nullo, con il testo equivalente a quello attuale di `emails.forwarded-pec`.
- [ ] **Step 4:** `php artisan test`. Atteso PASS. Commit.

---

### Task 10: Manuali e verifica finale

**Files:**
- Modify: `resources/manuals/manuale-utente.html`, `resources/manuals/manuale-tecnico.html`

- [ ] **Step 1:** Aggiungere ai manuali le sezioni su regole, registri, doppio protocollo, archiviazione e modelli di email (l'assistente AI li legge, quindi vanno aggiornati).
- [ ] **Step 2:** Su un database di prova: `php artisan migrate`, creare un registro e una regola da Filament, eseguire `mail:classify`, `mail:route`, aprire il link di presa in carico e controllare i due protocolli e lo stato di archiviazione.
- [ ] **Step 3:** `php artisan test` per l'intera suite. Atteso: tutto PASS. Commit.

---

## Self-review

- **Copertura:** regole (T2, T3), registri n:m (T4, T7), due protocolli (T5, T6), archiviazione e integrazione (T8), unico-core e modelli (T1, T9), manuali (T10). Fuori perimetro come da spec.
- **Coerenza dei tipi:** `ProtocolNumberer::assign`, `SmistamentoProtocoller::protocol`, `PresaInCaricoProtocoller::registersFor`, `EmailHandover::take` e `ArchivePackage::for` sono usati con le stesse firme nei task successivi.
- **Rischio principale:** T1 (l'autoload e il service provider di `unico-core` potrebbero registrare migrazioni o risorse Filament che confliggono). Si verifica per primo.
