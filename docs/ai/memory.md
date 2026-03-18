# AI Memory

Project memory for recurring pitfalls, decisions, and proven fixes.

## How to use
- Add one entry per incident/decision.
- Keep it short, factual, and actionable.
- Prefer prevention rules over long narratives.
- Update this file immediately after a bug is diagnosed/fixed.

## Entry template

```md
## YYYY-MM-DD - Short title
- Symptom:
- Root cause:
- Fix:
- Prevention:
- Links: (optional)
```

## Incident Log

## 2026-03-18 - Internal Fallout Wiki name contradictions should downgrade that provider for merge
- Symptom: after the latest import enrichment, three previously resolved name conflicts (`Deep pocketed metal armor chest`, `Bladed Commie Whacker`, `Vault 63 Jumpsuit`) came back in `source-merge`.
- Root cause: some `fallout.wiki` rows carry two materially different names at once (`resource.name` and `columns.name`). Preserving only one imported name without tracking the contradiction made the merge policy trust an internally inconsistent source again.
- Fix: the filesystem reader now stores the divergent column value in `source_name_raw`, and the merge policy prefers the other provider for `name` / `name_en` whenever one source shows that internal contradiction.
- Prevention: when a third-party row contains contradictory naming signals, keep both for audit and treat that source as lower-trust for merged names instead of forcing one arbitrary “canonical” value too early.

## 2026-03-18 - Admin merge-status filters should reuse the PHP merge policy, not reimplement it in SQL
- Symptom: adding a back-office filter for `aligned` / `generic_label` / `source_issue` / `material_conflict` could tempt us to encode the whole merge policy in Doctrine queries.
- Root cause: merge status is derived from `ItemSourceMergePolicy` reasons and conflicts, not from one stable persisted column.
- Fix: the admin catalog list keeps the existing paginated SQL path by default, and only switches to a full admin read + in-memory filtering when a merge-status filter is explicitly selected.
- Prevention: for internal screens that filter on computed merge semantics, prefer one trusted policy implementation plus an explicit in-memory path over duplicating the policy in brittle SQL conditions.

## 2026-03-18 - Derive wiki booleans from observed raw labels, not copied website vocabulary
- Symptom: admin/source-merge screens could show `obtained` labels that clearly implied an acquisition path (`Enemy Drop`, `Spawned`, `Merchants`) while the derived boolean flags still showed `false`.
- Root cause: the initial `fallout_wiki` mapping covered only a subset of labels seen in the real JSON snapshots, and some manual vocabulary examples had mixed in alt-text/site artifacts instead of the exact stored values.
- Fix: the import enricher now recognizes the observed aliases from local snapshots, including `Enemy Drop`, `Spawned`, `Merchants`, `Quests`, `Containers`, and `Fallout 76 Limited Time Content`.
- Prevention: when refining source mappings, extract the vocabulary from committed raw snapshots first and only then update canonical flag derivation.

## 2026-03-18 - Add new canonical acquisition flags only from generic raw labels first
- Symptom: the `fallout_wiki.obtained` residual backlog still contained useful concepts like `Overview:Unused Content`, but many other remaining labels were specific NPC/event names that would be risky to normalize too aggressively.
- Root cause: once a raw vocabulary report exists, it is tempting to map everything left in one pass even when some labels are generic taxonomy and others are one-off content references.
- Fix: `unused_content` was introduced only from clear generic labels (`Overview:Unused Content`, `Unused Content`, `Fallout 76 Unused Content`) and then propagated through merge/reporting like the other canonical flags.
- Prevention: extend canonical taxonomy incrementally from generic, repeatable source labels first; keep named NPCs/activities in raw metadata until there is an explicit product need for a new canonical concept.

## 2026-03-18 - Generic activity markers sometimes need token matching, not exact-label matching
- Symptom: raw `fallout_wiki.obtained` labels such as `Gleaming Depths (Raid)` and `Raid: Gleaming Depths` clearly described raid content, but an exact-label matcher would miss them unless every wording variant was enumerated.
- Root cause: some source labels encode the same taxonomy concept as a reusable token inside longer titles rather than as one stable exact string.
- Fix: the importer now derives `raid` through normalized token matching, so both `Raid: ...` and `... (Raid)` feed the same canonical flag without cataloguing every concrete raid title.
- Prevention: when a concept appears as a stable token embedded in multiple labels, prefer normalized token matching over brittle exact-string lists.

## 2026-03-18 - Prefer generic category tokens over named activities for new expedition taxonomy
- Symptom: the `fallout_wiki.obtained` backlog contained both a reusable category marker (`Expeditions: The Pitt`, `Expeditions Giuseppe`) and many named expedition activities (`Tax Evasion`, `The Human Condition`) that would be unsafe to normalize wholesale.
- Root cause: expedition-related labels mix stable taxonomy prefixes with one-off mission names and vendor references.
- Fix: `expeditions` was introduced only from the generic `Expeditions` token, leaving named mission labels in raw metadata until there is an explicit need to classify them further.
- Prevention: when a source blends category labels with named activities, normalize the shared category token first and keep the named content raw until the product model needs a finer distinction.

## 2026-03-18 - Fallout Wiki `{text, icons}` payloads should trust `icons` over concatenated text
- Symptom: the vocabulary audit still surfaced garbage labels like `ContainersTreasure MapsQuestsMerchantsWorld spawns` and `Tax EvasionGiuseppe`, even though the same payload already exposed clean `icons` arrays.
- Root cause: label extraction recursively consumed both `text` and `icons`, so badly concatenated fallback text polluted taxonomy and hid the more trustworthy structured labels.
- Fix: Fallout Wiki label extraction now prefers `icons` whenever they are present, falls back to `text` only when no icons exist, and uses those clean labels to derive named vendor currencies (`Giuseppe` -> `stamps`, `Regs`/`Minerva`/`Reginald Stone` -> `gold_bullion`) plus expedition hints (`Tax Evasion`, `The Human Condition`, `The Most Sensational Game`, `Atlantic City`, `The Pitt`).
- Prevention: when a third-party payload provides both a human-readable fallback string and a structured label/icon list, use the structured list as the canonical extraction source and treat the raw text as fallback only.

## 2026-03-18 - Concatenated fallback text should be split only with a bounded glossary
- Symptom: some `fallout_wiki.obtained.text` values still appeared as merged strings (`ContainersWorld spawns`, `Tax EvasionGiuseppe`) when no structured `icons` array was available.
- Root cause: certain source rows expose only a concatenated fallback string, so preferring `icons` is not enough to keep the vocabulary audit readable.
- Fix: the vocabulary report now splits text-only obtained values with a bounded glossary of observed segments, while the importer separately recognizes the safe named aliases that carry real business meaning (`Union Dues`, `Samuel (Wastelanders)`, `Bullion vendors`).
- Prevention: when repairing concatenated third-party text, use a short, explicit glossary tied to observed source data instead of generic NLP-style splitting.

## 2026-03-18 - Fallout Wiki recipe rows must keep anchor href and dedupe by form_id
- Symptom: `fallout.wiki` recipes with the same visible label (for example `Recipe: Healing Salve`) collapsed into one JSON row, and the stored `wiki_url` pointed to a generic 404 page instead of the variant page.
- Root cause: the sync command built `slug` and fallback `wiki_url` from the visible `name`, while deduplication keyed rows by `type|slug`; generic labels therefore overwrote distinct variants even when each row had its own anchor `href` and `form_id`.
- Fix: the Fallout Wiki sync now extracts `wiki_url` and `source_slug` from the row anchor `href`, derives the resource slug from that source slug when available, and deduplicates primarily by `form_id`.
- Prevention: for wiki tables, never infer page identity only from visible cell text when the row already exposes a specific linked target and technical identifier.

