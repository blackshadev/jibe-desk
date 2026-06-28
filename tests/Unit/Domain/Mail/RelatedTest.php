<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail;

use App\Domain\Mail\Related;
use App\Models\Invoice;
use App\Models\Member;
use Tests\UnitTestCase;

final class RelatedTest extends UnitTestCase
{
    public function test_it_stores_class_and_id(): void
    {
        $related = new Related(Member::class, 42);

        static::assertSame(Member::class, $related->class);
        static::assertSame(42, $related->id);
    }

    public function test_it_works_with_any_eloquent_model_class(): void
    {
        $related = new Related(Invoice::class, 123);

        static::assertSame(Invoice::class, $related->class);
        static::assertSame(123, $related->id);
    }
}
