<?php
declare(strict_types=1);

namespace Tonyapps\NotifyOnNewBrowserLogin\Test\Unit\Model\Observer;

use Magento\Backend\Model\Session\AdminConfig;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\User\Model\User;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tonyapps\NotifyOnNewBrowserLogin\Model\Observer\StoreLoginDataInformation;
use Tonyapps\NotifyOnNewBrowserLogin\Model\ResourceModel\Browser as BrowserResource;

class StoreLoginDataInformationTest extends TestCase
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /** @var array<string, string> cookies sent with the request */
    private array $cookies = [];
    private ?string $setToken = null;
    private ?string $rememberedHash = null;
    private bool $known = false;
    private int $sent = 0;
    private bool $mailFails = false;
    private int $errors = 0;
    private string $area = 'adminhtml';
    private bool $result = true;
    private array $rememberedHashes = [];

    private array $vars = [];
    private ?string $template = null;

    private function backendUrl(): BackendUrl
    {
        $url = $this->createMock(BackendUrl::class);
        $url->method('getBaseUrl')->willReturn('https://shop.example.com/admin/');
        $url->method('getUrl')->willReturnCallback(
            static fn(string $route, array $params) => $params === ['_nosecret' => true, '_nosid' => true]
                ? 'https://shop/admin/' . $route
                : 'wrong-params'
        );
        return $url;
    }

    public function testEmailNamesTheAccountTheChannelAndLinksToAccountSettings(): void
    {
        $this->login();

        self::assertSame('wk', $this->vars['username']);
        self::assertSame('Admin panel', $this->vars['channel']);
        self::assertSame('https://shop/admin/adminhtml/system_account/index', $this->vars['accountUrl']);
        self::assertSame('unknown', $this->vars['ip']);
        self::assertSame('shop.example.com', $this->vars['adminHost']);
    }

    public function testEmailUsesTheTemplateChosenInConfig(): void
    {
        $this->login();

        self::assertSame('23', $this->template);
    }

    private function login(int $userId = 7): void
    {
        $resource = $this->createMock(BrowserResource::class);
        $resource->method('isKnown')->willReturnCallback(
            fn (int $id, string $hash) => $this->known
                && ($hash === hash('sha256', $this->cookies[StoreLoginDataInformation::COOKIE_PREFIX . $id] ?? '')
                    || in_array($hash, $this->rememberedHashes, true))
        );
        $resource->method('remember')->willReturnCallback(function (int $id, string $hash, string $browser, string $platform) {
            $this->assertSame(['Chrome', 'Windows'], [$browser, $platform]);
            $this->rememberedHash = $hash;
            $this->rememberedHashes[] = $hash;
        });

        $cookieManager = $this->createMock(CookieManagerInterface::class);
        $cookieManager->method('getCookie')->willReturnCallback(fn (string $name) => $this->cookies[$name] ?? null);
        $cookieManager->method('setPublicCookie')->willReturnCallback(function (string $name, string $value, PublicCookieMetadata $meta) use ($userId) {
            $this->assertSame(StoreLoginDataInformation::COOKIE_PREFIX . $userId, $name);
            $this->assertTrue($meta->getHttpOnly());
            $this->assertSame('/admin', $meta->getPath());
            $this->setToken = $value;
        });

        $transport = $this->createMock(TransportInterface::class);
        $transport->method('sendMessage')->willReturnCallback(function () {
            if ($this->mailFails) {
                throw new \RuntimeException('smtp down');
            }
            $this->sent++;
        });
        $tb = $this->createMock(TransportBuilder::class);
        $tb->method('setTemplateIdentifier')->willReturnCallback(function (string $template) use ($tb) {
            $this->template = $template;
            return $tb;
        });
        foreach (['setTemplateOptions', 'setFromByScope', 'addTo'] as $m) {
            $tb->method($m)->willReturnSelf();
        }
        $tb->method('setTemplateVars')->willReturnCallback(function (array $vars) use ($tb) {
            $this->vars = $vars;
            return $tb;
        });
        $tb->method('getTransport')->willReturn($transport);

        $adminConfig = $this->createMock(AdminConfig::class);
        $adminConfig->method('getCookiePath')->willReturn('/admin');
        $remoteAddress = $this->createMock(RemoteAddress::class);
        $remoteAddress->method('getRemoteAddress')->willReturn(false); // no IP must not crash
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function () {
            $this->errors++;
        });

        $user = $this->getMockBuilder(User::class)->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getEmail', 'getName', 'getUserName'])->getMock();
        $user->method('getUserName')->willReturn('wk');
        $user->method('getId')->willReturn($userId);
        $user->method('getEmail')->willReturn('a@b.c');

        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willReturnCallback(fn () => $this->area);

        $metadataFactory = $this->createMock(CookieMetadataFactory::class);
        $metadataFactory->method('createPublicCookieMetadata')->willReturnCallback(fn () => new PublicCookieMetadata());

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([[StoreLoginDataInformation::XML_PATH_EMAIL_TEMPLATE, 'default', null, '23']]);

        (new StoreLoginDataInformation(
            new \Browser(self::UA),
            $resource,
            $remoteAddress,
            $cookieManager,
            $metadataFactory,
            $adminConfig,
            $this->createMock(RequestInterface::class),
            $tb,
            $logger,
            $state,
            $this->backendUrl(),
            $scopeConfig
        ))->execute(new Observer(['user' => $user, 'result' => $this->result]));
    }

    public function testFirstLoginFromADeviceSetsAHashedTokenAndNotifies(): void
    {
        $this->login();

        $this->assertSame(1, $this->sent);
        $this->assertSame(64, strlen((string)$this->setToken));
        $this->assertSame(hash('sha256', $this->setToken), $this->rememberedHash, 'only the hash is stored');
    }

    public function testDeviceWithKnownTokenIsQuiet(): void
    {
        $this->cookies[StoreLoginDataInformation::COOKIE_PREFIX . 7] = 'known-token';
        $this->known = true;

        $this->login();

        $this->assertSame(0, $this->sent);
        $this->assertNull($this->setToken);
    }

    public function testSameBrowserAndOsWithoutTheTokenIsANewDevice(): void
    {
        // What the old name+OS check missed: an attacker on another Chrome/Windows machine.
        $this->known = true;

        $this->login();

        $this->assertSame(1, $this->sent);
    }

    public function testUnknownOrForgedTokenIsANewDevice(): void
    {
        $this->cookies[StoreLoginDataInformation::COOKIE_PREFIX . 7] = 'forged';

        $this->login();

        $this->assertSame(1, $this->sent);
        $this->assertNotSame('forged', $this->setToken);
    }

    public function testMailFailureIsLoggedNotThrown(): void
    {
        $this->mailFails = true;

        $this->login();

        $this->assertSame(1, $this->errors);
    }

    public function testFailedAuthenticationIsIgnored(): void
    {
        $this->result = false;

        $this->login();

        $this->assertSame(0, $this->sent);
        $this->assertNull($this->rememberedHash);
    }

    public function testApiTokenLoginAlertsOncePerClientWithoutCookie(): void
    {
        $this->area = 'webapi_rest';
        $this->known = true;

        $this->login();
        $this->login();

        $this->assertSame(1, $this->sent, 'the same API client is a known device the second time');
        $this->assertNull($this->setToken, 'no cookie for API clients');
        $this->assertSame([hash('sha256', 'api:|' . self::UA)], $this->rememberedHashes);
    }
}