## 2026-03-18 - Arbitration reports should separate generic labels from material source conflicts
- Symptom: after fixing specific Fallout Wiki links, `Healing Salve` still appeared as a standard name conflict even though both sources targeted the same region-specific record and only one label stayed generic.
- Root cause: the arbitration report classified all differing visible names as conflicts, without distinguishing a generic label confirmed by a specific target URL/form_id from a real naming disagreement.
- Fix: the report now emits `provider_*_generic_label_confirmed` verdicts and exposes `generic_label_items` vs `material_conflict_items` counts.
- Prevention: when one provider keeps a generic visible label but the stored source URL and `form_id` are specific, classify it as a resolved labeling issue rather than a true source conflict.

## 2026-03-18 - Merge policy should retain specific names but tag generic-label resolutions explicitly
- Symptom: `source-merge` and `source-merge-summary` still treated resolved generic-label cases as plain `specific_variant_preferred`, which hid the fact that the losing source was still technically aligned through a specific target URL.
- Root cause: the merge policy only looked at visible names and parenthetical variants, not at whether the generic source already carried a specific linked target.
- Fix: the merge policy now emits `generic_label_confirmed_by_specific_target` when it keeps the specific name while the generic source URL is already variant-specific; merge reports/summaries count these cases separately from material conflicts.
- Prevention: when a merge keeps the more specific label but both sources already point to the same specific target, store that as an explicit resolution reason instead of a generic variant preference.

## 2026-03-17 - Multi-source raw sync should always expose a provider index and visible progress
- Symptom: `app:data:sync` felt inconsistent because Fandom/Fallout Wiki produced `index.json` while Nukaknights only wrote raw files, and long external syncs could look stalled in the terminal.
- Root cause: Nukaknights kept its older endpoint-per-file flow without the same catalog summary and progress conventions as the newer wiki sources.
- Fix: `app:data:sync` now writes `data/sources/nukaknights/index.json` and prints explicit per-dataset progress (`Legendary mods`, `Minerva`) during the sync.
- Prevention: every raw sync source should ship with both an operator-readable progress output and a machine-readable provider index for downstream tooling.

## 2026-03-17 - External page sync should degrade to partial success instead of dropping the whole batch
- Symptom: a single upstream Fandom 502 on one page could abort the whole sync, even though previous pages had already been scraped successfully.
- Root cause: the page loop was wrapped in one global `try/catch`, so one remote page failure stopped index generation and hid successful partial work.
- Fix: Fandom and `fallout.wiki` syncs now catch failures per page, preserve successful page files, write a partial `index.json` with `page_errors`, and support targeted reruns via `app:data:sync --only=<provider> --<provider>-page='...'`.
- Prevention: for third-party multi-page syncs, isolate failures per page/resource and always emit a machine-readable partial summary.

## 2026-03-17 - Do not run integration and functional suites in parallel against the same test database
- Symptom: functional tests exploded with missing tables such as `player_item_knowledge`, `contact_message`, and `auth_audit_log`, even though migrations were present and the suite passed when rerun alone.
- Root cause: integration and functional Make targets both drop/create/migrate the shared `app_test` database, so running them in parallel creates race conditions and leaves one suite observing a half-reset schema.
- Fix: rerun `make phpunit-functional` alone; the suite passed fully once no other test target was mutating the same test database.
- Prevention: never run `make phpunit-integration` and `make phpunit-functional` concurrently unless test DB isolation is introduced first.

## 2026-03-16 - After dual-write stabilization, remove legacy source columns quickly
- Symptom: keeping both `item_external_source` and legacy source columns in `item` prolongs drift risk and duplicate truth.
- Root cause: split migrations often stop at dual-write, leaving cleanup postponed indefinitely.
- Fix: added follow-up migration dropping `item.form_id`, `item.editor_id`, `item.wiki_url`, `item.tradeable`, `item.payload` once backfill + dual-write were validated.
- Prevention: for core/source data split, always plan cleanup migration in the same delivery window after tests pass.

## 2026-03-16 - Multi-source catalog migration should use dual-write first
- Symptom: moving source-specific fields out of `item` in one shot would risk broad regressions across import/read flows.
- Root cause: existing import and UI still read legacy columns while new source metadata model is being introduced.
- Fix: implemented `item_external_source` with migration backfill and import dual-write, while keeping legacy columns temporarily.
- Prevention: for schema separations of core vs provider data, always sequence as: create new store -> backfill -> dual-write -> switch reads -> cleanup.

## 2026-03-16 - Catalog multi-source requires source metadata model, not item table growth
- Symptom: adding Fandom fields exposed schema pressure (`wiki_url`, currencies, availability flags, tags) and upcoming Nukacrypt integration would add more source-specific attributes.
- Root cause: `item` was carrying both common catalog fields and provider-specific metadata, causing churn and risk on each new source.
- Fix: decision recorded to move provider data into a dedicated per-item source metadata model (`provider`, `external_ref`, `external_url`, `metadata`) while keeping `item` as domain core.
- Prevention: for any new external source, add mappings in source-metadata layer first; avoid adding provider-specific columns to `item`.

## 2026-03-17 - A new sync source is not done until the orchestrator and docs know about it
- Symptom: a source-specific command can exist locally but stay half-integrated, which makes the raw-data pipeline harder to reason about and easier to forget.
- Root cause: adding a provider command before wiring `app:data:sync`, tests, and README leaves the feature in an in-between state.
- Fix: for `fallout-wiki`, the dedicated command was versioned, added to `app:data:sync`, covered with unit tests, and documented with its default `data/sources/<provider>/...` output path.
- Prevention: every new data source must ship as one slice: dedicated command + orchestrator integration + tests + runbook/docs.

## 2026-03-17 - External wiki sync payloads must be flattened before import
- Symptom: Fandom/Fallout Wiki sync files are JSON objects with `resources`, while the legacy import expected a flat list of rows and silently skipped object payloads.
- Root cause: the import reader was still tailored to older raw-array sources only.
- Fix: `FilesystemItemImportSourceReader` now ignores `index.json`, unwraps `resources[*]`, flattens `columns`/`availability`, derives a stable numeric `id` from `form_id`, and feeds normalized rows into the existing import pipeline.
- Prevention: every new raw source format must either match the flat-row contract or add a reader normalization test before import wiring.

## 2026-03-17 - Compare multi-source catalog data before inventing merge rules
- Symptom: deciding merge policy too early risks encoding fragile assumptions about which provider is "right" on `obtained`, booleans, values, or URLs.
- Root cause: once multiple providers feed the same item, source differences become a product decision, not just a parsing decision.
- Fix: added a read-only console report (`app:data:report:source-diff`) that lists per-item divergent fields between two providers.
- Prevention: before implementing consolidation rules or admin merge UI, inspect real diffs from the report and decide field priorities from observed data.

## 2026-03-17 - Cross-source catalog merge should be field-based, not provider-global
- Symptom: `fandom` and `fallout.wiki` disagree on different kinds of fields for the same item, so choosing one provider globally would either lose availability booleans or lose useful `unlocks/obtained` details.
- Root cause: the two wiki sources have complementary strengths rather than one uniformly better payload.
- Fix: introduced a read-only merge policy + report (`app:data:report:source-merge`) with explicit per-field priorities: `fandom` for availability-style booleans/weight/currency, `fallout.wiki` for `unlocks`/`obtained`/`type`, and names consolidated only when values are equivalent after loose normalization.
- Prevention: for future providers, add merge rules field by field and keep URLs/source-specific references out of the consolidated profile unless there is a clear single-source requirement.

## 2026-03-17 - Expose new cross-source consolidation additively before wiring UI behavior
- Symptom: the merge policy existed in console only, so future UI work would otherwise need to reimplement or rediscover the same consolidation rules.
- Root cause: no read-side payload exposed the retained fields/conflicts yet.
- Fix: added an additive `sourceMerge` block to the player item API payload, leaving existing fields unchanged while surfacing retained decisions and remaining conflicts.
- Prevention: when introducing a new read-side consolidation rule, expose it as additive metadata first so consumers can adopt it gradually without breaking current UX.

