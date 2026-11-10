# AGENTS.md

## Agent skills

### Issue tracker

Issues and specs live as local markdown files under `.scratch/<feature>/`. See `docs/agents/issue-tracker.md`.

### Triage labels

Default five canonical triage roles (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs & ADRs

Single-context layout (`GLOSSARY.md` at root and `docs/adr/`). See `docs/agents/domain.md`.

### Architecture & Service Boundaries

Controllers coordinate requests and delegate domain logic and database transactions to dedicated services in `app/Services/`. Service boundaries and catalog live in `docs/architecture/services.md`. Mechanically enforced by `tests/ArchTest.php`.

### Coding Standards

Judgement calls, concurrency patterns, and review rules live in `CODING_STANDARDS.md`. Consulted during `/code-review`.

