<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;

interface Job extends ShouldQueue {}