## 2026-03-17 - Merge policy needs both item-level and field-level reporting
- Symptom: item-by-item merge reports are useful for debugging one record, but they do not show whether a field rule is globally healthy across the catalog.
- Root cause: the first merge report only exposed per-item retained fields/conflicts.
- Fix: added `app:data:report:source-merge-summary`, which aggregates retained-provider counts and conflict counts by field across scanned items.
- Prevention: when a merge policy grows beyond a few fields, always keep both views: item-level for debugging and field-level for steering policy changes.

## 2026-03-17 - Some wiki name conflicts were source duplicates, not merge-policy mistakes
- Symptom: merge reports showed a few severe `name` conflicts (`Bladed Commie Whacker` vs `Garden Trowel Knife`, `Vault 63` vs `Vault 96`, etc.) on the same `form_id`.
- Root cause: `fallout.wiki` raw snapshots contained duplicated rows with the same `form_id` but different names/URLs; the import kept the last one and overwrote the correct first occurrence.
- Fix: import now ignores later duplicate rows for `fandom`/`fallout_wiki` when the same provider repeats the same `form_id`, keeping the first occurrence and emitting a warning. Name merge also prefers the more specific parenthetical variant when one source has the generic label and the other has the full regional/item variant.
- Prevention: for wiki-like sources keyed by `form_id`, treat intra-provider duplicate `form_id` rows as source-quality issues to ignore at import time instead of trying to solve them later in merge logic.

## 2026-03-17 - Treat live third-party API 500s as an external blocker, not a mapping bug
- Symptom: Nukacrypt GraphQL introspection and `nukeCodes` succeed, but item queries (`esmRecord`, `esmRecords`) return HTTP 500 even on simple `formId`/`searchTerm` probes.
- Root cause: the remote item endpoint is unstable or expects undocumented constraints; the failure is server-side, not caused by local parsing code.
- Fix: paused the read-only Nukacrypt item sync and continued with read-only collision reporting on local multi-source data instead.
- Prevention: when a third-party API returns reproducible 5xx on minimal valid probes, record it as an upstream blocker and avoid shipping speculative client logic on top of it.

## 2026-03-17 - Nukacrypt public GraphQL is usable for targeted search, not reliable direct formId lookup
- Symptom: a direct `esmRecord(formId)` lookup from the app kept returning HTTP 500 / empty body, while the user could still browse records on the Nukacrypt site.
- Root cause: the public GraphQL contract is inconsistent: `games` and `nukeCodes` are stable, `esmRecord(formId)` is not reliable from the app context, but `esmRecords(searchTerm + signatures)` does answer for exact item-name probes.
- Fix: replaced the attempted direct `formId` lookup slice with a targeted console probe built on `esmRecords(searchTerm, signatures)`, which is enough for semi-automatic conflict arbitration by candidate names.
- Prevention: when integrating third-party GraphQL, validate the exact query shape live before designing abstractions; if only search works reliably, model the client as a search helper instead of a direct-ID repository.

## 2026-03-17 - Nukacrypt conflict arbitration should search by candidate name or editorId, then validate by formId
- Symptom: the user confirmed the public Nukacrypt search works with `name` or `editorId`, but not with `formId`, which matches our live API probes.
- Root cause: on the public side, `formId` is a good validation field but not a dependable lookup input; treating it as a search key sends us back to the flaky GraphQL path.
- Fix: added a dedicated conflict-probe command that searches one or more candidate names and an optional `editorId`, then reports which results match the expected `formId`.
- Prevention: for external arbitration workflows, separate lookup keys (name/editorId) from validation keys (`formId`) instead of assuming one field can do both jobs.

## 2026-03-17 - Nukacrypt shell cURL can work in app container while PHP runtime still fails
- Symptom: the user reproduced a working GraphQL search by pasting the browser `curl` directly in the `app` container shell, while the same search still failed from the Symfony app with `Response body is empty`.
- Root cause: the remaining instability is narrower than network/container access; the failure sits in the PHP runtime path (`HttpClient` and our attempted `Process` fallback), not in basic reachability from the container.
- Fix: kept the stable read-only probes in place and avoided shipping speculative runtime fallback logic after the PHP-side experiments still failed to reproduce the successful shell request.
- Prevention: when a third-party API works in the container shell but not through the app, isolate the problem as a runtime/client parity issue rather than a general container/network blocker.

## 2026-03-13 - Async messenger transport requires Doctrine table migration in prod DB
- Symptom: roadmap OCR upload failed with `relation "messenger_messages" does not exist`.
- Root cause: `MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0` disables runtime table auto-creation, and async feature shipped before creating `messenger_messages`.
- Fix: added migration `Version20260313193000` creating `messenger_messages` + compound queue index.
- Prevention: every new Doctrine Messenger async route must ship with transport table migration (or explicit setup command in deploy runbook).

## 2026-03-13 - Roadmap OCR layout preprocessing works better when stacking monthly right-pane bands
- Symptom: OCR on full roadmap image remained noisy on older seasons despite grayscale/bw filters.
- Root cause: decorative left pane and mixed layout density polluted recognition; one-pass preprocessing preserved too much irrelevant content.
- Fix: added `layout-bw` preprocessing mode that crops the right timeline pane, splits it into 4 monthly bands, stacks them, then applies strong grayscale/contrast/bw preprocessing.
- Prevention: keep OCR preprocessing modes explicit and benchmark with the same sample image set before changing parser heuristics.

## 2026-03-11 - OCR fallback must keep best confidence, not last provider
- Symptom: when all OCR providers were below acceptance threshold, snapshot kept a lower-quality result (e.g. tesseract 0.83 over ocr.space 0.89).
- Root cause: `OcrProviderChain` returned the last successful provider instead of the best-confidence successful provider.
- Fix: chain now tracks highest confidence among successful attempts and returns that result when no provider is acceptable.
- Prevention: keep a unit test for "all providers rejected by threshold" asserting best-confidence fallback selection.

## 2026-03-11 - Optional OCR providers should not pollute attempt diagnostics
- Symptom: admin OCR details repeatedly showed `paddle ... not installed/configured`, creating noise and confusion.
- Root cause: unavailable optional provider was treated as a regular failed attempt.
- Fix: introduced `OcrProviderUnavailableException`; chain skips unavailable providers in attempt reporting while preserving real failures.
- Prevention: optional/inactive providers should raise dedicated unavailable exceptions and be excluded from operator-facing failure summaries.

## 2026-03-11 - Roadmap merge quality should rely on reviewed snapshot events when present
- Symptom: merge could reparse raw OCR text and ignore manually corrected `roadmap_event` rows from admin, reducing reliability on older seasons.
- Root cause: merge pipeline always parsed `raw_text` instead of preferring persisted reviewed events.
- Fix: merge now uses snapshot persisted events first (sorted), falls back to parser only when events are absent, and runs quality validation before merge.
- Prevention: for review-driven workflows, merge/publish should consume edited persisted artifacts before any regeneration fallback.

## 2026-03-06 - Admin roadmap functional tests broke on locale query in form action
- Symptom: `RoadmapSnapshotControllerTest` could not find CSRF tokens in parse/approve/save forms (`assertCount(1)` got `0`).
- Root cause: test selectors used `form[action$=\"...\"]` while rendered actions now append `?locale=...`.
- Fix: switched selectors to `form[action*=\"...\"]` and aligned table count expectation to current page layout (snapshots table + events table when a snapshot is selected).
- Prevention: for functional selectors on generated routes, prefer `action*=` (or stable form ids) over strict `action$=` when locale/query params may be appended.

## 2026-03-06 - Functional test stale Doctrine entity after multiple HTTP requests
- Symptom: roadmap snapshot approval test still read `DRAFT` status after successful approve POST.
- Root cause: test reused the same `EntityManager` across requests and could keep a managed stale entity instance.
- Fix: call `$entityManager->clear()` before reloading entities after state-changing HTTP requests.
- Prevention: in functional tests doing write then read across separate requests, clear Doctrine identity map before assertions on persisted state.

## 2026-03-06 - Roadmap month blocks can include non-ranged lines
- Symptom: expected OCR roadmap count was off by one (assumed 20 events for 4 months x 5 lines).
- Root cause: some month blocks include one editorial/update line without a date range (`MISE A JOUR ...`) that is not an event window.
- Fix: parser remains date-range driven; multiline title extraction was improved, but only ranged entries are persisted as events.
- Prevention: validate roadmap totals by counting date ranges, not visual bullet lines.

