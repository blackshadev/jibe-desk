<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Activities;

use App\Domain\Activities\ActivityId;
use App\Infrastructure\Activities\ActivityDbRepository;
use App\Models\Activity;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\FeatureTestCase;

final class ActivityDbRepositoryTest extends FeatureTestCase
{
    public function test_get_by_id_returns_activity_domain_object(): void
    {
        /** @var Activity $model */
        $model = Activity::factory()->create();

        $repo = new ActivityDbRepository();

        $activity = $repo->getById(ActivityId::create($model->id));

        self::assertSame($model->id, $activity->id->value);
        self::assertSame($model->billable_item_id, $activity->billableItemId->value);
        self::assertSame($model->start_date->toDateString(), $activity->startDate->format('Y-m-d'));
        self::assertSame($model->end_date?->toDateString(), $activity->endDate?->format('Y-m-d'));
    }

    public function test_get_by_id_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $repo = new ActivityDbRepository();

        $repo->getById(ActivityId::create(999999));
    }
}
