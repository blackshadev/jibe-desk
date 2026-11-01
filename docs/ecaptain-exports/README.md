# e-Captain exports

Agent-facing runbooks for extracting data from the Almere-Centraal e-Captain CMS
at <https://almerecentraal-cms.e-captain.nl/index2.php>.

| Need | Runbook |
|---|---|
| Log in, tooling and CMS navigation | [ecaptain-login.md](./ecaptain-login.md) |
| Members, rental objects and RFID tags | [export-member-data.md](./export-member-data.md) |
| The Grootboek (chart of accounts) | [export-bookkeeping.md](./export-bookkeeping.md) |

## Ground rules

- Every runbook assumes a logged-in session. Start with
  [ecaptain-login.md](./ecaptain-login.md).
- Write every export to `exports/`. It is gitignored: the files contain member
  personal data and financial data and must never be committed.
- Keep e-Captain's native format for the Grootboek export. The member assignment
  exports are converted to the app's CSV dialect because an importer already
  consumes them.
- The exports are one-time migration inputs. Do not wire them into a schedule.

## What consumes each file

| File | Consumer |
|---|---|
| `exports/members-all.xlsx` | `app:import-members` |
| `exports/members-with-active-verhuurobject.csv` | `app:import-storage-space-rentals` |
| `exports/members-with-active-tags.csv` | `app:import-member-tags` |
| `exports/gboverzicht.csv` | No importer yet; target is `cost_centers` |

The cost-center import does not exist yet. [export-bookkeeping.md](./export-bookkeeping.md)
defines the export and the target mapping so the importer can be built against it.
