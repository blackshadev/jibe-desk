# Nederlandse vertaling voor "Invoice Batch"

## Samenvatting

1. **Aanbevolen:** **Facturatieronde** — het meest begrijpelijk voor gewone gebruikers, omdat het een verwerkingsmoment aanduidt en niet alleen een technische groep.
2. **Goed alternatief:** **Facturatiebatch** — duidelijker dan "Factuurbatch" als je dichtbij de huidige term wilt blijven.
3. **Alleen als de bankstap centraal staat:** **Incassobatch** — passend voor SEPA/bank, maar minder goed voor klantcommunicatie.

## Advies

Mijn sterkste advies is om **Invoice Batch** te vertalen als **Facturatieronde**.

Waarom:
- In deze applicatie is het niet alleen een lijstje facturen, maar een **operationele stap**: facturen toevoegen, batch sluiten, batch afronden en **SEPA exporteren**.
- Voor gebruikers klinkt **ronde** natuurlijker dan **batch**.
- Het dekt zowel communicatie naar klanten als verwerking richting bank.

## Alternatieven

### 1. Facturatieronde
Beste keuze als je wilt dat niet-technische gebruikers het meteen begrijpen.

Past goed bij teksten zoals:
- "Nieuwe facturatieronde"
- "Facturatieronde sluiten"
- "Facturatieronde exporteren"

### 2. Facturatiebatch
Beste keuze als je functioneel dicht bij de huidige benaming wilt blijven, maar wel net iets duidelijker wilt zijn dan "Factuurbatch".

### 3. Incassobatch
Alleen gebruiken als de nadruk vooral ligt op de **bank/SEPA-incasso**. Minder geschikt als dezelfde term ook zichtbaar is bij klantgerichte facturatie.

## Waarom ik **Factuurbatch** minder sterk vind

De codebase gebruikt nu al **Factuurbatch** als label, maar voor veel gebruikers is **batch** vrij technisch of intern jargon.

## Codebase-context

- De huidige UI-labels gebruiken nu **`Factuurbatch`** en **`Factuurbatchen`** in `lang/nl/labels.php:122-123`.
- De workflow laat zien dat dit object een processtap is met acties als **`Batch sluiten`**, **`Batch afronden`** en **`SEPA export`** in `lang/nl/labels.php:127-129`.
- In de bewerkpagina horen daar concrete acties bij: **close batch**, **complete batch** en **export SEPA** in `app/Filament/Admin/Resources/InvoiceBatches/Pages/EditInvoiceBatch.php:79-121`.
- Het model koppelt meerdere facturen aan één batch en rekent aantallen/open bedragen uit in `app/Models/InvoiceBatch.php:27-76`.
- De database bevestigt dat een batch facturen groepeert via `invoice_batch_id` in `database/migrations/2026_05_19_193924_create_invoice_batches_table.php:12-20`.

## Bronnen

### Intern
- `lang/nl/labels.php:117-129`
- `app/Filament/Admin/Resources/InvoiceBatches/InvoiceBatchResource.php:47-57`
- `app/Filament/Admin/Resources/InvoiceBatches/Pages/EditInvoiceBatch.php:79-121`
- `app/Models/InvoiceBatch.php:27-76`
- `database/migrations/2026_05_19_193924_create_invoice_batches_table.php:12-20`

### Extern
- Digital.gov — *Principles of plain language*: schrijf in woorden die aansluiten op de gebruiker en vermijd jargon. https://digital.gov/guides/plain-language/principles/
- Nielsen Norman Group — *10 Usability Heuristics for User Interface Design*, vooral "Match Between the System and the Real World": gebruik woorden en concepten die gebruikers kennen. https://www.nngroup.com/articles/ten-usability-heuristics/