## 2026-03-07 - OCR roadmap titles can include footer/banner garbage lines
- Symptom: parsed event titles sometimes included unrelated OCR text (`Falleut 76`, `COMMUNITY CALENDAR`, `Bethesda`, `TM` artifacts).
- Root cause: multiline title reconstruction accepted nearby non-event banner/footer lines.
- Fix: strengthened `isIgnoredTitleLine()` noise filtering (calendar/footer/brand/TM patterns + symbol-only lines).
- Prevention: keep a dedicated unit test with known OCR garbage snippets to prevent regressions.

## 2026-03-07 - Multi-locale roadmap parsing should use locale profiles, not scattered locale conditionals
- Symptom: English/German OCR payloads produced 0 events in some real-world date formats.
- Root cause: locale-specific month aliases/ordinals/date notation were hardcoded in a single parser with limited FR-centric assumptions.
- Fix: introduced `RoadmapLocaleProfile` + registry (`fr/en/de`) and moved month aliases/title normalization per locale into dedicated profiles; parser now uses one common pipeline with locale profile lookup.
- Prevention: when adding/changing locale behavior, update only the matching profile + add targeted unit test for that locale format (ordinals, dots, aliases, OCR typos).

## 2026-02-28 - Flatpickr theme override requires CSS import order
- Symptom: datepicker popup still showed default blue/black selection despite custom styles.
- Root cause: `flatpickr.min.css` loaded after app styles, overriding custom theme rules.
- Fix: import `flatpickr` CSS before `assets/styles/app.css` and keep stronger range selectors in app CSS.
- Prevention: when theming third-party widgets, ensure vendor CSS is imported first and validate final cascade in built output.

## 2026-02-28 - Admin Minerva column widths were applied to the wrong table
- Symptom: requested width change (`Liste` compact, `Localisation` dominant) was not visible.
- Root cause: timeline width class was first attached to the manual-overrides table, then widths stayed weak without fixed table layout.
- Fix: attach class to timeline table, add explicit `colgroup` and `table-layout: fixed` with dedicated column widths.
- Prevention: for admin pages with multiple tables, always verify class target in template and prefer `colgroup` + fixed layout for deterministic widths.

## 2026-02-28 - Minerva player switch did not refresh timeline progression
- Symptom: changing player in Minerva filters updated item cards but timeline progress column stayed stale until reload.
- Root cause: `minerva_knowledge` and `minerva_progression` Stimulus controllers were independent with no shared event.
- Fix: emit `f76:minerva-player-changed` from knowledge controller and reload stats in progression controller on event.
- Prevention: when two controllers share a critical state (active player), define an explicit cross-controller event contract.

## 2026-02-28 - Minerva progression still used legacy modulo-4 fallback
- Symptom: Minerva timeline progress could show values from lists 1..4 for rows >4.
- Root cause: front controller `minerva_progression_controller.js` still mapped list numbers with a legacy modulo-4 fallback.
- Fix: removed fallback mapping and kept strict progress lookup by absolute list number (1..24).
- Prevention: when domain numbering changes (e.g. list model), remove temporary compatibility fallbacks once migration is completed.

## 2026-02-28 - Minerva special list files can contain duplicate item IDs
- Symptom: list 24 showed 39 unique items while raw counts of lists 21/22/23 summed to 40.
- Root cause: source JSON contained duplicated `id` (`931`) within the same special-list payload.
- Fix: importer emits a warning on duplicate source rows in the same file (`Doublon detecte ... (conserve)`) but does not skip/filter rows.
- Prevention: keep importer warnings visible, and treat source row count and unique-item count as distinct metrics by design.

## 2026-02-27 - public/assets may be ignored for new icon files
- Symptom: `git add` failed with `The following paths are ignored ... public/assets`.
- Root cause: `.gitignore` rules ignore parts of `public/assets`, while existing files can still be tracked.
- Fix: add new icon files explicitly with force (`git add -f public/assets/icons/<file>`).
- Prevention: when creating new assets under ignored paths, check tracking with `git ls-files` and use forced add only for intended files.

## 2026-02-27 - Minerva BOOK list model 1..4 caused semantic drift
- Symptom: UI/stats showed progression only for 4 "listes" while Minerva rotation uses 24 real lists.
- Root cause: import collapsed `minerva_61..84` into modulo-4 list numbers (`1..4`) instead of absolute list numbers (`1..24`).
- Fix: switched import mapping to absolute lists (`list_number = minervaNumber - 60`), expanded DB constraint to `1..24`, and reset/rebuilt BOOK list relations on import.
- Prevention: when filenames encode absolute business identifiers, store them as-is in persistence; avoid modulo compression unless explicitly required by product semantics.

## 2026-02-27 - Minerva countdown offset by 5h due to wall-time timezone handling
- Symptom: front countdown showed ~5h less than external Minerva trackers for the same next window.
- Root cause: `minerva_rotation` uses `timestamp without time zone` wall times, but timeline service converted DateTime timezone directly (`setTimezone`) instead of reinterpreting stored wall time as `America/New_York`.
- Fix: normalize stored wall times by reparsing them in `America/New_York` (`normalizeStoredWallTime`) and keep timeline timezone explicit to `America/New_York`.
- Prevention: for DB columns storing wall-time schedules (`timestamp without time zone`), never apply direct timezone conversion as if absolute instant; first reinterpret in business timezone.

## 2026-02-27 - PHPUnit mock typing on final class can break phpstan
- Symptom: `phpstan` reported `createMock()` as unresolvable/mixed in Minerva unit tests.
- Root cause: unit test mocked a concrete final service (`MinervaRotationGenerationApplicationService`) and relied on mock-specific typing.
- Fix: switched test to real deterministic generation service + mocked repository/entity manager only.
- Prevention: prefer mocking ports/interfaces; when a deterministic final service has no side effects, instantiate it directly in tests.

## 2026-02-27 - Catalog import stability improves with explicit value objects
- Symptom: import flow used multiple implicit array-shape contracts (`context`, `contextResult`, `translationData`) that were easy to misuse during refactors.
- Root cause: permissive array plumbing across `ItemImport*` services without explicit typed contracts.
- Fix: introduced dedicated value objects (`ItemImportFileContext`, `ItemImportContextApplyResult`, `ItemImportTranslationCatalog`) and aligned service/tests.
- Prevention: in import pipelines, prefer small readonly value objects over associative arrays for cross-class contracts.

## 2026-02-27 - Lock architecture against legacy root namespace regressions
- Symptom: after DDD migration, accidental reintroduction of `App\\Controller`/`App\\Entity` style dependencies was still possible.
- Root cause: architecture tests did not explicitly forbid dependencies toward legacy root namespaces.
- Fix: added PHPat rule `testAppDoesNotDependOnLegacyRootNamespaces` in `tests/Architecture/ArchitectureTest.php`.
- Prevention: when removing legacy roots, add an explicit architecture guard so future refactors cannot silently depend on them again.

## 2026-02-27 - Query object signature hardening requires aligned unit fixtures
- Symptom: unit/phpstan failures after changing admin query `fromRaw(...)` signatures from `int|string|null` to `?int`.
- Root cause: unit tests still passed string pagination values to application query objects.
- Fix: keep string-to-int parsing in UI sanitizers and update query-object unit fixtures to pass already-sanitized ints/null.
- Prevention: when moving sanitization upstream to UI, update application-level tests to reflect typed contracts.

## 2026-02-27 - Route import path must follow DDD controller moves
- Symptom: full functional suite returned HTTP 500 on nearly all pages/endpoints after controller namespace migration.
- Root cause: `config/routes.yaml` still imported `../src/Controller/` while that directory had been removed.
- Fix: switched route attribute import to `path: ../src/` and `namespace: App`.
- Prevention: after any controller relocation/removal, verify routing imports in `config/routes.yaml` before running functional tests.

