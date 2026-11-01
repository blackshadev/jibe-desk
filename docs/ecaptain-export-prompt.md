# Task: Export e-Captain member data (Almere-Centraal) to CSV

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

## Access

- Login needs THREE fields: gebruikersnaam, wachtwoord AND pincode. There is no
  way around this. Do not attempt to bypass it.
- The credentials live in 1Password and are read with the 1Password CLI. All
  three fields are there, so login is fully automated — verified 2026-11-01.
- Sessions expire. If pages redirect to https://www.e-captain.nl/inloggen, run
  the login block below again (no need to ask the user).

### Login with the 1Password CLI

The item is `E-captain` in the `Private` vault of the `my.1password.eu` account
(item id `ynq6rw7dfsbnqdimokkqw2ovu4`). Its fields are `username`, `password`
and `pincode`, which map 1:1 onto the three form inputs.

Three gotchas, all of them silent failures if you get them wrong:

- **`--account my.1password.eu` is mandatory.** Two accounts are configured and
  the default is `enrise.1password.eu`, which does not hold this item. Without
  the flag `op` answers `"<uuid>" isn't an item. Specify the item with its UUID,
  name, or domain.` — the item really exists, so this looks like a stale id.
- **`--reveal` is mandatory** for the concealed fields, otherwise `op` prints
  `[use 'op item get <id> --reveal' to reveal]` and you end up typing that
  message into the form.
- **The login page is on a different host than the CMS.** The `index2.php`
  frameset is on `almerecentraal-cms.e-captain.nl`, but the login form is on
  `https://www.e-captain.nl/inloggen` after the redirect.

The unlock/approval happens through the 1Password desktop app (CLI integration,
`op` 2.39.0). If the app is locked you get `account is not signed in`; unlock
the app or run `op signin --account my.1password.eu` once.

```bash
export PATH="$HOME/.bun/bin:$PATH"
export AGENT_BROWSER_SESSION="ecaptain-export"

OP_ACCOUNT="my.1password.eu"
OP_ITEM="ynq6rw7dfsbnqdimokkqw2ovu4"          # item "E-captain", vault "Private"

agent-browser cookies clear                    # force the form, even with a stale cookie
agent-browser open "https://almerecentraal-cms.e-captain.nl/index2.php" --headed
# -> redirects to https://www.e-captain.nl/inloggen

export ECAP_USER=$(op item get "$OP_ITEM" --account "$OP_ACCOUNT" --fields username --reveal)
export ECAP_PASS=$(op item get "$OP_ITEM" --account "$OP_ACCOUNT" --fields password --reveal)
export ECAP_PIN=$(op item get "$OP_ITEM" --account "$OP_ACCOUNT" --fields pincode --reveal)
test -n "$ECAP_USER" && test -n "$ECAP_PASS" && test -n "$ECAP_PIN" || echo "op read failed"
```

Fill and submit. Pipe the script through `--stdin` so the secrets never land in
a file or in `agent-browser`'s argv, and let Python do the JSON escaping:

```bash
python3 - <<'PY' | agent-browser eval --stdin
import json, os
user, password, pin = (json.dumps(os.environ[key]) for key in ("ECAP_USER", "ECAP_PASS", "ECAP_PIN"))
print(f"""(() => {{
  const values = {{ loginname: {user}, password: {password}, pincode: {pin} }};
  for (const [id, value] of Object.entries(values)) {{
    const el = document.getElementById(id);
    el.focus();
    el.value = value;
    el.dispatchEvent(new Event("input", {{ bubbles: true }}));
    el.dispatchEvent(new Event("change", {{ bubbles: true }}));
  }}
  document.getElementById("btncaplogin").click();
  return "submitted";
}})()""")
PY
```

- Input ids: `#loginname`, `#password`, `#pincode`. Submit is
  `#btncaplogin`, a `<button type="button">` with a JS handler — `form.submit()`
  and a synthetic Enter keystroke do nothing, so `.click()` it.
- The page has two extra unnamed password inputs; ignore them.
- `unset ECAP_PASS ECAP_PIN` afterwards; the session cookie lives in the browser,
  not in the shell.

Verify the session landed (should print `submitted`, then a welcome line from
`/home/home.php`, i.e. `Welkom <naam> (7 / 26)`):

```bash
sleep 4
agent-browser eval '(() => { const f = window.frames["main"]; return JSON.stringify({ mainUrl: f.location.href, text: f.document.body.innerText.slice(0, 80) }); })()'
agent-browser eval '(() => { window.frames["main"].location.href = "/members/members.php?membermode=0&nohtml=-1"; return "nav"; })()'
sleep 4
agent-browser eval '(() => { const doc = window.frames["main"].document; return doc.querySelector("#members_tablecontent").querySelectorAll("tr").length; })()'
# -> 208 (1 header + 207 members), proving the session is usable
```

