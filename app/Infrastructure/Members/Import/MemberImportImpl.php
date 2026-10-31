<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Billing\BillableItems\BillPeriod;
use App\Domain\Billing\Subscriptions\BillingItemApplicators\ApplyMembershipBilling;
use App\Domain\Billing\Subscriptions\BillingItemApplicators\ApplyMemberVolunteerBilling;
use App\Domain\Billing\Subscriptions\BillingItemApplicators\ApplySameHouseholdBilling;
use App\Domain\Members\Import\MemberImport;
use App\Domain\Members\Import\MemberImportException;
use App\Domain\Members\Import\MemberImportReport;
use App\Domain\Members\Import\MemberImportRequest;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use App\Domain\Members\PaymentMethod;
use App\Models\BillableItem;
use App\Models\BillableItemInstance;
use App\Models\CostCenter;
use App\Models\Member;
use App\Models\Membership;
use App\Models\PaymentInformation;
use Carbon\CarbonImmutable;
use Database\Seeders\CostCenterNumber;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Override;
use Throwable;

final readonly class MemberImportImpl implements MemberImport
{
    private const string ERELID_MEMBERSHIP_NAME = 'Erelid';

    private const int MINIMUM_INSTRUCTOR_AGE = 16;

    public function __construct(
        private MemberSpreadsheetReader $reader,
        private SpreadsheetRowMapper $rowMapper,
        private HouseholdCreator $householdCreator,
        private MemberImportReportBuilder $reportBuilder,
        private ApplyMembershipBilling $applyMembershipBilling,
        private ApplyMemberVolunteerBilling $applyMemberVolunteerBilling,
        private ApplySameHouseholdBilling $applySameHouseholdBilling,
    ) {}

    #[Override]
    public function import(MemberImportRequest $request): MemberImportReport
    {
        if (Member::query()->exists()) {
            throw MemberImportException::membersTableNotEmpty();
        }

        $spreadsheetRows = $this->reader->read($request->path);
        $report = null;

        DB::beginTransaction();

        try {
            $report = $this->write($spreadsheetRows, $request);

            if ($request->dryRun) {
                DB::rollBack();

                return $report;
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        return $report;
    }

    /** @param list<SpreadsheetImportRow> $spreadsheetRows */
    private function write(array $spreadsheetRows, MemberImportRequest $request): MemberImportReport
    {
        $erelidMembershipCreated = $this->ensureErelidMembership();
        $membershipIds = $this->membershipIds();
        $createdMembers = $this->createMembers($spreadsheetRows, $membershipIds);

        $continuingMembers = array_values(
            array_filter($createdMembers, static fn (CreatedMember $member): bool => !$member->row->isLeaver()),
        );

        $householdsCreated = $this->householdCreator->create($continuingMembers);

        foreach ($continuingMembers as $createdMember) {
            $this->createMandate($createdMember, $request->lastCollected);
            $this->applyBilling($createdMember, $request->billingStart);
        }

        return $this->reportBuilder->build(
            allMembers: $createdMembers,
            continuingMembers: $continuingMembers,
            householdsCreated: $householdsCreated,
            billingInstancesCreated: $this->countBillingInstances($continuingMembers),
            erelidMembershipCreated: $erelidMembershipCreated,
        );
    }

    /**
     * @param list<SpreadsheetImportRow> $spreadsheetRows
     * @param array<string, int> $membershipIds
     * @return list<CreatedMember>
     */
    private function createMembers(array $spreadsheetRows, array $membershipIds): array
    {
        $createdMembers = [];

        foreach ($spreadsheetRows as $spreadsheetRow) {
            $row = $this->rowMapper->map($spreadsheetRow->values, $spreadsheetRow->number);
            $this->assertSailInstructorIsAdult($row, $spreadsheetRow->number);

            $membershipId = $membershipIds[$row->membershipName]
                ?? throw MemberImportException::missingMembership($row->membershipName);

            $member = $this->createMember($row, $membershipId);
            $createdMembers[] = new CreatedMember(
                MemberId::create($member->id),
                MembershipId::create($membershipId),
                $row,
            );
        }

        return $createdMembers;
    }

    private function createMember(MappedMemberRow $row, int $membershipId): Member
    {
        return Member::query()->createQuietly([
            'first_name' => $row->firstName,
            'infix_name' => $row->infixName,
            'last_name' => $row->lastName,
            'email' => $row->email,
            'gender' => $row->gender,
            'birthdate' => $row->birthdate,
            'address_street' => $row->addressStreet,
            'address_housenumber' => $row->addressHousenumber,
            'address_housenumber_addition' => $row->addressHousenumberAddition,
            'address_postalcode' => $row->addressPostalcode,
            'address_city' => $row->addressCity,
            'membership_id' => $membershipId,
            'payment_method' => PaymentMethod::DirectDebit,
            'is_volunteer' => $row->isVolunteer,
            'accepted_at' => $row->startDate,
            'stopped_at' => $row->stopDate,
        ]);
    }

    private function assertSailInstructorIsAdult(MappedMemberRow $row, int $rowNumber): void
    {
        if (!$row->isSailInstructor()) {
            return;
        }

        $ageAtMembershipStart = CarbonImmutable::instance($row->birthdate)->diffInYears($row->startDate);

        if ($ageAtMembershipStart < self::MINIMUM_INSTRUCTOR_AGE) {
            throw MemberImportException::instructorTooYoung($rowNumber, $row->birthdate->format('Y-m-d'));
        }
    }

    /** @return array<string, int> */
    private function membershipIds(): array
    {
        $membershipIds = [];

        foreach (Membership::query()->pluck('id', 'name') as $name => $id) {
            $membershipIds[(string) $name] = (int) $id;
        }

        return $membershipIds;
    }

    private function ensureErelidMembership(): bool
    {
        if (Membership::query()->where('name', self::ERELID_MEMBERSHIP_NAME)->exists()) {
            return false;
        }

        $costCenter = CostCenter::query()
            ->where('number', CostCenterNumber::Contribution)
            ->firstOrFail();

        $adultBillableItem = BillableItem::create([
            'description' => 'Lidmaatschap Erelid (volwassenen)',
            'price' => 0,
            'vat' => 0,
            'bill_period' => BillPeriod::Annually,
            'cost_center_id' => $costCenter->id,
        ]);
        $kidsBillableItem = BillableItem::create([
            'description' => 'Lidmaatschap Erelid (jeugd)',
            'price' => 0,
            'vat' => 0,
            'bill_period' => BillPeriod::Annually,
            'cost_center_id' => $costCenter->id,
        ]);

        Membership::create([
            'name' => self::ERELID_MEMBERSHIP_NAME,
            'adult_billable_item_id' => $adultBillableItem->id,
            'kids_billable_item_id' => $kidsBillableItem->id,
            'is_default' => false,
        ]);

        return true;
    }

    private function createMandate(CreatedMember $member, DateTimeInterface $lastCollected): void
    {
        PaymentInformation::query()->create([
            'member_id' => $member->memberId->value,
            'banking_account_number' => $member->row->iban,
            'banking_bic' => $member->row->bic,
            'banking_account_holder_name' => $member->row->accountHolderName,
            'mandate_accepted_date' => $member->row->startDate,
            'revoked_at' => null,
            'last_collected_at' => $lastCollected,
        ]);
    }

    private function applyBilling(CreatedMember $member, DateTimeInterface $billingStart): void
    {
        $this->applyMembershipBilling->apply($member->memberId, $member->membershipId);
        $this->applyMemberVolunteerBilling->apply($member->memberId);
        $this->applySameHouseholdBilling->apply($member->memberId);

        BillableItemInstance::query()
            ->where('member_id', $member->memberId->value)
            ->update(['start_date' => $billingStart]);
    }

    /** @param list<CreatedMember> $continuingMembers */
    private function countBillingInstances(array $continuingMembers): int
    {
        $memberIds = array_map(static fn (CreatedMember $member): int => $member->memberId->value, $continuingMembers);

        if ($memberIds === []) {
            return 0;
        }

        return BillableItemInstance::query()->whereIn('member_id', $memberIds)->count();
    }
}
