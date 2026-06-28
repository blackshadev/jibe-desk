<?php

declare(strict_types=1);

namespace App\Domain\Mail;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface MailSender
{
    public function send(BaseMail $mail): void;
}
