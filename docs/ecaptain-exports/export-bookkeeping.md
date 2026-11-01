# Task: Export the e-Captain Grootboek (Almere-Centraal) to CSV

Requires a logged-in session; see [ecaptain-login.md](./ecaptain-login.md).

Export the complete Grootboek from the e-Captain CMS to
`exports/gboverzicht.csv`. The file is the source for the app's cost centers:

| Grootboek column | `cost_centers` column |
|---|---|
| `Rekening` | `number` |
| `Omschrijving` | `title` |
| `Verdichting` | `description` |

Every account becomes a `CostCenter`, including balance accounts. The app has no
cost-center importer yet; this document defines the export and the target
mapping so the importer can be built against it. Do not report the import as done.

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

Do it in two steps, because the report select auto-submits and `#btnview` stays
disabled until that submit has reloaded the page:

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

## File shape (verified 2026-11-01)

- 197 data rows plus one header row, 198 lines total.
- Semicolon delimiter, every field double-quoted, no trailing newline.
- ASCII / ISO-8859-1, LF line endings. Parse with
  `encoding="latin-1", delimiter=";"`.
- Exact header:

```text
"Rekening";"Omschrijving";"B/W";"O/K/N";"D/C";"BTW";"Nr   ";"Verdichting"
```

`"Nr   "` is padded with three trailing spaces. Match the header verbatim.

### Columns

| Column | Meaning |
|---|---|
| `Rekening` | Unique account number |
| `Omschrijving` | Account name |
| `B/W` | `B` = balans, `W` = winst & verlies |
| `O/K/N` | `O` = omzet, `K` = kosten, `N` = neutraal |
| `D/C` | Debit or credit side |
| `BTW` | VAT code; empty in this export |
| `Nr   ` | Verdichting number |
| `Verdichting` | Verdichting (grouping) name |

The report has no `Actief`, `Einddatum` or `Kostenplaatsen` column. It is a full
snapshot: every account is returned, active or not. The maintenance grid
(`Beheercentrum → Boekhouding → Onderhoud grootboekschema`,
`/onderhoud/onderhoud.php?option=grootboek`) shows the same 197 rows. If a future
e-Captain version adds an active flag, exclude accounts with an `Einddatum`.

`Kostenplaatsen` in e-Captain is a separate dimension on a grootboekrekening. It
is not what this export maps: the grootboekrekeningen themselves become the app's
cost centers.

## Existing cost centers

These numbers are already seeded in the app:

| Number | App title | e-Captain title |
|---|---|---|
| `500` | Borg | Borg |
| `3008` | Lesgelden | ihkomsten lessen geld lessers |
| `8104` | Deelnemer bijdrage RTC | Deelnemer bijdrage (RTC) |
| `8105` | Contributie leden | Contributie Leden |
| `8115` | Inschrijf geld | Inschrijfgeld (verplicht voor nieuwe leden) |
| `8270` | Plankstalling | Plankstalling Inkomsten |

The import must match on `number` and **overwrite** `title` and `description` from
e-Captain, including differences like the 3008 typo. If a curated title must be
kept, correct the Grootboek in e-Captain before exporting; do not special-case it
in the importer.

## Validation before you hand over

- 197 data rows.
- 197 unique `Rekening` values; all numeric (`1`–`10007` in this export).
- No empty `Omschrijving` or `Verdichting`.
- `B/W`: 47 `B`, 150 `W`.
- `O/K/N`: 56 `O`, 86 `K`, 55 `N`.
- The six seeded numbers above are present.
- The row count equals the row count of the maintenance grid.
- Every row parses with `encoding="latin-1", delimiter=";"`.

If a count differs, investigate before reporting success.

## Output conventions

- Keep e-Captain's native filename and format: `exports/gboverzicht.csv`.
- Do not convert the delimiter, encoding or dates. Nothing in this file is a date.
- Do not commit the file. `exports/` is gitignored and the file contains the
  club's financial chart.

## Do not use

- **Overzicht verdichtingen** and **Overzicht hoofdverdichtingen** — these are
  groupings, not accounts.
- **Grootboekkaart** and **Grootboek Verwerkingsverslag** — these are mutations
  and journals, not the chart of accounts.

## Decisions (confirmed by the user 2026-11-01)

- **Contents:** the Grootboek only. No bookings, mutations, balances or budgets.
- **Account scope:** every account, balance and profit & loss alike, becomes a
  cost center.
- **Inactive accounts:** the report exposes no active flag in this installation;
  export the full snapshot. Filter only if a future version exposes `Einddatum`.
- **Field mapping:** `number` = `Rekening`, `title` = `Omschrijving`,
  `description` = `Verdichting`. The other columns stay in the file for context.
- **Existing numbers:** overwrite `title` and `description` from e-Captain.
- **Format:** keep e-Captain's native CSV; do not convert to the app's comma/UTF-8
  dialect.
- **Filename:** keep e-Captain's `gboverzicht.csv`.
- **Importer:** out of scope. No cost-center importer exists yet.
