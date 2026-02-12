# Changelog

Tutti i cambiamenti significativi a questo progetto saranno documentati in questo file.

Il formato è basato su [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
e questo progetto aderisce a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.4] - 2026-02-12

### Corretto
- Risolto errore fatale PHP causato da costante non definita durante l'inizializzazione del sistema di aggiornamento.
- Ottimizzata l'inizializzazione del GitHub Updater nel file principale del plugin.

## [1.0.3] - 2026-02-12

### Corretto
- Riscritto meccanismo di aggiornamento GitHub utilizzando file JSON remoto.
- Aggiunta gestione della cache per le richieste di aggiornamento.
- Implementato controllo forzato degli aggiornamenti con pulsante nella lista plugin.
- Aggiunta correzione automatica della cartella plugin durante l'aggiornamento.

## [1.0.2] - 2026-02-12

### Corretto
- Risolto problema di rilevamento degli aggiornamenti (GitHub Updater) per includere correttamente lo slug del plugin.
- Aggiunto supporto per la visualizzazione del link "Abilita aggiornamento automatico" nella lista plugin.
- Migliorato oggetto di risposta dell'aggiornamento con icone e banner.
- Risolto problema con il popup dei dettagli dell'aggiornamento.

## [1.0.1] - 2026-02-12

### Modificato
- Rinominato file principale plugin da `marrison-master.php` a `wp-master-updater.php` per coerenza.
- Aggiornati namespace API e slug interni da `marrison-master` a `wp-master-updater`.
- Corretti endpoint API per la comunicazione con WP Agent Updater (da `marrison-agent` a `wp-agent-updater`).
- Aggiornati riferimenti documentazione e UI.

## [1.0.0] - 2024-02-12

### Aggiunto
- Versione iniziale del plugin Marrison Master
- Dashboard principale per la gestione multi-sito
- Sistema di indicatori LED per lo stato dei client (verde, giallo, rosso, nero)
- Operazioni di sincronizzazione remota con i client
- Sistema di aggiornamento centralizzato per plugin, temi e traduzioni
- Gestione completa dei backup con ripristino remoto
- Supporto per repository privati di plugin e temi
- Operazioni di gruppo (sync massiva, aggiornamento massivo)
- Interfaccia di dettaglio per ogni client
- Sistema di notifiche e messaggi di stato
- Pulsante di refresh forzato della cache repository
- Protezione contro operazioni duplicate con disabilitazione bottoni
- Auto-sync dopo operazioni di ripristino
- Gestione degli errori con messaggi dettagliati
- Supporto per la gestione dei plugin disattivati
- Logica di priorità per indicatori di stato (nero > rosso > giallo > verde)

### Modificato
- Migliorata la gestione delle versioni PHP nel core di aggiornamento
- Ottimizzata la gestione degli URL di download con sanitizzazione
- Migliorata la gestione degli errori di rete e timeout
- Ottimizzate le prestazioni per operazioni bulk

### Corretto
- Risolto problema di aggiornamento con versioni PHP richieste troppo alte
- Corretto gestione URL di download malformati
- Risolto problema di visualizzazione dopo pulizia cache
- Corretto conteggio client per operazioni di gruppo
- Risolti vari bug di compatibilità con diverse versioni di WordPress

### Sicurezza
- Implementata validazione rigorosa dei dati in entrata
- Aggiunti nonce per tutte le operazioni AJAX
- Implementato controllo dei permessi basato sui ruoli WordPress
- Sanitizzazione di tutti i dati di output

## [Pre-1.0.0] - Fasi di sviluppo iniziali

Le versioni precedenti alla 1.0.0 erano fasi di sviluppo e test interno.

---

## Come aggiornare

Per aggiornare il plugin:

1. **Backup**: Crea sempre un backup prima di aggiornare
2. **Download**: Scarica la nuova versione dal [repository GitHub](https://github.com/marrisonlab/wp-master-updater)
3. **Installazione**: Sostituisci i file del plugin con la nuova versione
4. **Test**: Verifica che tutto funzioni correttamente
5. **Sync**: Esegui una sincronizzazione completa con tutti i client

## Segnalazione problemi

Se incontri problemi con questo plugin:

1. Verifica di avere la versione più recente
2. Controlla i requisiti di sistema
3. Consulta la [documentazione](README.md)
4. Apri una [issue su GitHub](https://github.com/marrisonlab/wp-master-updater/issues)

---

**Autore**: Angelo Marra  
**Sito**: [marrisonlab.com](https://marrisonlab.com)  
**Repository**: [marrisonlab/wp-master-updater](https://github.com/marrisonlab/wp-master-updater)