## 2026-02-27 - Doctrine entities in Infrastructure namespace break PHPat boundaries
- Symptom: after moving entities out of `src/Entity`, PHPat raised many `Application/UI -> Infrastructure` violations and doctrine test bootstrap failed on missing `src/Entity` mapping path.
- Root cause: entities were first moved under `*/Infrastructure/...`, which made every entity type-hint count as an infra dependency; Doctrine config still pointed to `src/Entity`.
- Fix: moved entities to `*/Domain/Entity`, removed `repositoryClass` attributes from domain entities to avoid `Domain -> Infrastructure`, and switched Doctrine mapping to `dir: src` + `prefix: App`.
- Prevention: when removing `src/Entity`, place entities in domain context namespaces and update Doctrine mapping in the same slice.

## 2026-02-27 - UI namespace migration exposes hidden infrastructure coupling
- Symptom: after moving web/security controllers into `.../UI/...`, PHPat started failing on direct infra injections that previously went unnoticed.
- Root cause: legacy `src/Controller/*` classes were outside strict layer checks; once moved under UI namespaces, architectural rules applied.
- Fix: replaced direct infra dependencies with application-level abstractions (`IdentityCaptchaSiteKeyProvider`, `PlayerReadApplicationService`).
- Prevention: when migrating legacy paths into DDD layers, expect PHPat to surface hidden couplings and budget a follow-up decoupling pass in the same slice.

## 2026-02-27 - Moving controllers/repositories can trigger PHPat layer violations
- Symptom: after namespace moves, PHPat reported `Application -> Infrastructure` and `UI -> Infrastructure` violations.
- Root cause: some classes injected concrete repositories (`MinervaRotationEntityRepository`, `UserEntityRepository`) instead of application ports.
- Fix: introduced ports (`MinervaRotationRegenerationRepository`, `AdminUserManagementReadRepository`) and rewired dependencies to interfaces.
- Prevention: after structural moves, run phpstan/PHPat early and immediately replace concrete infra dependencies with application-level contracts.

## 2026-03-01 - Port/class same-name collision after Interface suffix removal
- Symptom: `phpstan`/PHPUnit fatal error `Cannot redeclare class ...ContactMessageEmailSender`.
- Root cause: after renaming the port to `ContactMessageEmailSender`, the infrastructure class kept `use App\...\ContactMessageEmailSender` and had the same class basename in the same file.
- Fix: removed ambiguous import and implemented the port with fully-qualified name.
- Prevention: when removing `Interface` suffixes, check files where implementation class basename equals the new port basename and avoid conflicting `use` imports.

## 2026-03-01 - Same-namespace port naming collision in UI layer
- Symptom: renaming `ProgressionOwnedPlayerReadResolverInterface` directly caused a filename/class collision with existing `ProgressionOwnedPlayerReadResolver`.
- Root cause: port and implementation lived in the same `App\Progression\UI\Api` namespace with identical basename after suffix removal.
- Fix: renamed the port to `ProgressionOwnedPlayerReadPort` and kept the implementation class name unchanged.
- Prevention: when removing `Interface` suffixes, if port and implementation share namespace, pick a distinct port suffix (`Port`/`Contract`) before applying global replacements.

## 2026-02-27 - Repository namespace moves require entity metadata updates
- Symptom: moving Doctrine repositories between namespaces can silently break runtime if entity `repositoryClass` attributes still point to old FQCNs.
- Root cause: repository migration impacts both service wiring and ORM metadata references.
- Fix: updated repository imports in all entities (`repositoryClass`) and adjusted DI aliases/config usages in the same slice.
- Prevention: after each repository move, run a grep on `repositoryClass:` and `App\\Repository\\` to ensure zero stale references.

## 2026-02-27 - CSRF field name consistency across admin forms
- Symptom: admin translation functional test passed token but save endpoint behaved like invalid CSRF.
- Root cause: template/test used `_token` while shared admin validator reads `_csrf_token`.
- Fix: aligned translation form and functional test to `_csrf_token`.
- Prevention: for admin POST actions using `AdminCsrfTokenValidatorTrait`, always use `_csrf_token` in forms/tests.

## 2026-02-27 - PHPat boundary guard when relocating shared services
- Symptom: after moving security helpers into `Identity/Infrastructure`, `phpstan` failed on PHPat rule (`UI` layer depending on `Infrastructure`).
- Root cause: `Identity/UI` and `Support/UI` classes injected concrete services (`AuthEventLogger`, `SignedUrlGenerator`) relocated too low in architecture.
- Fix: moved these shared services to `Identity/Application/Security` and kept infra classes depending on application layer.
- Prevention: when reorganizing namespaces for DDD, check PHPat boundaries first; `UI -> Application` is acceptable, `UI -> Infrastructure` is not.

## 2026-02-27 - Backoffice translations POST missed CSRF protection
- Symptom: admin translation save endpoint accepted POST without CSRF token.
- Root cause: controller was protected by role guard but lacked `AdminCsrfTokenValidatorTrait` wiring.
- Fix: added CSRF validation in `ItemTranslationController`, `_token` in Twig form, and functional coverage for invalid token rejection.
- Prevention: every admin POST/PUT/DELETE endpoint must include shared CSRF trait + template token + at least one functional assertion.

## 2026-02-27 - Admin input sanitization drift across controllers
- Symptom: duplicated `optionalString` / `optionalIntOrString` / pagination sanitation logic in multiple admin controllers.
- Root cause: sanitation helpers were added incrementally per controller instead of a shared component.
- Fix: extracted `AdminInputSanitizerTrait` and reused it in `AuditLogController`, `ContactMessageController`, `ItemTranslationController`.
- Prevention: for new admin endpoints, reuse the shared sanitizer trait and avoid local helper duplication.

## 2026-02-27 - Port and adapter with same short class name need alias in tests/adapters
- Symptom: `phpstan` failed with `Cannot use ... as ... because the name is already in use`.
- Root cause: after removing `Interface` suffix, a file imported both the port and adapter class sharing the same short name (`TranslationCatalogWriter`).
- Fix: keep same naming convention but alias one import (`... as YamlTranslationCatalogWriter` / `... as ...Port`) where both are needed.
- Prevention: when suffix-free ports mirror adapter names, check each file for short-name collisions after refactors.

## 2026-02-27 - DDD inventory: legacy root folders explicitly tracked
- Symptom: several legacy root folders remained in `src` during the DDD migration (`Controller`, `Domain`, `Entity`, `EventSubscriber`, `Repository`, `Security`, `Service`) plus empty `src/Translation`.
- Root cause: migration slices prioritized behavior and safety before final namespace placement cleanup.
- Fix: explicit inventory item tracked in backlog/current focus to migrate remaining files into context namespaces and remove empty `src/Translation`.
- Prevention: after each DDD slice batch, run a structural inventory pass on `src` and track leftovers immediately.

## 2026-02-27 - Interface naming and location convention aligned with DDD target
- Symptom: interfaces remained split between `src/Contract` and context namespaces, with inconsistent naming (`*Interface` suffix).
- Root cause: transition refactors prioritized behavior safety over final architectural placement/convention.
- Fix: decision recorded: move ports into context namespaces and use interface names without `Interface` suffix.
- Prevention: new ports should be created directly in context namespaces and follow suffix-free naming.

## 2026-02-27 - Constructor signature changes require synchronized test fixture updates
- Symptom: `phpstan` and unit tests failed after resolver constructor/signature refactor.
- Root cause: test fixtures still instantiated old constructor argument order/count and outdated null-user case.
- Fix: updated all impacted unit tests to new constructor contracts and removed obsolete unauthenticated-path test where typing made it impossible.
- Prevention: after any constructor/signature change, run a targeted search on `new <ClassName>(` and update every fixture before running full quality gates.

## 2026-02-27 - Final classes block PHPUnit doubles in unit tests
- Symptom: unit tests failed with `ClassIsFinalException` while trying to mock a `final` resolver.
- Root cause: test targeted a concrete `final` class instead of a contract.
- Fix: introduced an interface port and wired consumers to it (`ProgressionOwnedPlayerReadPort`).
- Prevention: when a service is expected to be doubled in unit tests, depend on an interface in collaborators.

