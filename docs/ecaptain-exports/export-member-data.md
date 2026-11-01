# Task: Export e-Captain member data (Almere-Centraal) to CSV

Requires a logged-in session; see [ecaptain-login.md](./ecaptain-login.md).

Export three files from the e-Captain CMS at
https://almerecentraal-cms.e-captain.nl/index2.php:

0. `exports/members-all.xlsx` — the complete member list, as produced by the saved
   report "Leden export" (report id 39). See "Source 5". This file is directly
   consumable by `MemberSpreadsheetReader` / `ImportMembersCommand`.

1. `exports/members-with-active-verhuurobject.csv` — one row per active rental
   object. Columns: `email,lidnr,objectnummer,start_date,end_date`. A member with
   multiple objects gets multiple rows.
2. `exports/members-with-active-tags.csv` — one row per active RFID tag.
   Columns: `email,lidnr,tagnummer,start_date,end_date`. A member with multiple
   active tags gets multiple rows; members with no active tag are omitted.
   An active tag has an empty `Stopdatum` and `Verloren` != `Ja`. See
   "Decisions" for the confirmed scope.

Both assignment files carry a period from e-Captain, not from the
planobject/relatieobject master record: e-Captain stores `start`/`eind` on the
planning and `Startdatum`/`Stopdatum` on the RFID tag.

The two assignment files feed the one-time `app:import-member-tags` and
`app:import-storage-space-rentals` commands. Both read `lidnr` as the key and
accept an `email` column that they ignore.

## Source 1 — active rental objects

`/objects/planlijst.php?mydate=DD-MM-YYYY` ("Lijst met planningen").

- 72 planningen, one per object. Scrape rows via elements matching
  `[onclick^=showrel]`; the `showrel(<relId>)` id is the member record id.
  Cells: `td[1]` = plaats (object number), `td[2]` = start (`DD-MM-YYYY`),
  `td[3]` = eind (`DD-MM-YYYY`).
- **`td[2]` and `td[3]` are the only source for the rental period.** A
  planobject's edit screen has no start/end fields (only type, number,
  description, prices and dimensions); the period belongs to the planning.
  Map `td[2]` -> `start_date`, `td[3]` -> `end_date`, normalised to
  `YYYY-MM-DD`.
- **`td[7]` is a colour square for INVOICE status, not rental status.** Ignore it.
- The `mydate` URL parameter does NOT filter anything — every date returns the
  same rows. The list is already the current season only.
- Active = `start <= today <= eind`. With today in the season, all 72 qualify.

## Source 2 — member id / lidnr / name

`/members/members.php?membermode=0&nohtml=-1` (207 members, all rendered
server-side, no pagination needed: "zichtbaar (1-207) Totaal: 207").

```js
const c = document.querySelector("#members_tablecontent");
// each data row: input[id^=cb_] -> relId (strip "cb_")
// td[3] = lidnr, td[8] = soortlid
// email cell: the <td> whose title attribute contains "@"
```

All 59 planning relations resolve to a lidnr.

## Source 3 — email addresses

Generate report 39 as CSV (see "Report generation"). Use `E-mail privé`, fall back
to `Contact.email` (in practice all 207 have `E-mail privé`). This is the same
source the repo's `MemberSpreadsheetReader` expects.

## Source 4 — RFID tags

Generate report 35 ("TAGs") as CSV. Columns:
`Lidnr (getal)`, `Achternaam`, `Nummer`, `Innamedatum`, `Startdatum`,
`Uitgiftedatum`, `Stopdatum`, `Verloren`.

- Active tag = `Stopdatum` empty AND `Verloren` != "Ja".
- **Date mapping:** `start_date` = `Startdatum`, `end_date` = `Stopdatum`,
  normalised to `YYYY-MM-DD`.
  - `Uitgiftedatum` is the issue date. It equals `Startdatum` in all but 3 rows,
    so use `Startdatum` for the period start.
  - `Innamedatum` is the return date, not the period end; `Stopdatum` is
    authoritative.
  - Because export 2 keeps only active tags (`Stopdatum` empty), `end_date` is
    blank on every row. If populated historical end dates are needed, include
    inactive tags for current members as well (88 rows instead of 78).
- Drop tags whose lidnr is not in the 207 current members (the 2026-10-31 report
  had 30 such rows across 27 lidnrs).
- Counts from the 2026-10-31 report: 118 tag rows total; 88 belong to current
  members; 78 are active across 73 members (5 members have 2 active tags).
- Tag numbers differ in format — 69 are 9 chars with leading zero, 4 are 8 chars
  without. Pass through verbatim unless told to normalise.

## Where the dates live (summary)

| Export | e-Captain source | Start field | End field |
|---|---|---|---|
| verhuurobjecten | Source 1, `/objects/planlijst.php` | `td[2]` | `td[3]` |
| objects / RFID tags | Source 4, report 35 | `Startdatum` | `Stopdatum` |

The object master screens do not store a period: planobjecten only have
type/number/description/prices/dimensions, and relatieobjecten only have
name/number/dimensions/free fields. The period always lives on the planning or
the RFID tag.

