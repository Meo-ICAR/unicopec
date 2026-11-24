<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Unico\Core\Mail\OutgoingMailer;
use Unico\Core\Models\MailAccount;

class OutgoingMailerOAuthTest extends TestCase
{
    public function test_il_trasporto_oauth_usa_xoauth2_con_utente_e_token(): void
    {
        $a = new class extends MailAccount
        {
            protected function casts(): array
            {
                return [];
            }
        };
        $a->setRawAttributes(['email_address' => 'pechub@races.it', 'smtp_host' => 'smtp.office365.com', 'smtp_port' => 587, 'access_token' => 'tok'], true);

        $t = OutgoingMailer::oauthTransport($a);

        $this->assertInstanceOf(EsmtpTransport::class, $t);
        $this->assertSame('pechub@races.it', $t->getUsername());
        $this->assertSame('tok', $t->getPassword());
    }

    public function test_solo_microsoft_oauth2_con_refresh_token_puo_inviare_con_oauth(): void
    {
        $a = new MailAccount;
        $a->setRawAttributes(['auth_type' => 'oauth2', 'provider' => 'microsoft'], true);
        $this->assertFalse($a->canSendWithOAuth());
    }
}
