<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Mail\BaseMail;
use App\Domain\Mail\MailSender;
use Illuminate\Support\Facades\Mail;
use Override;

final readonly class LaravelMailSender implements MailSender
{
    #[Override]
    public function send(BaseMail $mail): void
    {
        $recipient = $mail->to();
        $mailable = new MailMailable($mail);

        Mail::to($recipient->email, $recipient->name)
            ->send($mailable);
    }
}
