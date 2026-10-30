<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class UnicoCoreAvailableTest extends TestCase
{
    public function test_unico_core_classes_are_loadable(): void
    {
        foreach ([
            \Unico\Core\Mail\OutgoingMailer::class,
            \Unico\Core\Mail\EmailTemplateResolver::class,
            \Unico\Core\Models\EmailTemplate::class,
            \Unico\Core\Models\MailAccount::class,
        ] as $class) {
            $this->assertTrue(class_exists($class), "$class non caricabile");
        }
    }
}
