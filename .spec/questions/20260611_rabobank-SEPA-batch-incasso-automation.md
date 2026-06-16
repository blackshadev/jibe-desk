# Rabobank SEPA Batch Incasso Automation

## Context

Our invoices need to be paid through a SEPA Direct Debit request. Currently we perform this action manually on the Rabobank website because we have permission to create a SEPA request to all our members. We want to automate this process.

## Current State of the Codebase

The codebase already has a **solid foundation** for building SEPA direct debit functionality:

- **Invoicing is complete**: `Invoice`, `InvoiceBatch`, `InvoiceLine` models with batch generation, billing applicators for memberships/activities/rentals/households, and a Filament admin UI.
- **Banking data collection is in place**: Members provide IBAN, BIC, account holder name, and accept a SEPA mandate during registration. This data is stored in the `payment_information` table with a UUID that can serve as a mandate reference.
- **What is missing is the entire "last mile"**: everything between having an invoice + member banking data and actually collecting the money. This includes SEPA XML (pain.008) generation, pre-notification, bank file submission, payment status tracking, and reconciliation.

---

## Mandate Reference & Creditor Identifier

There are two distinct identifiers in SEPA Direct Debit that are often confused. Both are required in every `pain.008` message.

### Mandate Reference (`MndtId`) — you generate this yourself

The mandate reference identifies a **specific authorization** from a specific member. **You (the creditor) assign it** — it is NOT provided by the bank or any central authority. It can be any unique string you choose, subject to these constraints:

| Constraint     | Value                                                                                                                 |
| -------------- | --------------------------------------------------------------------------------------------------------------------- |
| **Max length**     | **35 characters**                                                                                                         |
| **Allowed chars**  | Alphanumeric + limited special chars (`/`, `-`, `.`, `+`) — stick to alphanumeric for maximum compatibility across PSPs |
| **Uniqueness**     | Must be unique **per creditor** (the combination of Creditor ID + Mandate Reference is globally unique)                 |
| **Structure**      | None mandated — free-form string                                                                                      |

#### ⚠️ Current `uuid` column exceeds the 35-character limit

The `PaymentInformation` model currently uses a `uuid` column as the mandate reference. Standard UUIDs with hyphens are **36 characters** — that exceeds the SEPA limit. Options:

- **Strip hyphens** — a 32-character hex UUID fits perfectly (e.g. `a1b2c3d4e5f67890abcdef1234567890`)
- **Use a member-based format** — e.g. `MBR-00042-01` (member number + sequence). This is human-readable and helps with reconciliation when Rabobank sends back rejection reports.
- **Database auto-increment with prefix** — e.g. `DD-000042`. Simple and auditable.

#### Uniqueness scope

The mandate reference must be unique **within your organization** (per Creditor Identifier). If a member signs two separate mandates (e.g. for two different services), each must have a different `MndtId`. Across different creditors, the same string can be reused because the Creditor Identifier differentiates them.

### Creditor Identifier (`CdtrSchmeId`) — obtained from DNB via Rabobank

This is separate from the mandate reference and identifies **your organization** as a SEPA creditor. Format: `NL` + `ZZZ` + business code (e.g. `NL53ZZZ091737840000`).

|             | **Creditor Identifier**                                           | **Mandate Reference**                            |
| ----------- | ------------------------------------------------------------- | -------------------------------------------- |
| **What**        | Identifies **who is collecting** (your club)                      | Identifies **which authorization** (per member)  |
| **Who assigns** | DNB (De Nederlandsche Bank), via your bank                    | You generate it yourself                     |
| **How many**    | One per organization                                          | One per signed mandate (potentially hundreds) |
| **How to get**  | Contact Rabobank — they request it from DNB on your behalf    | Generate it in your app                      |
| **Format**      | Structured: `CC` + `ZZZ` + `BusinessCode` (e.g. `NL53ZZZ091737840000`) | Free-form alphanumeric, max 35 chars          |

If you're already submitting SEPA batches manually via Rabobank Online, **you almost certainly already have a Creditor Identifier**. Check your existing pain.008 files or your Rabobank business portal settings.

