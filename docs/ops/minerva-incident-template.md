# Minerva Incident Template

Use this template for every Minerva rotation incident.

## 1) Incident metadata
- Incident ID:
- Date/Time opened:
- Detector (name/team):
- Validator (product/owner):
- Executor (ops/admin):
- Severity: `minor` | `major`
- Status: `open` | `monitoring` | `resolved` | `closed`

## 2) Symptom
- What is wrong (user-visible):
- Scope (windows/locales impacted):
- First seen at:

## 3) Evidence (required before manual override)
- External source A (+ URL + timestamp):
- External source B (+ URL + timestamp):
- Internal observed data (`from/to`, `location`, `listCycle`):
- Why this is not a false positive:

## 4) Planned action
- [ ] Run targeted refresh/regeneration first
- [ ] Validate result in admin/front
- [ ] Create manual override only if divergence persists
- Override window (if needed): `from` / `to`
- Override reason:

## 5) Execution log
- Command/UI action:
- Operator:
- Timestamp:
- Result:

## 6) Closure checklist (mandatory)
- [ ] Regenerate impacted range after normalization
- [ ] Delete temporary manual override
- [ ] Verify `/minerva-rotation` (front)
- [ ] Verify `/admin/minerva-rotation` (admin)
- [ ] Add root cause summary
- [ ] Add prevention actions
- Closed at:

## 7) Post-incident notes
- Root cause:
- What worked well:
- What to improve:
- Follow-up tickets:
