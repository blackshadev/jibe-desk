<?php

declare(strict_types=1);

use App\Mail\MailbookMail;
use Xammie\Mailbook\Facades\Mailbook;

Mailbook::add(MailbookMail::class);