### The Mandate Document

The debtor signs a SEPA mandate form that must contain:

1. The mandate reference (which you pre-fill)
2. Your Creditor Identifier
3. Debtor's name, IBAN, and optionally BIC
4. The standard EPC legal text (Dutch: *"Ik machtig hierbij [creditor] om doorlopende automatische incasso's af te geven van mijn rekening..."*)
5. Date, place, and debtor's signature

**You must store the signed mandate** for at least **14 months after the last collection** (to cover the 13-month unauthorized refund window). The bank does NOT store it for you in the Core scheme. Paper or electronic storage is acceptable.

The EPC provides official mandate templates and translations:
- [Guidelines for the appearance of mandates](https://www.europeanpaymentscouncil.eu/index.cfm/knowledge-bank/epc-documents/guidelines-for-the-appearance-of-mandates-in-the-sepa-direct-debit-core-scheme/)
- [Core SDD mandate text translations (all SEPA languages)](https://www.europeanpaymentscouncil.eu/other/core-sdd-mandate-translations)

### Visual summary

```
Creditor Identifier (CdtrSchmeId)
  e.g., NL53ZZZ091737840000
  └── Obtained from DNB via Rabobank (you likely already have one)
  └── One per organization
  └── Identifies WHO is collecting

Mandate Reference (MndtId)
  e.g., MBR-00042-01  (max 35 chars)
  └── Generated by YOU
  └── One per signed mandate (per member)
  └── Identifies WHICH authorization

Together: CdtrSchmeId + MndtId = globally unique mandate
```

---

## Approach 1 (Recommended): Rabobank Business Direct Debit API

**Full automation** — your Laravel app submits SEPA Direct Debit batches programmatically via Rabobank's REST API, no manual file uploads needed.

### How it works

1. Your app generates a SEPA Direct Debit batch (internally using the `digitick/sepa-xml` library for structure/validation)
2. Submits it via the **Rabobank BDD API** (`POST /business-direct-debit/batches`)
3. Polls the **Batch Transaction Details (BTD) API** for results (rejections, settlements)
4. Optionally receives real-time webhooks via the **Account Notification Service (ANS)**

### What you need

| Requirement           | Details                                                                                                     |
| --------------------- | ----------------------------------------------------------------------------------------------------------- |
| **Rabo Banking Link** | An integration layer you activate on your Rabobank business account. Contact Rabobank to enable it.         |
| **OAuth2 + mTLS**     | The API uses OAuth2 with Mutual TLS certificates. You'll need to set up certificate-based authentication.   |
| **SEPA Creditor ID**  | You likely already have this (e.g. `NL32ZZZ123456780000`). It's required in every pain.008 message.         |
| **Sandbox access**    | Sign up at [developer.rabobank.com/signup](https://developer.rabobank.com/signup) to test before going live. |

### Libraries

- **`digitick/sepa-xml`** ([GitHub](https://github.com/php-sepa-xml/php-sepa-xml), [Packagist](https://packagist.org/packages/digitick/sepa-xml)) — v3.1.0, 6.38M+ installs, actively maintained. Explicitly confirms Rabobank compatibility with `pain.008.001.02`. Use it to generate the XML payload, then submit via the API.
- **Rabobank OpenAPI spec** — Available at [docs.developer.rabobank.com/payments/openapi/business-direct-debit.json](https://docs.developer.rabobank.com/payments/openapi/business-direct-debit.json). You can generate a PHP client from this using [openapi-generator](https://openapi-generator.tech/) or use Laravel's HTTP client directly.

### Automation level

| Step                        | Automated?                                                        |
| --------------------------- | ----------------------------------------------------------------- |
| Generate invoices (batch)   | ✅ Already built (`CreateInvoiceBatchCommand`)                    |
| Collect member banking data | ✅ Already built (`PaymentInformation` model + registration flow) |
| Generate SEPA XML           | ✅ New: service class wrapping `digitick/sepa-xml`                |
| Submit to Rabobank          | ✅ New: BDD API client                                            |
| Pre-notification to members | ✅ New: email notification before collection date                 |
| Track payment status        | ✅ New: poll BTD API or receive ANS webhooks                      |
| Reconcile payments          | ✅ New: match settlements against invoices                        |
| Handle rejections/refunds   | ✅ New: process return codes, update invoice status               |

---

## Approach 2 (Simpler): Generate XML + Manual Upload

**Semi-automated** — your Laravel app generates a valid `pain.008` XML file, which you download and upload manually through the Rabobank Online Banking web portal.

### How it works

1. Your app generates the SEPA XML file using `digitick/sepa-xml`
2. You download the file from the Filament admin panel
3. You upload it to Rabobank via their web banking interface
4. You manually check for rejections in Rabobank Online and update invoice statuses

### Automation level

Same as Approach 1 for steps 1–5, but **submission, status tracking, and reconciliation are manual**. This is a good starting point if you want to ship faster and add API integration later.

---

## Handling Failed, Rejected & Refunded SEPA Requests

This is critical regardless of which approach you choose.

### SEPA Rejection Reason Codes

| Code         | Meaning                        | Action                                                              |
| ------------ | ------------------------------ | ------------------------------------------------------------------- |
| **AC01**     | Incorrect IBAN                 | Contact member, update `PaymentInformation`, retry                  |
| **AC04**     | Account closed                 | Contact member for new banking details                              |
| **AM04**     | Insufficient funds             | Retry after a few days (configurable retry policy)                  |
| **MD01**     | Invalid mandate reference      | Verify mandate data, correct and re-submit                          |
| **MD02**     | Missing mandate info           | Ensure mandate ID + signature date are correct                      |
| **MD06**     | Debtor refund (within 8 weeks) | Member exercised "no questions asked" refund right — contact member |
| **MD07**     | Debtor deceased                | Cancel mandate, write off or contact estate                         |
| **MS03**     | Technical/format error         | Fix XML generation issue                                            |
| **RC01/RC02**| Bank/account restricted        | Contact member's bank or member directly                            |

### Refund timelines

- **Authorized debit**: Debtor can reverse within **8 weeks** — no questions asked
- **Unauthorized debit**: Debtor can reverse within **13 months**
- This means an invoice should not be considered "fully paid" until the 8-week window has passed

### Recommended `InvoiceStatus` expansion

Your current enum has `Open`, `Pending`, `Paid`. You'll need additional states:

| New Status               | When                                                       |
| ------------------------ | ---------------------------------------------------------- |
| `DirectDebitInitiated`   | SEPA batch created, not yet submitted                      |
| `Submitted`              | Sent to Rabobank (via API or file upload)                  |
| `Rejected`               | Bank rejected (with reason code stored separately)         |
| `Returned`               | Debtor's bank returned the debit (e.g. insufficient funds) |
| `Refunded`               | Debtor exercised refund right (MD06)                       |

### Sequence type tracking

Your `PaymentInformation` model's mandate reference identifies the mandate. You'll need to track whether each collection is:

| Type                 | When to use                                            |
| -------------------- | ------------------------------------------------------ |
| **FRST** (First)     | First collection on a new mandate                      |
| **RCUR** (Recurring) | Subsequent collections (most common for your use case) |
| **OOFF** (One-Off)   | Single collection, no recurring relationship           |
| **FNAL** (Final)     | Last collection (member leaving)                       |

You could add a `last_sequence_type` and `last_collection_date` to `PaymentInformation` to track this.

---

## Architecture Recommendation

Given your existing domain-driven architecture (`app/Domain/`), here's how I'd structure the new SEPA module:

```
app/Domain/Sepa/
├── SepaBatchGenerator.php          # Interface: generate pain.008 for a set of invoices
├── SepaBatchGeneratorImpl.php      # Uses digitick/sepa-xml
├── SepaSubmissionService.php       # Interface: submit batch to bank
├── SepaStatusTracker.php           # Interface: poll/webhook for status updates
├── RejectionHandler.php            # Process rejection codes, update invoices
├── PreNotificationService.php      # Send pre-notification emails to members
├── MandateSequenceResolver.php     # Determine FRST/RCUR/FNAL per member
└── ValueObjects/
    ├── CreditorIdentifier.php      # Your SEPA Creditor ID
    ├── Mandate.php                 # Mandate reference + signature date
    └── RejectionReason.php         # Enum of rejection codes

app/Infrastructure/Sepa/
├── RabobankBddClient.php           # HTTP client for Rabobank BDD API
├── RabobankBtdClient.php           # HTTP client for batch status polling
└── SepaBatchGeneratorDb.php        # Eloquent adapter for SEPA batch persistence
```

### Pre-notification requirement

SEPA rules require you to **notify the debtor at least 14 calendar days before the collection date** (unless you've agreed to a shorter period in your membership terms). This notification must include:

- The amount to be debited
- The collection date
- Your SEPA Creditor ID
- The mandate reference

This can be an automated email sent when you create the SEPA batch.

---

## Recommendation

Start with **Approach 2** (generate XML + manual upload) to ship quickly and validate the SEPA XML generation logic. Then graduate to **Approach 1** (Rabobank BDD API) once you've confirmed the flow works end-to-end. The `digitick/sepa-xml` library is used in both approaches, so no work is wasted. Contact Rabobank now to request sandbox access for the BDD API — the onboarding process (OAuth2 + mTLS setup) can take a few weeks.

---

## Sources

| Source                                  | URL                                                                                                                                                                                  |
| --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Rabobank Developer Portal               | [developer.rabobank.nl](https://developer.rabobank.nl/)                                                                                                                              |
| Rabobank BDD API Reference              | [docs.developer.rabobank.com/payments/reference/bdd](https://docs.developer.rabobank.com/payments/reference/bdd)                                                                     |
| Rabobank BDD OpenAPI Spec               | [business-direct-debit.json](https://docs.developer.rabobank.com/payments/openapi/business-direct-debit.json)                                                                        |
| Rabobank Payments Overview              | [payment-apis-overview](https://developer.rabobank.nl/payments/docs/payment-apis-overview)                                                                                           |
| digitick/sepa-xml (GitHub)              | [php-sepa-xml/php-sepa-xml](https://github.com/php-sepa-xml/php-sepa-xml)                                                                                                            |
| digitick/sepa-xml (Packagist)           | [packagist.org/packages/digitick/sepa-xml](https://packagist.org/packages/digitick/sepa-xml)                                                                                         |
| SEPA Direct Debit docs (library)        | [direct_debit.md](https://github.com/php-sepa-xml/php-sepa-xml/blob/master/doc/direct_debit.md)                                                                                      |
| EPC SEPA Core Direct Debit Rulebook     | [europeanpaymentscouncil.eu](https://www.europeanpaymentscouncil.eu/what-we-do/sepa-payments/sepa-core-direct-debit)                                                                 |
| EPC SDD Core Rulebook & Implementation  | [europeanpaymentscouncil.eu](https://www.europeanpaymentscouncil.eu/what-we-do/epc-payment-schemes/sepa-direct-debit/sepa-direct-debit-core-rulebook-and-implementation)              |
| EPC Mandate Appearance Guidelines       | [europeanpaymentscouncil.eu](https://www.europeanpaymentscouncil.eu/index.cfm/knowledge-bank/epc-documents/guidelines-for-the-appearance-of-mandates-in-the-sepa-direct-debit-core-scheme/) |
| EPC Core SDD Mandate Translations       | [europeanpaymentscouncil.eu](https://www.europeanpaymentscouncil.eu/other/core-sdd-mandate-translations)                                                                              |
| EPC SDD Mandate Overview                | [europeanpaymentscouncil.eu](https://www.europeanpaymentscouncil.eu/what-we-do/epc-payment-schemes/sepa-direct-debit/sdd-mandate)                                                     |
| SEPA Reason Codes                       | [EPC Guidance Documents](https://www.europeanpaymentscouncil.eu/document-library/guidance-documents/sepa-requirements-explanatory-document)                                           |
| ISO 20022 Message Archive               | [iso20022.org](https://www.iso20022.org/catalogue-messages/iso-20022-messages-archive)                                                                                               |
