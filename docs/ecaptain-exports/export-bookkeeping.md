# Task: Export the e-Captain bookkeeping chart (Almere-Centraal) to CSV

## Prerequisites

Log in and open the CMS exactly as described in
[ecaptain-login.md](./ecaptain-login.md) — credentials, tooling and
[logging in](./ecaptain-login.md#logging-in). This runbook starts on the
logged-in CMS dashboard and does not repeat those steps. Module/submenu ids and
the frame-navigation pattern come from
[Navigating the CMS](./ecaptain-login.md#navigating-the-cms).

The cost-center import needs three files:

| File | Contents | Rows in the 2026-11-01 export |
|---|---|---|
| `exports/gboverzicht.csv` | Grootboekrekeningen | 197 accounts |
| `exports/verdichtingoverzicht.csv` | Verdichtingen and their hoofdverdichting | 34 groups |
| `exports/hoofdverdichtingoverzicht.csv` | Hoofdverdichtingen | 14 groups |

The e-Captain hierarchy is hoofdverdichting → verdichting → grootboekrekening.
The app models the two grouping levels as `CostCenterGroup` (Kostengroep) and the
account as `CostCenter` (Kostenplaats). `Verdichting` is no longer written to
`cost_centers.description`; that column stays as an optional admin note.

## Source — "Overzicht grootboekrekeningen"

The chart of accounts is a system report, not the maintenance grid.

- Module: Boekhouding (navi id `11`).
- Submenu: Rapportage (navi id `1101`), page
  `/finance/boekhouding/rapportage.php`.
- Report: **Overzicht grootboekrekeningen** (report key `gboverzicht`).
- Output: **Text (*.csv)**.
- Result: `~/Downloads/gboverzicht.csv`.

### Steps in the UI

1. Click navi `11` (Boekhouding), then submenu `1101` (Rapportage).
2. Select **Overzicht grootboekrekeningen** in the system-list select.
   The select's `onchange` immediately submits `#myform`, so the page reloads
   with the report selected and the **Bekijk lijst** button enabled.
3. Choose **Text (*.csv)** as output and click **Bekijk lijst**.
4. Move `~/Downloads/gboverzicht.csv` to `exports/gboverzicht.csv` unchanged.

### Steps with agent-browser

Assumes the logged-in session and open CMS from
[ecaptain-login.md](./ecaptain-login.md). Do it in two steps, because the report
select auto-submits and `#btnview` stays disabled until that submit has reloaded
the page:

```js
// Step 1: select the report. This submits the form and reloads the page.
const main = window.frames["main"].document;
const reportSelect = main.querySelector("select");
reportSelect.value = "gboverzicht";
reportSelect.dispatchEvent(new Event("change", { bubbles: true }));
```

```js
// Step 2: after the reload, choose the CSV output and generate.
const reloaded = window.frames["main"].document;
reloaded.getElementById("r_output").value = "text";
reloaded.getElementById("btnview").click();
```

Alternatively, submit the form directly in one shot. This bypasses the disabled
button and is the most reliable method:

```js
const main = window.frames["main"].document;
main.getElementById("r_report").value = "gboverzicht";
main.getElementById("r_output").value = "text";
main.getElementById("sender").value = "btnview";
main.getElementById("myform").submit();
```

Clicking `#btnview` without selecting the report first does nothing: the button
is disabled until the select fires its change handler.

### Fallback

The maintenance grid's XLS button is a valid fallback when the report is
unavailable. It returns the same columns minus `Nr   `, but the report is the
documented source.

## Source — "Overzicht verdichtingen"

- Module: Boekhouding (navi id `11`).
- Submenu: Rapportage (navi id `1101`).
- Report: **Overzicht verdichtingen** (report key `verdichtingoverzicht`).
- Output: **Text (*.csv)**.
- Result: `~/Downloads/verdichtingoverzicht.csv`.

The first column, `Nummer `, is the verdichting's own e-Captain number. The
unnamed third column is the **parent number** (the hoofdverdichting's number),
and the fourth column is the parent name. Copy the file to
`exports/verdichtingoverzicht.csv` unchanged.

## Source — "Overzicht hoofdverdichtingen"

- Module: Boekhouding (navi id `11`).
- Submenu: Rapportage (navi id `1101`).
- Report: **Overzicht hoofdverdichtingen** (report key
  `hoofdverdichtingoverzicht`).
- Output: **Text (*.csv)**.
- Result: `~/Downloads/hoofdverdichtingoverzicht.csv`.

The first column has no header but contains the hoofdverdichting number; the
second column is its name. Copy the file to
`exports/hoofdverdichtingoverzicht.csv` unchanged. `10 kapitaal` is the only
hoofdverdichting without children and therefore appears only in this file.

Generate the grouping reports the same way as the grootboek report: select the
report in the rapportage page, choose `Text (*.csv)`, and submit `#myform` with
`sender=btnview`.

## File shape (verified 2026-11-01)

All three files are ASCII / ISO-8859-1, semicolon-delimited, every field
double-quoted, LF line endings, and no trailing newline. Parse with
`encoding="latin-1", delimiter=";"`.

Exact headers:

```text
"Rekening";"Omschrijving";"B/W";"O/K/N";"D/C";"BTW";"Nr   ";"Verdichting"
"Nummer ";"Verdichting";"";"Hoofdverdichting"
"";"Hoofdverdichting"
```

`"Nr   "` and `"Nummer "` are padded with trailing spaces. Match every header
verbatim.

### Grootboek columns

| Column | Meaning |
|---|---|
| `Rekening` | Unique account number |
| `Omschrijving` | Account name |
| `B/W` | `B` = balans, `W` = winst & verlies |
| `O/K/N` | `O` = omzet, `K` = kosten, `N` = neutraal |
| `D/C` | Debit or credit side |
| `BTW` | VAT code; empty in this export |
| `Nr   ` | Verdichting number |
| `Verdichting` | Verdichting name |

The report has no `Actief`, `Einddatum` or `Kostenplaatsen` column. It is a full
snapshot: every account is returned, active or not. The maintenance grid
(`Beheercentrum → Boekhouding → Onderhoud grootboekschema`,
`/onderhoud/onderhoud.php?option=grootboek`) shows the same 197 rows. If a future
e-Captain version adds an active flag, exclude accounts with an `Einddatum`.

`Kostenplaatsen` in e-Captain is a separate dimension on a grootboekrekening. It
is not modeled by this import: the grootboekrekeningen themselves become the
app's cost centers.

### Verdichting and hoofdverdichting columns

| File | Column | Meaning |
|---|---|---|
| Verdichtingen | `Nummer ` | Verdichting's own number |
| Verdichtingen | `Verdichting` | Verdichting name |
| Verdichtingen | unnamed | Parent hoofdverdichting number |
| Verdichtingen | `Hoofdverdichting` | Parent hoofdverdichting name |
| Hoofdverdichtingen | unnamed | Hoofdverdichting number |
| Hoofdverdichtingen | `Hoofdverdichting` | Hoofdverdichting name |

e-Captain's own numbers collide across levels: verdichting `20` is
`kosten materiaal`, while hoofdverdichting `20` is `Kosten`. The import therefore
uses a qualified path as the app's identity: hoofdverdichting `20` becomes `20`,
its child verdichting `20` becomes `20.20`, and `external_number` keeps the
e-Captain number for matching.

## Field mapping

| e-Captain | App |
|---|---|
| Hoofdverdichting | `cost_center_groups` with qualified `number` = its own number, `parent_id` = null |
| Verdichting | `cost_center_groups` with qualified `number` = `{parent}.{own number}`, `parent_id` = the hoofdverdichting |
| `Rekening` | `cost_centers.number` |
| `Omschrijving` | `cost_centers.title` |
| `Verdichting` (via `Nr   `) | `cost_centers.cost_center_group_id` |

`B/W`, `O/K/N`, `D/C` and `BTW` are not stored. `cost_centers.description` is
never written by the import.

## Import

```shell
./Taskfile artisan app:import-cost-centers \
  exports/gboverzicht.csv \
  exports/verdichtingoverzicht.csv \
  exports/hoofdverdichtingoverzicht.csv
```

Use `--dry-run` to roll back the transaction after reporting, or
`--validate-only` to parse and cross-check the files without opening a write
transaction. The files own the tree: missing groups are created, names and
account titles are overwritten, every account is linked to its group, and
nothing is deleted. Database rows absent from the files are reported and left
untouched. A second run against unchanged files changes nothing.

The import fails, listing the offending numbers, when the three files disagree
about the hierarchy or when an account references a verdichting that is not in
the verdichtingen file.

## Existing cost centers

These numbers are already seeded in the app:

| Number | App title | e-Captain title | Kostengroep |
|---|---|---|---|
| `500` | Borg | Borg | `11 Overige vorderingen` |
| `3008` | Lesgelden | ihkomsten lessen geld lessers | `31 inkomsten lessen` |
| `8104` | Deelnemer bijdrage RTC | Deelnemer bijdrage (RTC) | `101 inkomsten algemeen` |
| `8105` | Contributie leden | Contributie Leden | `101 inkomsten algemeen` |
| `8115` | Inschrijf geld | Inschrijfgeld (verplicht voor nieuwe leden) | `101 inkomsten algemeen` |
| `8270` | Plankstalling | Plankstalling Inkomsten | `101 inkomsten algemeen` |

The import matches on `number` and **overwrites** `title`, including differences
like the 3008 typo. If a curated title must be kept, correct the Grootboek in
e-Captain before exporting; do not special-case it in the importer. The same
applies to group names: the files own them.

## Validation before you hand over

- `gboverzicht.csv`: 197 data rows.
- 197 unique `Rekening` values; all numeric (`1`–`10007` in this export).
- No empty `Omschrijving` or `Verdichting`.
- `B/W`: 47 `B`, 150 `W`.
- `O/K/N`: 56 `O`, 86 `K`, 55 `N`.
- `verdichtingoverzicht.csv`: 34 rows, all with a known parent in
  `hoofdverdichtingoverzicht.csv`.
- `hoofdverdichtingoverzicht.csv`: 14 rows, including `10 kapitaal`.
- Only 31 of the 34 verdichtingen are referenced by the grootboek export;
  `3 deelnemingen`, `60 kosten lessen extra` and `199 Verschillen` have no
  accounts. The import still creates them.
- The six seeded numbers above are present.
- Every row parses with `encoding="latin-1", delimiter=";"`.

If a count differs, investigate before reporting success.

## Known e-Captain data quirks

The importer copies these verbatim, as the files own the titles:

- `3008` is `ihkomsten lessen geld lessers`.
- `liquide middelen (bank/spaargeld` has an unclosed parenthesis.
- `inkomsten  RTC` has a double space.

Fix the source in e-Captain, not the importer.

## Output conventions

- Keep e-Captain's native filenames and format: `exports/gboverzicht.csv`,
  `exports/verdichtingoverzicht.csv`, `exports/hoofdverdichtingoverzicht.csv`.
- Do not convert the delimiter, encoding or dates. Nothing in these files is a
  date.
- Do not commit the files. `exports/` is gitignored and the files contain the
  club's financial chart.

## Do not use

- **Grootboekkaart** and **Grootboek Verwerkingsverslag** — these are mutations
  and journals, not the chart of accounts.

## Decisions (confirmed by the user 2026-11-01)

- **Contents:** the grootboek plus its verdichting and hoofdverdichting
  hierarchy. No bookings, mutations, balances or budgets.
- **Account scope:** every account, balance and profit & loss alike, becomes a
  cost center.
- **Inactive accounts:** the report exposes no active flag in this installation;
  export the full snapshot. Filter only if a future version exposes `Einddatum`.
- **Field mapping:** `number` = `Rekening`, `title` = `Omschrijving`,
  `cost_center_group_id` = the verdichting identified by `Nr   `. The other
  columns stay in the file for context and are not stored.
- **Existing numbers:** overwrite `title` from e-Captain.
- **Group names:** the files own them and overwrite the app's names.
- **Orphans:** nothing is deleted; database cost centers and groups without an
  e-Captain counterpart are reported.
- **Format:** keep e-Captain's native CSV; do not convert to the app's comma/UTF-8
  dialect.
- **Filenames:** keep the e-Captain filenames.
- **Importer:** `app:import-cost-centers`, artisan only; no Filament uploader.