If the item id ever changes, find it again with:

```bash
op item list --account my.1password.eu --format json \
  | python3 -c 'import json,sys; [print(i["id"], i["title"], i["vault"]["name"]) for i in json.load(sys.stdin) if "captain" in i["title"].lower()]'
```

## Tooling

```bash
bun add -g agent-browser          # if missing
export PATH="$HOME/.bun/bin:$PATH"
export AGENT_BROWSER_SESSION="ecaptain-export"
agent-browser open "https://almerecentraal-cms.e-captain.nl/index2.php" --headed
```

- Switch tabs with `agent-browser tab t1` — `tab 1` does NOT switch.
- `eval` only runs in the top document. Everything else needs
  `window.frames["navi"].document` / `window.frames["main"].document`.
- Top-level page has no anchors; it is a frameset:
  `header`, `navi`, `main`, `footer`.

Four shapes the snippets below rely on:

- **Wrap every snippet in an IIFE.** `eval` shares one page scope across calls,
  so a top-level `const` survives and the next snippet that reuses the name dies
  with `Identifier 'd' has already been declared`.
- **Index the frames.** `window.frames` is array-like, not iterable:
  `for (const f of window.frames)` throws. Use `window.frames[i]`.
- **Do not pass `--json`.** It wraps the result in a ~250-token lifecycle block
  (launch hash, `saveStatus`, …). Plain output returns the bare string.
- **Re-select `t1` after generating an xlsx.** The report popup becomes the
  active tab, and the next snippet then fails with
  `Cannot read properties of undefined (reading 'document')`.

The canonical read:

```js
(() => {
  const doc = window.frames["main"].document;
  return JSON.stringify({ href: window.frames["main"].location.href });
})()
```

Navigate the content frame by assigning its `location`; the frameset stays put:

```js
(() => { window.frames["main"].location.href = "/objects/planlijst.php?mydate=01-11-2026"; return "nav"; })()
```

## Navigation

Click nav menu items by id, with both events:

```js
(() => {
  const el = window.frames["navi"].document.querySelector('[id="3"]');
  el.dispatchEvent(new MouseEvent("mousedown", { bubbles: true }));
  el.click();
  return "clicked";
})()
```

Module ids: `3` Ledenbeheer, `5` Verhuurobjecten, `1` Beheercentrum,
`4` Contact info, `10` Factureren, `14` Statistieken.

## Source 1 — active rental objects

`/objects/planlijst.php?mydate=DD-MM-YYYY` ("Lijst met planningen").

- 72 planningen, one per object. Scrape rows via elements matching
  `[onclick^=showrel]`; the `showrel(<relId>)` id is the member record id.
  Indices are **0-based `cells[i]`**, as in Source 2: `cells[1]` = plaats (object
  number), `cells[2]` = start (`DD-MM-YYYY`), `cells[3]` = eind (`DD-MM-YYYY`).
- **`cells[2]` and `cells[3]` are the only source for the rental period.** A
  planobject's edit screen has no start/end fields (only type, number,
  description, prices and dimensions); the period belongs to the planning.
  Map `cells[2]` -> `start_date`, `cells[3]` -> `end_date`, normalised to
  `YYYY-MM-DD`.
- **`cells[7]` is a colour square for INVOICE status, not rental status**
  (`<div class="p1" style="width:24px; height:24px">`). Ignore it.
- The `mydate` URL parameter does NOT filter anything — every date returns the
  same rows. The list is already the current season only.
- Active = `start <= today <= eind`. With today in the season, all 72 qualify.

A row reads:

```text
[0] "Achternaam, Voornaam"   [1] "3-02"   [2] "01-01-2026"   [3] "31-12-2026"
[4] ""   [5] ""   [6] ""   [7] <div class="p1">   [8] "60.00"   [9] <img>
```


## Source 2 — member id / lidnr / name

`/members/members.php?membermode=0&nohtml=-1` (207 members, all rendered
server-side, no pagination needed: "zichtbaar (1-207) Totaal: 207").

```js
(() => {
  const doc = window.frames["main"].document;
  const table = doc.querySelector("#members_tablecontent");
  const rows = [...table.querySelectorAll("tr")].map(tr => {
    const checkbox = tr.querySelector("input[id^=cb_]");
    const cells = [...tr.querySelectorAll("td")];
    if (!checkbox) { return null; }                       // header row
    const emailCell = cells.find(c => (c.getAttribute("title") || "").includes("@"));
    return {
      relId: checkbox.id.replace("cb_", ""),
      lidnr: cells[3].innerText.trim(),
      naam: cells[1].innerText.trim(),
      soortlid: cells[8].innerText.trim(),
      email: emailCell ? emailCell.getAttribute("title").trim() : "",
    };
  }).filter(Boolean);
  return JSON.stringify({ count: rows.length, rows });
})()
```