## 2026-03-08 - Never run functional suite in parallel against shared test DB
- Symptom: massive false negatives in functional tests (deadlocks, missing tables, random 302/404), while code changes were unrelated.
- Root cause: two `make phpunit-functional` runs executed concurrently and both mutated the same `app_test` database lifecycle (drop/create/migrate) at the same time.
- Fix: rerun functional tests in a single serial execution only; ignore parallel run output as invalid.
- Prevention: never launch multiple functional-suite commands concurrently in this project unless isolated test databases are configured per process.

## 2026-02-27 - Unit test on 204 JsonResponse content expected empty string
- Symptom: unit test failed asserting `''` for a `JsonResponse` with HTTP 204.
- Root cause: Symfony `JsonResponse(null, 204)` serializes as `{}` content, not empty string.
- Fix: adjusted assertion to expect `'{}'`.
- Prevention: when asserting raw response body, verify Symfony serializer behavior for each response class/status.

## 2026-02-27 - Mock configured on wrong repository method name
- Symptom: PHPUnit error `MethodCannotBeConfiguredException` while testing owned player read resolver.
- Root cause: test mocked `findOwnedByPublicId` but repository contract method is `findOneByPublicIdAndUser`.
- Fix: updated mock to correct method name and argument order.
- Prevention: always align mocks with interface signatures (not service convenience names).

## 2026-02-27 - Reflection setAccessible deprecated in PHP 8.5 tests
- Symptom: unit suite failed with deprecations treated as errors on `ReflectionProperty::setAccessible()`.
- Root cause: PHP 8.5 deprecates `setAccessible()` (no-op since PHP 8.1).
- Fix: removed `setAccessible()` calls from tests and kept direct `setValue()` usage.
- Prevention: when setting private IDs in tests, use `ReflectionProperty::setValue()` directly.

## 2026-02-26 - Final repositories blocked unit tests in application services
- Symptom: unit tests for new application service failed (`Class ...Repository is declared final and cannot be doubled`), plus weak type guarantees in service result arrays.
- Root cause: application service depended directly on Doctrine repositories (`final`) instead of contracts; result array shapes were too loose for static analysis.
- Fix: introduced dedicated transfer contracts in `src/Contract`, made repositories implement them, and tightened service return union shapes.
- Prevention: new application services should depend on interfaces (ports), not concrete repositories, and use explicit success/error union return shapes.

## 2026-02-26 - Dependency approval must be explicit
- Symptom: dependency discussions happened too late during implementation.
- Root cause: approval/justification flow was not enforced early enough.
- Fix: mandatory policy in `AGENTS.md` and checklist: ask first, explain package, rationale, and fallback cost.
- Prevention: no `composer require`/new tool before explicit user confirmation.

## 2026-02-26 - Public IDs leaked via numeric route params
- Symptom: API routes exposed predictable incremental IDs.
- Root cause: resources used internal DB IDs in public URLs/payloads.
- Fix: added `public_id` for `player` and `item`, migrated routes/lookups to opaque IDs.
- Prevention: all new externally exposed resources must use opaque public IDs.
- Links: `migrations/Version20260226200000.php`

## 2026-02-26 - Temporary link rules duplicated across controllers
- Symptom: TTL/cooldown values were repeated in multiple auth/admin controllers.
- Root cause: no shared policy service for temporary links.
- Fix: introduced `TemporaryLinkPolicy` and centralized durations/cooldowns.
- Prevention: auth link timing rules must live in one service.
- Links: `src/Security/TemporaryLinkPolicy.php`

## 2026-02-26 - Sensitive links lacked URL signature
- Symptom: verify/reset URLs accepted token-only links.
- Root cause: no URL integrity check.
- Fix: introduced `SignedUrlGenerator` (`UriSigner`) and enforced signature check on verify/reset endpoints.
- Prevention: all temporary action links must be signed and validated server-side.

## 2026-02-26 - Dashboard stored selected player in query param
- Symptom: selected player leaked in URL (`?player=...`) and caused noisy links.
- Root cause: front state sync used query params by default.
- Fix: switched selected player persistence to localStorage metadata.
- Prevention: avoid query params for state that is not shareable/business-critical.

## 2026-02-26 - Root memory diverged from project docs
- Symptom: memory content existed in root and docs with risk of drift.
- Root cause: no single source of truth for AI memory.
- Fix: `memory.md` at root now points to `docs/ai/memory.md`.
- Prevention: update only `docs/ai/memory.md`.

## 2026-02-26 - Python command unavailable in shell scripts
- Symptom: ad-hoc script failed with `python: command not found`.
- Root cause: environment did not guarantee `python` binary.
- Fix: prefer shell-native tools (`sed`, `rg`, `apply_patch`) for repo updates.
- Prevention: do not assume Python availability for simple text edits.

## 2026-02-26 - DDD extraction broke controller dependency wiring
- Symptom: `phpstan` reported undefined property in API controller after refactor.
- Root cause: moved item lookup to app service but forgot controller still needed repository for list query.
- Fix: restored explicit `ItemEntityRepository` injection for list endpoint while keeping app service for use-cases.
- Prevention: during incremental extraction, re-check constructor dependencies per endpoint path.

## 2026-02-25 - Functional tests brittle on localized strings
- Symptom: functional tests failed when locale/text changed (e.g. expecting `Bonjour`).
- Root cause: assertions coupled to translated UI labels.
- Fix: switched assertions to stable signals (status code, route, form selector, known data).
- Prevention: never assert on fragile localized wording in functional tests.

## 2026-02-25 - WebTestCase helper naming collision
- Symptom: unexpected behavior around browser/client helpers in tests.
- Root cause: local helper name conflicted with `WebTestCase` static methods.
- Fix: standardized helper name to `browser()` in tests.
- Prevention: avoid helper names that shadow framework test APIs.

## 2026-02-25 - Test DB setup duplicated schema work
- Symptom: redundant schema operations and unstable test setup.
- Root cause: schema recreation in tests while `db-test-init` already handled migrations.
- Fix: rely on migrated schema and isolate tests with `TRUNCATE ... RESTART IDENTITY CASCADE`.
- Prevention: keep one DB bootstrap path for test suites.

## 2026-02-25 - YAML comments lost on translation write
- Symptom: translation files lost section comments after save.
- Root cause: default YAML dumper does not preserve comments.
- Fix: custom writer/renderer for controlled output sections.
- Prevention: use dedicated writer for human-maintained translation files.

## 2026-02-24 - PostgreSQL platform API mismatch (DBAL 4)
- Symptom: migration/runtime error calling undefined platform method (`getName`).
- Root cause: DBAL 4 API differences.
- Fix: platform checks now use `instanceof PostgreSQLPlatform`.
- Prevention: avoid legacy DBAL platform name APIs.

## 2026-02-24 - Duplicate item import on `(type, source_id)`
- Symptom: import failed with unique constraint violation.
- Root cause: same source item encountered multiple times in one import pass.
- Fix: dedup cache during import write mode.
- Prevention: importer must deduplicate by business key before persist.

## 2026-02-24 - DB host `database` unresolved outside Docker
- Symptom: `could not translate host name "database"` on local CLI.
- Root cause: app expected Docker network DNS.
- Fix: run Symfony commands inside app container.
- Prevention: use `make`/docker exec commands as default.

## 2026-02-24 - Build failure around opcache install
- Symptom: Docker build failed while installing `opcache`.
- Root cause: extension already present in base image.
- Fix: removed redundant `docker-php-ext-install opcache`.
- Prevention: verify base image capabilities before adding extension install steps.

## 2026-03-01 - Functional test brittle on fixed createdAt window
- Symptom: admin users functional test could not find row/token with `createdFrom/createdTo` filters.
- Root cause: test used a fixed February 2026 date range while test users were created at current date.
- Fix: explicitly set `createdAt` for involved users inside the asserted range before requesting the page.
- Prevention: when asserting date-range filters, always control timestamps in fixtures instead of relying on `now`.

