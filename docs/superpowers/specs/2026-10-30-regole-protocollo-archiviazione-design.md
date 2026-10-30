# Regole configurabili, protocollo a più registri, archiviazione a norma, email da unico-core

Data: 2026-10-30. App: UnicoPEC (Laravel 13, Filament 5). Nasce dal confronto con MailEDOC e MailDoc Pro (punti 1, 3 e 4 della lista «cosa conviene fare»; il punto 2, lettura degli allegati con AI/OCR, lo fa `unico-core`).

## Perché

Oggi le regole di classificazione sono costanti nel codice (`EmailClassifier::RULES`, 3 regole) e per cambiarle serve un rilascio. Non c'è protocollo né archiviazione a norma, e le email che UnicoPEC invia (inoltro al referente) sono costruite a mano. Si vuole che l'amministratore gestisca le regole da interfaccia, che ogni PEC abbia un numero di protocollo in registri legati alle funzioni aziendali, e che i documenti protocollati vadano a un servizio di conservazione.

## Obiettivi

1. Regole di classificazione salvate nel database, per società, gestibili da Filament, con prova su mail già ricevute.
2. Più registri di protocollo, collegati n:m alle funzioni aziendali, con numerazione progressiva per registro e anno.
3. Due protocolli per ogni mail: uno automatico allo smistamento, uno alla presa in carico.
4. Invio dei documenti protocollati a un servizio di archiviazione a norma, dietro un'interfaccia sostituibile; per ora una implementazione finta.
5. Le email inviate da UnicoPEC (inoltro al referente e notifiche di protocollo) usano `unico-core`: modelli `email_templates` e `OutgoingMailer`.

## Non obiettivi

- Lettura degli allegati con AI/OCR e anteprima di p7m/XML: la fa `unico-core`.
- Composizione e invio di PEC nuove, e pulsante «Rispondi» (scelta mia: fuori perimetro; ci pensa `unico-core`).
- Un servizio di conservazione vero: oggi non ce n'è uno; si costruisce solo l'interfaccia e un finto.
- Un'API esposta a servizi esterni per l'archiviazione: è UnicoPEC che invia, non viene interrogato.
- Regole che assegnano una persona precisa: assegnano solo una funzione.
- Protocollo in uscita.

## Anti-obiettivi (sarebbe un fallimento anche se funziona)

- Numeri di protocollo duplicati, con buchi non spiegati, o modificabili dopo l'assegnazione.
- Una regola sbagliata che manda mail in silenzio alla funzione sbagliata senza lasciare traccia: ogni classificazione registra quale regola è scattata.
- Il link di presa in carico che protocolla perché un antivirus ha aperto la pagina (già oggi solo il POST conferma: va mantenuto).

## Regole di classificazione

- Tabella `classification_rules` (per `company_id`; scelta mia: `company_id` nullo = valida per tutte): nome, priorità (numero più basso vince), attiva, categoria assegnata (una delle 14 di `EmailCategory`), condizioni su mittente, oggetto e corpo (ognuna facoltativa, pattern regex), operatore tra le condizioni compilate (**AND** o **OR**, scelto per regola).
- Le condizioni sono regex PHP. Il form le valida (compilano, sono limitate in lunghezza) per evitare pattern invalidi o catastrofici.
- La funzione competente resta quella della categoria (`EmailCategory::role()`): la regola sceglie la categoria, non la persona né il ruolo direttamente. Il referente lo sceglie l'organigramma come oggi (`EmailRouter`).
- Ordine di valutazione: ricevute del gestore PEC, poi le regole del database in ordine di priorità, poi l'AI. Le tre regole attuali vengono migrate nel database come regole di partenza, con le stesse condizioni.
- Resta il comportamento attuale: se una regola scatta, l'AI aggiunge solo urgenza e sintesi, e il metodo resta `rules`.
- Quando scatta una regola se ne registra l'id nell'email (e nel registro eventi), per spiegare perché.
- «Prova la regola»: dal form si applica la regola (anche non salvata) alle mail già ricevute della società e si vedono le prime corrispondenze, senza modificare niente.
- Gestione in Filament, con la stessa visibilità per società delle altre risorse.

## Registri di protocollo

- `protocol_registers`: società, codice (breve, es. `FIS`), nome, attivo. Un contatore per anno: `protocol_register_counters` (registro, anno, ultimo numero).
- `protocol_register_roles` (n:m): registro ↔ funzione aziendale (`employee_types`).
- `protocols`: registro, anno, numero, identificativo leggibile (es. `FIS/2026/000045`), email, tipo (`smistamento` o `presa_in_carico`), funzione, persona (per la presa in carico), data/ora, utente o link che l'ha generato. Univoco su registro+anno+numero. Dopo la creazione il record non è modificabile né cancellabile dall'interfaccia.
- Il numero si assegna in transazione con blocco sul contatore. In caso di errore la transazione si annulla e non si consumano numeri.
- Gestione in Filament: registri con le funzioni collegate, e consultazione dei protocolli.

## Due protocolli per mail

1. **Smistamento (automatico).** Appena `EmailRouter` ha trovato il referente, si protocolla in entrata nel registro collegato alla funzione competente della categoria. Se quella funzione ha più registri si usa il primo attivo per codice (scelta mia). Se non ne ha nessuno, nessun protocollo e un'anomalia visibile («nessun registro per la funzione X»). Le categorie senza ruolo (ricevute, non rilevante) non si protocollano.
2. **Presa in carico (scelta della persona).** Nella pagina del link firmato (`TakeEmailController`, vista `pec.take`) la persona sceglie il registro tra quelli collegati alla propria funzione (o alle funzioni che ricopre, se più di una). Se ce n'è uno solo è preselezionato. Se non ce n'è nessuno la presa in carico si ferma con un messaggio che rimanda all'amministratore. Il POST registra la presa in carico e crea il secondo protocollo nella stessa transazione. Il GET resta senza effetti.
3. Il secondo protocollo è collegato al primo. La presa in carico dall'interno di Filament (azione `presa_in_carico`) funziona allo stesso modo, con la scelta del registro nel modulo.
4. Le anomalie (`EmailAnomalies`) segnalano le mail smistate ma senza protocollo.

