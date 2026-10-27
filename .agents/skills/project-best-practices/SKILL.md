---
name: project-best-practices
description: "ALWAYS activate when producing, reviewing, or refactoring code in this project — PHP, Laravel, Filament, migrations, collections, or Blade. Holds this repository's own conventions for date arithmetic, query shape and collection pipelines, and points at the layering and review rules in docs/architecture.md and CODING_STANDARDS.md. These override generic Laravel defaults."
---

# Project Best Practices

Rules this repository enforces that generic Laravel guidance does not cover. Read the file for the area you are touching; where a rule here and a generic best practice disagree, this one wins.

- `rules/dates.md` — date arithmetic and comparison.
- `rules/queries.md` — filtering, ordering and per-parent picks in SQL.
- `rules/collections.md` — pipelines instead of `foreach` + `continue`.

Two project documents carry the rest and apply to every code change:

- `docs/architecture.md` — layer boundaries, repository naming and ownership, derived model attributes, Filament resource anatomy.
- `CODING_STANDARDS.md` — review-enforced conventions that no automated check covers.

Automated checks (`mago` and PHPStan) run through `./Taskfile lint`; they cover style and typing, not the rules here.