## 2026-03-01 - Final services reduce testability without ports
- Symptom: unit tests failed while mocking `final` application services (PHPUnit cannot double final classes).
- Root cause: command/service constructors depended on concrete `final` classes instead of application ports.
- Fix: introduced explicit Minerva ports (`Generator`, `Regenerator`, `Refresher`) and injected interfaces.
- Prevention: expose application behavior through interfaces (ports) when class will be consumed/mocked in tests.

## 2026-03-01 - Keep audit read queries out UI controllers
- Symptom: admin Minerva controller contained inline query-builder logic to resolve latest refresh audit summary.
- Root cause: missing application-level read use-case over existing audit read repository.
- Fix: added `LatestMinervaRefreshSummaryApplicationService` + `AuditLogReadRepository::findLatestByActions()` and moved query to infrastructure repository.
- Prevention: when a controller needs persisted read-model data, add/extend an application service/port instead of embedding persistence queries in UI layer.

## 2026-03-01 - Minerva governance docs must match runtime capabilities
- Symptom: governance doc still stated "override not active" while admin UI supported manual overrides.
- Root cause: documentation drift after enabling backoffice override flow.
- Fix: updated governance/runbook to "generated by default, manual override only for incidents, then return to generated" and surfaced this rule in admin UI.
- Prevention: whenever enabling/disabling an operational override mechanism, update both ops docs and the related admin page wording in the same slice.

## 2026-03-01 - Reliability checks need machine-readable output for cron monitoring
- Symptom: Minerva coverage checks were human-readable only, making alerting/parsing harder.
- Root cause: command output format was text-only despite non-zero exit support.
- Fix: added `--format=json` to `app:minerva:refresh-rotation` and `make minerva-refresh-check-json`.
- Prevention: for recurring ops checks, provide both stable exit codes and structured output (JSON) in the same command.

## 2026-03-03 - Keep a fast smoke gate separate from full functional suite
- Symptom: regressions on critical routes could be detected late when only full functional runs were used.
- Root cause: no dedicated quick gate mixing ops drift signal and key functional flows.
- Fix: added `make smoke` (`smoke-ops` + `smoke-app`) and documented thresholds/triage in ops runbook.
- Prevention: run smoke first for rapid signal, then run full `make phpunit-functional` when smoke is green.

## 2026-03-06 - EasyOCR sidecar unstable in local Docker for roadmap scans
- Symptom: OCR chain kept falling back to Tesseract with `EasyOCR HTTP request failed` and `Empty reply from server`.
- Root cause: EasyOCR sidecar process was unstable during heavy inference on this setup (connection dropped / service stopped).
- Fix: removed EasyOCR sidecar path and switched to `ocr.space` HTTP provider as OCR fallback.
- Prevention: for large roadmap-image OCR on constrained local CPU memory, prefer external OCR provider fallback instead of heavy local Python inference service.

## 2026-03-07 - Roadmap cross-locale date drift from OCR day truncation
- Symptom: FR snapshot produced `2026-05-03 -> 2026-06-01` while EN/DE produced `2026-05-28 -> 2026-06-01`, creating low-confidence merge warnings.
- Root cause: OCR truncated a cross-month start day near month-end (`28` read as `3`) on FR raw text.
- Fix: parser now corrects suspicious cross-month ranges when continuity indicates a late-month start; merge also emits explicit `Potential OCR day mismatch` warning when locale buckets split on same end date with large start-day gap.
- Prevention: keep locale-merge warnings enabled and manually review any mismatch warning before approving canonical roadmap.

## 2026-03-10 - Functional test service override lost between requests
- Symptom: roadmap upload functional test created zero snapshots even with an OCR stub provider configured.
- Root cause: `KernelBrowser` rebooted the kernel between GET and POST, dropping the overridden `OcrProviderChain` service from the test container.
- Fix: disabled reboot in that test before overriding the service (`$client->disableReboot()`), so the same container is reused for upload POST.
- Prevention: when a functional test overrides container services and performs multiple requests, disable reboot (or re-inject before each request).

## 2026-03-11 - OCR date parsing regressed on split `BIS` lines and mixed locale snapshots
- Symptom: some roadmap snapshots dropped events or produced broken windows (`end < start`) when OCR split date lines (`... BIS` on one line, continuation later) and when snapshot locale did not match text language.
- Root cause: normalization merged `... BIS` with any next line (including titles), and month resolution was strict to selected locale for month-first patterns.
- Fix: restrict `... BIS` merge to date-like continuations, support deferred continuation lines, and add cross-locale month token fallback (FR/EN/DE maps) for month-first parsing while keeping strict token checks to avoid false positives.
- Prevention: validate parser changes against mixed-locale real snapshots (including legacy uploads) and keep dedicated unit tests for split-date continuations.

## 2026-03-11 - Duplicate range warning should not flag valid overlapping events
- Symptom: roadmap quality flagged `Duplicate range detected` for snapshots where two distinct events intentionally shared the same date window.
- Root cause: validator warning logic keyed only on date range and ignored title difference.
- Fix: duplicate warning now triggers only when both range and normalized title match (true duplicate), not when different events share the same dates.
- Prevention: keep validator tests for both cases (same range + different titles => no warning, same range + same title => warning).

## 2026-03-11 - OCR snapshots can encode two events on one line or two date lines before titles
- Symptom: roadmap parser missed one event and mis-assigned titles on FR snapshot blocks (e.g. `12-16` + `19-23` on one line; `8-12` + `13-27` with alternating title lines).
- Root cause: parser assumed one date range per line and simple forward/backward title lookup, which breaks on OCR column interleaving.
- Fix: added paired-consecutive-date handling and multi-range line splitting with title pairing heuristics (`left,right,left` and `left,right,left,right` patterns).
- Prevention: keep unit tests for multi-range line splits and alternating title-line assignment.

## 2026-03-11 - Inverted roadmap ranges from OCR-truncated end days
- Symptom: some parsed events had `end < start` on noisy FR snapshots (e.g. `5 SEPT - 1` instead of `5 SEPT - 11/12`, or month rollover truncated).
- Root cause: OCR often dropped tens on end day or omitted rollover context near month end.
- Fix: added inverted-range recovery in parser (`+10/+20/+30` day reconstruction for short windows, and month rollover fallback for end-of-month starts).
- Prevention: for OCR-heavy snapshots, keep parser recovery for `end < start` before validation; validator should only flag residual impossible windows.

## 2026-03-13 - Snapshot deletion must only unlink files from roadmap uploads directory
- Symptom: deleting a snapshot could remove a versioned example image when `source_image_path` pointed outside uploads (e.g. `data/roadmap_calendar_examples/...`).
- Root cause: `deleteSnapshot()` unlinked any resolved in-project path, without restricting deletion scope to managed uploads.
- Fix: added `resolveSnapshotDeletableImagePath()` and only allow unlink under `var/data/roadmap_uploads/`; source image preview still works from other locations.
- Prevention: never `unlink` user/content paths unless explicitly scoped to a dedicated managed storage directory.

## 2026-03-17 - Docker app false unhealthy due to strict php-fpm process name match
- Symptom: `f76-app-1` showed `unhealthy` even though the app answered requests and `php-fpm` was running.
- Root cause: Docker `HEALTHCHECK` used `pgrep -x php-fpm`, but Alpine/PHP-FPM exposes the master process as `php-fpm: master process (...)`, so the exact-name match always failed.
- Fix: changed the healthcheck to `pgrep -f "php-fpm: master process"`.
- Prevention: when healthchecking `php-fpm`, match the actual process command line observed inside the container instead of assuming the bare binary name.

## 2026-03-17 - Alpine package pin drift can break Docker rebuilds
- Symptom: rebuilding `f76-app` failed with `libpq-18.3-r0 breaks: world[libpq=18.2-r0]`.
- Root cause: the Dockerfile pins Alpine package revisions exactly, and upstream repositories had already moved PostgreSQL packages from `18.2-r0` to `18.3-r0`.
- Fix: aligned `libpq` and `postgresql18-dev` pins to `18.3-r0`.
- Prevention: when a rebuild suddenly fails on `apk` with `breaks: world[...]`, first check whether an exact package revision pin has drifted in Alpine repos before changing the wider build.

