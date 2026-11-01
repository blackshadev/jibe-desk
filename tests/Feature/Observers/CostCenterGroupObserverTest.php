<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Models\CostCenterGroup;
use Tests\FeatureTestCase;

final class CostCenterGroupObserverTest extends FeatureTestCase
{
    public function test_renumbering_a_parent_rewrites_the_qualified_numbers_of_its_descendants(): void
    {
        $parent = CostCenterGroup::factory()->create(['number' => '20', 'external_number' => '20']);
        $child = CostCenterGroup::factory()->create([
            'number' => '20.21',
            'external_number' => '21',
            'parent_id' => $parent->id,
        ]);
        $grandchild = CostCenterGroup::factory()->create([
            'number' => '20.21.5',
            'external_number' => '5',
            'parent_id' => $child->id,
        ]);

        $parent->number = '25';
        $parent->save();

        static::assertSame('25.21', $child->refresh()->number);
        static::assertSame('25.21.5', $grandchild->refresh()->number);
    }
}
