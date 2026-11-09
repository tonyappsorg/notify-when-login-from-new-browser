<?php
declare(strict_types=1);

namespace Tonyapps\NotifyOnNewBrowserLogin\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class NotificationEmailTest extends TestCase
{
    public function testEmailShowsTheSignInDetailsAndTheChangePasswordButton(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../../../view/adminhtml/email/new_browser_login_notification.html');

        foreach (['username', 'channel', 'ip', 'time', 'accountUrl', 'adminHost'] as $var) {
            self::assertStringContainsString('{{var ' . $var . '}}', $html);
        }
        self::assertStringContainsString('browser=$browser platform=$platform', $html);
        self::assertStringContainsString('<a href="{{var accountUrl}}"', $html);
        // Sent in the adminhtml area, where the storefront header/footer templates don't exist.
        self::assertStringNotContainsString('design/email/header_template', $html);
    }
}
