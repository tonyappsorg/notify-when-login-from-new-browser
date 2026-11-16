<?php
declare(strict_types=1);

namespace Tonyapps\NotifyOnNewBrowserLogin\Test\Unit\Model;

use Magento\Backend\Model\Session\AdminConfig;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tonyapps\NotifyOnNewBrowserLogin\Model\EmailPreview;
use Tonyapps\NotifyOnNewBrowserLogin\Model\Observer\StoreLoginDataInformation;
use Tonyapps\NotifyOnNewBrowserLogin\Model\ResourceModel\Browser as BrowserResource;

class EmailPreviewTest extends TestCase
{
    public function testPreviewHasTheSendersVariablesForTheRecordedDeviceAndChangesNothing(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchRow')->willReturn([
            'entity_id' => '12', 'user_id' => '7', 'browser' => 'Chrome', 'platform' => 'Windows',
            'ip' => '10.0.0.1', 'created_at' => '2026-11-10 08:30:00',
        ]);
        foreach (['insert', 'update', 'delete'] as $write) {
            $connection->expects(self::never())->method($write);
        }
        $browsers = $this->createMock(BrowserResource::class);
        $browsers->method('getConnection')->willReturn($connection);
        $browsers->expects(self::never())->method('isKnown');
        $browsers->expects(self::never())->method('remember');

        $user = $this->getMockBuilder(User::class)->disableOriginalConstructor()
            ->onlyMethods(['load', 'getId', 'getFirstName', 'getUserName', 'save'])->getMock();
        $user->method('load')->with(7)->willReturnSelf();
        $user->method('getId')->willReturn(7);
        $user->method('getFirstName')->willReturn('Ada');
        $user->method('getUserName')->willReturn('ada');
        $user->expects(self::never())->method('save');
        $users = $this->createMock(UserFactory::class);
        $users->method('create')->willReturn($user);

        $transport = $this->createMock(TransportBuilder::class);
        $transport->expects(self::never())->method('setTemplateVars');
        $transport->expects(self::never())->method('getTransport');
        $cookies = $this->createMock(CookieManagerInterface::class);
        $cookies->expects(self::never())->method('setPublicCookie');
        $sender = $this->sender($transport, $cookies);

        $vars = (new EmailPreview($browsers, $users, $sender))
            ->getVariables('admin_security_new_browser_login_template', '12');

        // The observer's own builder, with the device's first-seen time.
        self::assertSame(
            $sender->getVariables($user, 'Chrome', 'Windows', '10.0.0.1', false, strtotime('2026-11-10 08:30:00 UTC')),
            $vars
        );
        self::assertSame('2026-11-10 08:30 UTC', $vars['time']);
        self::assertSame('Admin panel', $vars['channel']);
        self::assertSame('ada', $vars['username']);
        // Sent from the admin store, like the observer does.
        self::assertSame(0, (new EmailPreview($browsers, $users, $sender))
            ->getStoreId('admin_security_new_browser_login_template', '12'));
    }

    private function sender(TransportBuilder $transport, CookieManagerInterface $cookies): StoreLoginDataInformation
    {
        $url = $this->createMock(BackendUrl::class);
        $url->method('getBaseUrl')->willReturn('https://shop.example.com/admin/');
        $url->method('getUrl')->willReturn('https://shop/admin/account');

        return new StoreLoginDataInformation(
            new \Browser(''),
            $this->createMock(BrowserResource::class),
            $this->createMock(RemoteAddress::class),
            $cookies,
            $this->createMock(CookieMetadataFactory::class),
            $this->createMock(AdminConfig::class),
            $this->createMock(RequestInterface::class),
            $transport,
            $this->createMock(LoggerInterface::class),
            $this->createMock(State::class),
            $url,
            $this->createMock(ScopeConfigInterface::class)
        );
    }
}
