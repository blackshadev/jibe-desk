<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\CostCenter;
use App\Models\CostCenterGroup;
use Illuminate\Database\QueryException;
use Tests\FeatureTestCase;

final class CostCenterGroupTest extends FeatureTestCase
{
    public function test_a_group_with_children_cannot_be_deleted(): void
    {
        $parent = CostCenterGroup::factory()->create(['number' => '20']);
        CostCenterGroup::factory()->create(['number' => '20.21', 'parent_id' => $parent->id]);

        $this->expectException(QueryException::class);

        $parent->delete();
    }

    public function test_a_group_with_cost_centers_cannot_be_deleted(): void
    {
        $group = CostCenterGroup::factory()->create(['number' => '20']);
        CostCenter::factory()->create(['cost_center_group_id' => $group->id]);

        $this->expectException(QueryException::class);

        $group->delete();
    }

    public function test_self_and_descendant_ids_returns_the_whole_subtree(): void
    {
        $parent = CostCenterGroup::factory()->create(['number' => '20']);
        $child = CostCenterGroup::factory()->create(['number' => '20.21', 'parent_id' => $parent->id]);
        $grandchild = CostCenterGroup::factory()->create([
            'number' => '20.21.5',
            'parent_id' => $child->id,
        ]);
        CostCenterGroup::factory()->create(['number' => '30']);

        static::assertEqualsCanonicalizing(
            [$parent->id, $child->id, $grandchild->id],
            $parent->selfAndDescendantIds(),
        );
    }
}
