# F76-148 - Roadmap manual JSON import fallback (AI-assisted)

## Status
`Done`

## Context
- OCR parsing of Fallout 76 roadmap images is good on dates but still inconsistent on some titles for older/multi-column seasons.
- A high-quality JSON can be produced manually from an external assistant UI (copy/paste), without requiring a paid API integration in the app.

## Goal
Provide an admin fallback flow to import a reviewed roadmap JSON payload directly, then publish it like regular parsed snapshots.

## Scope
- Add an admin form to paste/upload JSON payload (`season`, `name`, `events[]`).
- Validate payload strictly:
  - season number required,
  - date format `YYYY-MM-DD`,
  - `date_end >= date_start`,
  - non-empty title.
- Show preview + validation errors before persistence.
- Save as draft snapshot/events and keep existing approve/merge lifecycle.

### Progress
- Done: direct JSON import form (admin roadmap), strict payload validation, draft snapshot creation (`manual.json`), event persistence, and helper prompt modal for guided extraction workflow.
- Remaining: none in this ticket (future UX refinements tracked separately if needed).

## Out of scope
- Direct paid API integration (Claude Vision API, etc.).
- Automatic generation of JSON from image in this ticket.

## Notes
- This is a fallback path; OCR pipeline remains the default path.
- Keep auditability: actor, source, season, creation date.
