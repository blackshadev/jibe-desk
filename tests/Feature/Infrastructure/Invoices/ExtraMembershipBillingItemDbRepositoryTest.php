<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Members\ExtraMembershipItemCode;
use App\Infrastructure\Invoices\Billing\ExtraMembershipBillingItemDbRepository;
use App\Models\BillableItem;
use App\Models\ExtraMembershipItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\FeatureTestCase;

final class ExtraMembershipBillingItemDbRepositoryTest extends FeatureTestCase
{
    public function test_it_returns_billable_item_id_for_code(): void
    {
        $billableItem = BillableItem::factory()->create();
        ExtraMembershipItem::query()->create([
            'billable_item_id' => $billableItem->id,
            'code' => ExtraMembershipItemCode::VolunteerContribution,
        ]);

        $subject = new ExtraMembershipBillingItemDbRepository();

        $result = $subject->getByCode(ExtraMembershipItemCode::VolunteerContribution);

        static::assertSame($billableItem->id, $result->value);
    }

    public function test_it_throws_when_code_is_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        new ExtraMembershipBillingItemDbRepository()->getByCode(ExtraMembershipItemCode::VolunteerContribution);
    }
}
