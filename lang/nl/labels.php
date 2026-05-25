<?php

declare(strict_types=1);

use App\Domain\Invoices\Billing\BillPeriod;
use App\Domain\Members\Gender;

return [
    'members' => 'Leden',
    'member' => 'Lid',
    'name' => 'Naam',
    'code' => 'Code',
    'first_name' => 'Voornaam',
    'infix_name' => 'Tussenvoegsel',
    'last_name' => 'Achternaam',
    'gender' => 'Geslacht',
    'birthdate' => 'Geboortedatum',
    'genders' => [
        Gender::Male->value => 'Man',
        Gender::Female->value => 'Vrouw',
        Gender::NonBinary->value => 'Non-binair',
        Gender::Undetermined->value => 'Niet bepaald',
        Gender::Other->value => 'Anders',
    ],
    'membership' => 'Lidmaatschap',
    'memberships' => 'Lidmaatschappen',
    'created_at' => 'Aangemaakt op',
    'updated_at' => 'Bijgewerkt op',
    'deleted_at' => 'Verwijderd op',
    'invoice_number' => 'Factuurnummer',
    'total' => 'Totaal',
    'invoice' => 'Factuur',
    'invoices' => 'Facturen',
    'recipient_name' => 'Tennaamstelling',
    'recipient_address' => 'Factuuradres',
    'invoice_lines' => 'Factuurregels',
    'invoice_date' => 'Factuurdatum',
    'description' => 'Omschrijving',
    'quantity' => 'Aantal',
    'price' => 'Prijs',
    'personal_information' => 'Persoonlijke informatie',
    'invoice_information' => 'Factuur informatie',
    'navigation_groups' => [
        'member_administration' => 'Ledenbeheer',
        'invoicing' => 'Facturatie',
    ],
    'members_count' => 'Aantal leden',
    'billing' => 'Facturering',
    'billable_item_instances' => 'Terugkerende factuurregels',
    'bill_period' => 'Factuurperiode',
    'bill_periods' => [
        BillPeriod::Monthly->value => 'Maandelijks',
        BillPeriod::Quarterly->value => 'Kwartalijks',
        BillPeriod::Annually->value => 'Jaarlijks',
    ],
    'start_date' => 'Startdatum',
    'end_date' => 'Einddatum',
    'extra_membership_item' => 'Extra lidmaatschap toevoeging',
    'extra_membership_items' => 'Extra lidmaatschap toevoegingen',
    'is_volunteer' => 'Is vrijwilliger',
    'membership_information' => 'Lidmaatschap informatie',
];
