# e-Captain login, tooling and navigation

Shared prerequisites for every export in this folder.

## Credentials

Login needs THREE fields: gebruikersnaam, wachtwoord and pincode. There is no
way around it. Do not attempt to bypass it.

The credential is in 1Password: item `E-captain` in the `Private` vault of the
`my.1password.eu` account.

```bash
op read "op://Private/E-captain/username" --account my.1password.eu
op read "op://Private/E-captain/password" --account my.1password.eu
op read "op://Private/E-captain/pincode" --account my.1password.eu
```

Never write these values into a file or a commit. If the item is missing, open a
headed browser and let the user type the three fields.

Sessions expire. If a page redirects to <https://www.e-captain.nl/inloggen>,
stop and log in again in the visible window.

## Tooling

```bash
bun add -g agent-browser          # if missing
export PATH="$HOME/.bun/bin:$PATH"
export AGENT_BROWSER_SESSION="ecaptain-export"
agent-browser open "https://almerecentraal-cms.e-captain.nl/index2.php" --headed
```

- The CMS redirects to `https://www.e-captain.nl/inloggen` when there is no
  session.
- Switch tabs with `agent-browser tab t1` — `tab 1` does NOT switch.
- `eval` only runs in the top document. Everything else needs
  `window.frames["navi"].document` / `window.frames["main"].document`.
- The top-level page has no anchors; it is a frameset:
  `header`, `navi`, `main`, `footer`.
- Downloads land in `~/Downloads`.

## Logging in

Always start from the CMS URL (`index2.php`) so the redirect carries the right
tenant into the login page. Do not open `www.e-captain.nl/inloggen` directly.

The login form is `#caploginfrm` on <https://www.e-captain.nl/inloggen>:

| Field | Selector |
|---|---|
| Gebruikersnaam | `#loginname` |
| Wachtwoord | `#password` |
| Pincode | `#pincode` |
| Submit | `#btncaplogin` |

Fill the three inputs, click `#btncaplogin`, and wait for the redirect to
`https://almerecentraal-cms.e-captain.nl/index2.php`. The hidden `#dl` field can
stay empty.

```js
const setValue = (id, value) => {
  const input = document.getElementById(id);
  const setter = Object.getOwnPropertyDescriptor(
    window.HTMLInputElement.prototype,
    "value",
  ).set;
  setter.call(input, value);
  input.dispatchEvent(new Event("input", { bubbles: true }));
};

setValue("loginname", "<gebruikersnaam>");
setValue("password", "<wachtwoord>");
setValue("pincode", "<pincode>");
document.getElementById("btncaplogin").click();
```

## Navigating the CMS

Click nav menu items by id, with both events:

```js
const el = window.frames["navi"].document.querySelector('[id="3"]');
el.dispatchEvent(new MouseEvent("mousedown", { bubbles: true }));
el.click();
```

Module ids: `3` Ledenbeheer, `5` Verhuurobjecten, `10` Factureren,
`11` Boekhouding, `1` Beheercentrum, `4` Contact info, `14` Statistieken.

Boekhouding submenu ids: `1100` Boeken, `1101` Rapportage,
`11001` Grootboek inkijken, `1102` Verwerken, `1103` Afschrift inlezen,
`1104` Betaalbestand, `1105` Incasso crediteuren, `1114` Verrekenen,
`1106` Activa, `1107` Debiteuren, `1108` Crediteuren, `1109` Rapportgenerator.

Beheercentrum opens in the simple view (`100`) or the advanced view (`101`).

## Reports

System reports live either in Boekhouding → Rapportage (`#1101`) or in the
central Rapportage dashboard (`/reports/reports.php`). Each export runbook
documents the report it needs and the exact generation steps.

Text output is semicolon-delimited and ISO-8859-1 encoded. Parse it with
`encoding="latin-1", delimiter=";"`.

Never assume a download succeeded: check `~/Downloads` for the file and its row
count before continuing.