Indices are **0-based `cells[i]`**, not CSS `td:nth-child`. A row reads:

```text
["", "Achternaam, Voornaam", "€", "123456", "Straatnaam 1",
 "Almere", "", "+31600000000", "Volwassenlid", "", "06-02-2016", …]
```

The table holds 208 `tr`s: one header (no `cb_` checkbox) plus 207 data rows.

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
- Tag numbers differ in format — 73 are 9 chars with a leading zero, 5 are 8
  chars without. Pass them through verbatim. Two of the 9-char numbers arrive
  padded with a trailing space (`"012183500 "`); trim surrounding whitespace, but
  do not strip or add the leading zero.

## Where the dates live (summary)

| Export | e-Captain source | Start field | End field |
|---|---|---|---|
| verhuurobjecten | Source 1, `/objects/planlijst.php` | `cells[2]` | `cells[3]` |
| objects / RFID tags | Source 4, report 35 | `Startdatum` | `Stopdatum` |

The object master screens do not store a period: planobjecten only have
type/number/description/prices/dimensions, and relatieobjecten only have
name/number/dimensions/free fields. The period always lives on the planning or
the RFID tag.

## Report generation (reusable)

`/reports/reports.php`, form `myform`. A plain POST returns the form again —
generation only happens by clicking `#btnview`.

`#r_report` is a **hidden input**. The list the user sees is the second `<select>`
on the page (`[0]` system lists, `[1]` your own lists, `[2]` output), and its
`onchange` is what normally sets `r_report`. Set both, or the click generates
whatever report was selected before.

```js
(() => {
  const doc = window.frames["main"].document;
  const reportList = [...doc.querySelectorAll("select")][1];

  reportList.value = "35";                                  // 35=TAGs, 39=Leden export
  reportList.dispatchEvent(new Event("change", { bubbles: true }));
  doc.getElementById("r_report").value = "35";
  doc.getElementById("r_output").value = "text";            // text=CSV, excel=xlsx
  doc.getElementById("btnview").click();

  return "clicked";
})()
```

The CSV lands in `~/Downloads/<reportname>.csv`, encoded ISO-8859-1 with `;`
delimiter and CRLF. Parse with `encoding="latin-1", delimiter=";"`.

Other report ids: `21` containerstalling, `32` RTC.

### xlsx does NOT auto-download

`r_output = "excel"` behaves differently: a new tab opens on
`/frameset.php?url=/members/openlink.php&file=<key>.xlsx&filename=39`, which only
says "Uw document staat klaar". The inner frame of that tab holds the real link:

```text
../getfile.php?file=<key>.xlsx&filename=39&<n>&cd=a
```

`agent-browser cookies get` returns `[]` — the session cookie is HttpOnly, so
there is no cookie to hand to curl. Download through the browser instead, which
sends the cookie for you:

1. Read the link out of the frameset tab's inner frame:

   ```js
   (() => {
     const frame = window.frames[0];
     return frame.document.querySelector("a[href*=getfile]").getAttribute("href");
   })()
   ```

2. Navigate that same tab to the absolute `getfile.php` URL. Chrome saves the
   file to `~/Downloads/`, named after the `filename` parameter
   (`Leden_export.xlsx` for report 39).

   ```bash
   agent-browser eval 'location.href = "https://almerecentraal-cms.e-captain.nl/getfile.php?file=<key>.xlsx&filename=39&<n>&cd=a"'
   ls -la ~/Downloads/*.xlsx
   ```

3. `cp ~/Downloads/Leden_export.xlsx exports/members-all.xlsx`, then switch back
   with `agent-browser tab t1`.

`file` should report `Microsoft Excel 2007+`.

## Source 5 — full member export (xlsx)

Report 39 is the single "Leden export" entry (there is no separate user-saved
copy, despite it being described as a saved template).

Expected shape — verify all four before handing over:

- sheet name `Worksheet`
- row 1 = `Leden export` (title), row 2 = the real header
- 207 data rows, 102 columns
- all 20 columns required by `MemberSpreadsheetReader::REQUIRED_HEADERS` present
  (`Lidnr` at index 26, `E-mail privé` at index 78)

This is the safest check, and it runs through the Taskfile like every other
command:

```bash
./Taskfile artisan app:import-members exports/members-all.xlsx --validate-only
```

`--validate-only` reads the file through `MemberSpreadsheetReader` and maps every
row through the same parsers an import uses, then reports what an import would
do — leavers, rows without a membership type, email mismatches. It bypasses the
empty-table guard and never opens a transaction, so it runs against the seeded
dev database and writes nothing.

Expect:

```text
Validation complete. The spreadsheet is valid and nothing was written.
| Imported members          | 185   |
| Stopped members           | 22    |
```

185 + 22 = 207 data rows. `--dry-run` is the other mode: it performs the import
inside a transaction and rolls it back, which still requires an empty `members`
table.

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