## Archiviazione a norma

- Interfaccia `ArchiveService` con un metodo di invio che restituisce l'identificativo dato dal servizio; implementazione `FakeArchiveService` per ora (non esce nulla dall'app, genera un identificativo finto e registra l'esito). L'implementazione si sceglie da configurazione.
- Un job in coda per ogni protocollo di smistamento: prepara il pacchetto (l'`.eml` originale se disponibile, gli allegati dalla media library, i metadati dei due protocolli, l'impronta SHA-256 di ogni file) e lo passa al servizio. Il pacchetto parte dopo il protocollo di smistamento; se nel frattempo c'è il protocollo di presa in carico va incluso. Scelta mia: si archivia una sola volta alla presa in carico, oppure dopo un tempo massimo se nessuno la prende (da decidere in implementazione, vedi sotto).
- Esito sull'email: stato (`da archiviare`, `archiviata`, `errore`), identificativo, data. Tentativi ripetuti con attesa crescente; dopo l'ultimo fallimento compare tra le anomalie e nel registro eventi, come per gli eventi BPM.

## Email da unico-core

- UnicoPEC aggiunge `unico-core` come dipendenza di Composer (oggi non c'è) e ne usa `EmailTemplateResolver`/`EmailTemplate` e `OutgoingMailer`.
- Le email che UnicoPEC già manda (inoltro al referente, `ForwardedPec`) diventano modelli `email_templates` con `app_identifier` di UnicoPEC e segnaposto (mittente, oggetto, categoria, link di presa in carico, nota, numero di protocollo). Si seedano modelli di partenza equivalenti al testo attuale.
- Mittente scelto con la logica di `unico-core` (casella per società e tipo); se non c'è, si ripiega sul comportamento attuale.
- Resta la regola esistente: con l'organigramma di ripiego (simulazione) non si scrive ai destinatari veri.

## Vincoli

- Stack esistente: Laravel 13, Filament 5, spatie/laravel-medialibrary, MySQL. Test con PHPUnit.
- Multi-società: tutto ciò che è nuovo rispetta la visibilità per società già presente.
- Comandi artisan come `sudo -u www-data`.
- Niente chiavi o credenziali nel codice; il servizio di archiviazione reale, quando ci sarà, avrà le sue credenziali in configurazione per società.

## Lasciato a chi costruisce

Nomi di tabelle e classi, ordine delle migrazioni, struttura dei form Filament, formato esatto del pacchetto di archiviazione, politiche di ripetizione del job, copertura dei test.

## Decisioni prese dopo il controllo del costruttore (tutte mie scelte)

- **Quando archiviare.** Alla presa in carico. Un comando pianificato ogni giorno (`protocol:archive-pending`) archivia le mail con il solo protocollo di smistamento più vecchie di `pec.archive.max_wait_days` (default 7). Se la presa in carico arriva dopo l'archiviazione, parte un invio di integrazione (`supplement: true`) con il secondo protocollo; `ArchiveService` deve accettarlo.
- **Idempotenza del protocollo di smistamento.** Unico per mail (vincolo su email+tipo). Il protocollo si crea in `EmailPipeline::routed` dopo la scrittura del referente; se fallisce si registra l'anomalia e lo ritenta il comando `protocol:backfill`, che serve anche per le mail già smistate prima di questa funzione. Un nuovo smistamento della stessa mail riusa il protocollo esistente.
- **Anno e registro.** L'anno è quello della data del protocollo. Un registro appartiene a una società; la numerazione è per registro e anno. Si usano i registri della società della mail (non quelli di altre società).
- **Presa in carico con più funzioni.** La persona sceglie tra l'unione dei registri collegati alle funzioni che ricopre (`employee_roles`), nella società della mail; non deve coincidere con la funzione della categoria. Dal pannello un amministratore può prendere in carico per conto di un referente scegliendo un qualsiasi registro della società.
- **Nessun registro attivo alla presa in carico.** Dalla pagina pubblica la presa in carico si ferma con un messaggio. Dal pannello l'amministratore sceglie tra tutti i registri attivi della società. Se un registro viene disattivato tra i due passi vale lo stato al momento della conferma.
- **Regole.** Regex case-insensitive su oggetto e corpo (testo senza tag, primi 20.000 caratteri); il mittente si confronta con l'indirizzo. Regole di società e globali si mescolano per priorità, a parità vince quella di società. Una regola senza nessuna condizione non è valida. Ogni pattern: massimo 500 caratteri, deve compilare, e un errore del motore regex in esecuzione conta come «non corrisponde» e viene registrato.
- **unico-core.** Dipendenza Composer con repository `path` verso `../unico-core`. `email_templates` e `mail_accounts` stanno nello stesso database; l'invio carica la casella come `Unico\Core\Models\MailAccount` dallo stesso id. La protezione per la simulazione resta in UnicoPEC. Le «notifiche di protocollo» sono tolte dal perimetro: l'unico modello è quello dell'inoltro al referente, che include il numero di protocollo.
- **Pacchetto di archiviazione.** Se l'`.eml` originale non c'è si omette e lo si dichiara nei metadati; non è un errore.
