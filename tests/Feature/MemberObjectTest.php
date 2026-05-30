<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberObject;
use App\Models\MemberObjectType;
use Tests\FeatureTestCase;

final class MemberObjectTest extends FeatureTestCase
{
    public function test_member_objects_relation_and_type(): void
    {
        $type = MemberObjectType::factory()->create(['name' => 'anders']);
        $member = Member::withoutEvents(static fn () => Member::factory()->create());

        $object = MemberObject::factory()->create([
            'member_id' => $member->id,
            'object_type_id' => $type->id,
            'name' => 'Test Object',
        ]);

        $this->assertTrue($member->memberObjects()->where('id', $object->id)->exists());
        $this->assertEquals('anders', $object->memberObjectType->name);
    }
}