## Report generation (reusable)

`/reports/reports.php`, form `myform`. A plain POST returns the form again —
generation only happens by clicking `#btnview`.

```js
document.getElementById("r_report").value = "35";   // 35=TAGs, 39=Leden export
document.getElementById("r_output").value = "text"; // text=CSV, excel=xlsx
document.getElementById("btnview").click();
```

When the report page is inside the CMS frameset, prefix every lookup with
`window.frames["main"].document`.

The CSV lands in `~/Downloads/<reportname>.csv`, encoded ISO-8859-1 with a `;`
delimiter, LF line endings and no trailing newline. Parse with
`encoding="latin-1", delimiter=";"`.

Other report ids: `21` containerstalling, `32` RTC.

### xlsx does NOT auto-download

`r_output = "excel"` behaves differently: a new tab opens on
`/frameset.php?url=/members/openlink.php&file=<key>.xlsx&filename=39`, which only
says "Uw document staat klaar". Fetching that URL with curl returns a 1.8 KB HTML
page, not the file. The real download URL is inside that HTML:

```text
../getfile.php?file=<key>.xlsx&filename=39&<n>&cd=a
```

So: read the frameset page, extract the `getfile.php` link, then fetch it with the
session cookie:

```bash
curl -b "session=<cookie>" -o exports/members-all.xlsx \
  "https://almerecentraal-cms.e-captain.nl/getfile.php?file=<key>.xlsx&filename=39&<n>&cd=a"
```

`file` reports `Microsoft Excel 2007+`. Get the cookie with
`agent-browser cookies get`.

## Source 5 — full member export (xlsx)

Report 39 is the single "Leden export" entry (there is no separate user-saved
copy, despite it being described as a saved template).

Expected shape — verify all four before handing over:

- sheet name `Worksheet`
- row 1 = `Leden export` (title), row 2 = the real header
- 207 data rows, 102 columns
- all 20 columns required by `MemberSpreadsheetReader::REQUIRED_HEADERS` present
  (`Lidnr` at index 26, `E-mail privé` at index 78)

This is the safest check, run on the host (the `Taskfile` passes its argument
through `sh -c`, so `--execute` with quotes/parentheses gets mangled):

```php
require 'vendor/autoload.php';
$rows = (new App\Infrastructure\Members\Import\MemberSpreadsheetReader())
    ->read($base . '/exports/members-all.xlsx');
echo count($rows); // 207
```

## Do not use

- **`containerstalling` (report 21) looks perfect and is wrong.** It lists
  invoiced articles across 2018–2026 (only 12 rows for 2026). Not current
  planningen.
- `/settings/cms2/managetags` — CMS content tags, 0 rows.
- `/onderhoud/onderhoud.php?option=captainaccess` — IP allowlist, unrelated.

Tag issuance lives at `/onderhoud/onderhoud.php?option=rfiduitgifte`, but it has
only a per-tag lookup (`/onderhoud/objects/rfid.ajax.php?sn=<number>`) and a
complement list, so use report 35 instead.

## Validation before you hand over

- `/onderhoud/objects/rfid_uitgifte.php?act=selectcardless` reports members with
  no tag. As of 2026-10-31: 131. So 207 − 131 = 76 members have any tag row, of
  which 73 have an active tag. If your numbers differ, investigate before
  reporting success.
- Assert every planning relId resolves to a lidnr, and every lidnr exists in the
  Leden export.
- Assert every `start_date` and `end_date` parses as a date and, where both are
  present, `start_date <= end_date`. Export 1 must have both dates on every row;
  export 2 has `end_date` empty on every active-tag row.
- Expect: export 1 = 72 rows (one per active planning) / 59 distinct members /
  9 members with >1 object.
  export 2 = 78 rows (one per active tag for current members) / 73 distinct
  members / 5 members with >1 active tag; no rows for members without tags.
  export 3 (xlsx) = 207 data rows.

## Output conventions

- One row per object/tag, not one row per member.
- Dates in `YYYY-MM-DD` (e-Captain shows `DD-MM-YYYY`; convert on export).
- Comma delimiter, UTF-8, RFC4180 quoting. Note: Dutch-locale Excel puts this in
  one column — offer a `;` variant if the user will open it in Excel.
- Write to `exports/`. Do not add the raw source CSVs to the repo.
- `exports/` is gitignored; the generated files contain member personal data and
  must not be committed.

## Decisions (confirmed by the user 2026-11-01)

These were open questions in earlier drafts. They are settled; do not re-ask
unless a rerun produces different counts.

- **Members with zero tags:** omit them. One row per active tag, no placeholder
  rows. Export 2 therefore contains only the 73 members that own an active tag.
- **Inactive tags:** exclude them. Export 2 keeps only tags with an empty
  `Stopdatum`, so `end_date` is blank on every row. Historical end dates are out
  of scope; including inactive tags would give 88 rows.
- **Lost tags:** do not count as active. `Verloren = Ja` rows are dropped even
  when `Stopdatum` is empty.

Consequences for validation: export 2 must be 78 rows / 73 members / 5 members
with more than one active tag, and every `end_date` must be empty.
