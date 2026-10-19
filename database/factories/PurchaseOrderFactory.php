<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderApprovalStatus;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Models\Member;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<PurchaseOrder> */
final class PurchaseOrderFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'description' => fake()->sentence(),
            'creditor_name' => fake()->company(),
            'creditor_iban' => fake()->iban(),
            'date' => fake()->dateTimeBetween('-2 years', 'now'),
            'status' => fake()->randomElement(PurchaseOrderStatus::cases()),
            'approval_status' => PurchaseOrderApprovalStatus::Pending,
        ];
    }

    public function open(): self
    {
        return $this->state([
            'status' => PurchaseOrderStatus::Open,
            'approval_status' => PurchaseOrderApprovalStatus::Pending,
        ]);
    }

    public function accepted(): self
    {
        return $this->state([
            'status' => PurchaseOrderStatus::Open,
            'approval_status' => PurchaseOrderApprovalStatus::Accepted,
        ]);
    }

    public function pending(): self
    {
        return $this->state([
            'status' => PurchaseOrderStatus::Pending,
            'approval_status' => PurchaseOrderApprovalStatus::Accepted,
        ]);
    }

    public function paid(): self
    {
        return $this->state([
            'status' => PurchaseOrderStatus::Paid,
            'approval_status' => PurchaseOrderApprovalStatus::Accepted,
        ]);
    }

    public function declined(?string $reason = null): self
    {
        return $this->state([
            'status' => PurchaseOrderStatus::Declined,
            'approval_status' => PurchaseOrderApprovalStatus::Accepted,
            'declined_reason' => $reason ?? fake()->sentence(),
        ]);
    }

    public function cancelled(?string $reason = null): self
    {
        return $this->state([
            'status' => PurchaseOrderStatus::Cancelled,
            'approval_status' => PurchaseOrderApprovalStatus::Declined,
            'declined_reason' => $reason ?? fake()->sentence(),
        ]);
    }

    public function withoutCreditor(): self
    {
        return $this->state(['creditor_name' => null, 'creditor_iban' => null]);
    }

    public function forMember(Member $member): self
    {
        return $this->state(['member_id' => $member->id]);
    }

    public function withLines(?int $count = null): self
    {
        $count ??= fake()->numberBetween(1, 5);

        return $this->has(PurchaseOrderLine::factory()->count($count), 'lines');
    }
}
