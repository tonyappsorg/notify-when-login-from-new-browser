<?php
namespace Tonyapps\NotifyOnNewBrowserLogin\Test\Unit\Model\Observer;

use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Event\Observer;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\User\Model\User;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tonyapps\NotifyOnNewBrowserLogin\Model\Browser;
use Tonyapps\NotifyOnNewBrowserLogin\Model\BrowserFactory;
use Tonyapps\NotifyOnNewBrowserLogin\Model\Observer\StoreLoginDataInformation;
use Tonyapps\NotifyOnNewBrowserLogin\Model\ResourceModel\Browser\Collection;
use Tonyapps\NotifyOnNewBrowserLogin\Model\ResourceModel\BrowserRepository;

class StoreLoginDataInformationTest extends TestCase
{
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private function run_(int $knownCount, bool $expectNotify, bool $mailFails = false): void
    {
        $browser = $this->getMockBuilder(Browser::class)->disableOriginalConstructor()
            ->onlyMethods(['setUserId', 'setIp', 'setBrowser', 'setPlatform', 'getBrowser'])->getMock();
        $browser->expects($this->once())->method('setBrowser')->with('Chrome')->willReturnSelf();
        $browser->expects($this->once())->method('setPlatform')->with('Apple')->willReturnSelf();
        $browser->method('getBrowser')->willReturn('Chrome');

        $factory = $this->createMock(BrowserFactory::class);
        $factory->method('create')->willReturn($browser);

        $collection = $this->createMock(Collection::class);
        $collection->method('getSize')->willReturn($knownCount);
        $repo = $this->createMock(BrowserRepository::class);
        $repo->method('find')->willReturn($collection);
        $repo->expects($expectNotify ? $this->once() : $this->never())->method('save')->with($browser);

        $remoteAddress = $this->createMock(RemoteAddress::class);
        $remoteAddress->method('getRemoteAddress')->willReturn(false); // no IP must not crash

        $transport = $this->createMock(TransportInterface::class);
        $send = $transport->expects($expectNotify ? $this->once() : $this->never())->method('sendMessage');
        if ($mailFails) {
            $send->willThrowException(new \RuntimeException('smtp down'));
        }
        $tb = $this->createMock(TransportBuilder::class);
        foreach (['setTemplateIdentifier', 'setTemplateOptions', 'setFrom', 'setTemplateVars', 'addTo'] as $m) {
            $tb->method($m)->willReturnSelf();
        }
        $tb->method('getTransport')->willReturn($transport);

        $user = $this->getMockBuilder(User::class)->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getEmail', 'getName'])->getMock();
        $user->method('getId')->willReturn(1);
        $user->method('getEmail')->willReturn('a@b.c');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($mailFails ? $this->once() : $this->never())->method('error');

        (new StoreLoginDataInformation(new \Browser(self::UA), $factory, $repo, $remoteAddress, $tb, $logger))->execute(new Observer(['user' => $user]));
    }

    public function testNewBrowserIsSavedAndNotified(): void
    {
        $this->run_(0, true);
    }

    public function testKnownBrowserIsIgnored(): void
    {
        $this->run_(1, false);
    }

    public function testMailFailureIsLoggedNotThrown(): void
    {
        $this->run_(0, true, true);
    }
}