## 2026-03-17 - Nukacrypt GraphQL search fails when unused variables are sent as null/empty
- Symptom: the app-side Nukacrypt probe returned `HTTP 500` with an empty body for valid searches like `Plan: Bladed Commie Whacker`, while equivalent browser/curl lookups succeeded.
- Root cause: the lookup repository sent a broader GraphQL variables payload than the browser request, including unused keys such as `editorId: null` and sometimes empty `signatures`; Nukacrypt's upstream search endpoint was sensitive to those extra null/empty variables.
- Fix: switched the lookup to a dedicated `ext-curl` request path matching the successful browser shape more closely, with a static FO76 `gameState`, browser-like headers, and only the variables actually used for the current lookup.
- Prevention: when mirroring a fragile third-party GraphQL endpoint, keep the payload shape as close as possible to a known-good browser request and omit null/empty variables instead of sending placeholder values.

## 2026-03-18 - Admin paginated fetch with PostgreSQL JSON relations needs a two-step query
- Symptom: the new admin catalog page failed under integration tests when a paginated Doctrine query used `DISTINCT` together with a fetch-join on `item_external_source.metadata` (JSON), causing PostgreSQL errors on `DISTINCT`/`ORDER BY` and JSON equality.
- Root cause: a single paginated fetch-join query tried to deduplicate joined external-source rows at SQL level while also ordering by item columns; PostgreSQL cannot compare JSON for `DISTINCT` deduplication in that shape, and `ORDER BY` with `SELECT DISTINCT` must stay inside the selected columns.
- Fix: switched the admin list read to a two-step approach: first fetch ordered distinct item IDs, then load the selected items with their external sources in a second query and reapply the ID order in PHP.
- Prevention: for paginated admin/search screens over entities that fetch-join JSON-backed relations, prefer a two-step `IDs first, graph second` query instead of a single `DISTINCT` fetch-join.

## 2026-03-18 - Source fields can describe the same business concept with different labels
- Symptom: merge/reporting showed `fandom.value_currency` and `fallout_wiki.type` as separate retained fields even when both described the same purchase currency (for example `Bottle cap` vs `caps`).
- Root cause: the cross-source merge treated raw source column names literally instead of recognizing that some providers encode the same business concept under different metadata keys and labels.
- Fix: added a canonical merge field `purchase_currency` and normalized known currency labels (`caps`, `gold_bullion`, `stamps`, `tickets`) before comparing or reporting them.
- Prevention: when two providers expose equivalent business meaning under different raw keys, prefer a derived canonical field in merge/reporting and keep raw labels only in source metadata.

## 2026-03-18 - Fallout Wiki acquisition taxonomies need derived boolean flags for practical filtering
- Symptom: `fallout_wiki` stored acquisition hints mostly as raw label arrays/objects (`Fallout 76 Locations`, `Quest`, `Bottle Cap`, etc.), which made filtering and field-by-field merge much less practical than Fandom's boolean availability flags.
- Root cause: the sync preserved wiki labels faithfully, but the import layer did not derive any canonical booleans from those labels.
- Fix: enriched `fallout_wiki` metadata during import with derived canonical flags (`containers`, `enemies`, `quests`, `vendors`, `world_spawns`, `seasonal_content`, `treasure_maps`) plus a derived `purchase_currency`, while keeping the raw `obtained` / `type` labels intact.
- Prevention: when a provider exposes categorical labels instead of direct booleans, derive canonical filterable flags at import time rather than forcing downstream code to reverse-engineer label arrays repeatedly.

## 2026-03-18 - Vocabulary audits must inspect raw source snapshots, not normalized import rows
- Symptom: it was tempting to inventory possible source values from already normalized import rows, but that loses the structure of fields like `fandom.availability` and collapses `fallout_wiki.obtained` objects into downstream derived flags.
- Root cause: the import normalization flattens boolean availability keys and later derives canonical booleans, which is correct for app behavior but no longer reflects the original snapshot vocabulary faithfully.
- Fix: added `app:data:report:source-vocabulary`, which reads the raw JSON snapshots directly and reports observed values from `fandom.availability`, `fallout_wiki.obtained`, and `fallout_wiki.type`.
- Prevention: when auditing source taxonomies or expanding source-label mappings, start from the raw sync snapshots rather than imported metadata or UI output.

## 2026-03-18 - Fallout Wiki uses both generic and provider-prefixed taxonomy labels
- Symptom: some raw `fallout_wiki` labels looked unmapped even though they expressed already-known concepts, for example `Fallout 76 Quests`, `Caps`, `bullion`, or `Scoreboard`.
- Root cause: the initial alias mapping covered the obvious generic forms (`Quest`, `Bottle Cap`, `Gold Bullion`, `Seasonal content`) but not all provider-prefixed or shortened variants exposed by the real snapshots.
- Fix: expanded the importer alias mapping so `Fallout 76 Quests` feeds `quests`, `Caps`/`bullion` feed vendor/currency derivation, and `Scoreboard` feeds `seasonal_content`.
- Prevention: after adding a raw-vocabulary audit, use it to normalize provider-specific label variants before introducing new business fields.

## 2026-03-18 - Raw vocabulary reports are much more useful when they show canonical coverage
- Symptom: a plain frequency list of raw labels was helpful, but it still required manual reasoning to tell whether a given label was already covered by current importer mappings.
- Root cause: the first version of `app:data:report:source-vocabulary` only showed observed labels and counts, not whether those labels already fed a canonical field such as `vendors`, `quests`, or `purchase_currency`.
- Fix: the report now computes `mapped_fields` for each raw label by passing it through the current enrichment rules, so covered labels and genuinely unmapped residue are immediately distinguishable.
- Prevention: when building source-audit reports meant to guide taxonomy cleanup, include both frequency and current canonical coverage in the output.

## 2026-03-18 - Taxonomy audit reports need an unmapped-only mode to stay actionable
- Symptom: even with `mapped_fields`, the full raw-vocabulary report remained noisy because the already-covered high-frequency labels dominated the top of the list.
- Root cause: the audit output mixed two goals: documenting the full raw vocabulary and prioritizing the next taxonomy work.
- Fix: added `--only-unmapped` to `app:data:report:source-vocabulary`, so the report can be used directly as a backlog of labels that still do not feed any canonical field.
- Prevention: when an audit report is used to guide follow-up cleanup, provide a filtered “actionable residue only” mode instead of forcing manual scanning through already-covered entries.

## 2026-03-18 - Coverage audits also need a mapped-only view
- Symptom: once `mapped_fields` and `--only-unmapped` existed, it was still awkward to review just the already-covered taxonomy surface without scanning the full mixed report.
- Root cause: the report had a residue-only mode but no symmetrical “coverage-only” mode.
- Fix: added `--only-mapped` to `app:data:report:source-vocabulary` and made it mutually exclusive with `--only-unmapped`.
- Prevention: when an audit command supports a residue-only filter, add the symmetric covered-only view too so validation and backlog triage use the same command shape.

## 2026-03-18 - Add new canonical flags only from generic, low-risk source labels first
- Symptom: the unmapped fallout.wiki residue includes many specific activity names (`The Big Bloom`, `Neurological Warfare`, `Giuseppe`, etc.), but not all of them are safe to classify automatically without inventing or overfitting taxonomy rules.
- Root cause: raw source labels mix generic categories (`Fallout 76 Events`, `Daily Ops`) with named activities, NPCs, and noisy concatenations.
- Fix: introduced canonical `events` and `daily_ops` only from generic, low-risk markers first (`Fallout 76 Events`, `Event`, `Mutated Public Events`, `Daily Ops`) and propagated them through merge/reporting.
- Prevention: extend taxonomy incrementally from generic labels first; only map specific named activities once the business concept and matching rules are clear enough to avoid false positives.
