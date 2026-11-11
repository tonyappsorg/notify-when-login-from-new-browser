<?php
declare(strict_types=1);

namespace Tonyapps\NotifyOnNewBrowserLogin\Model\Observer;

use Magento\Backend\Model\Session\AdminConfig;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\User\Model\User;
use Psr\Log\LoggerInterface;
use Tonyapps\NotifyOnNewBrowserLogin\Model\ResourceModel\Browser as BrowserResource;

/**
 * Emails an admin when they sign in from a device they haven't used before.
 *
 * A device is a random token in a long-lived cookie, stored hashed per user. Browser name and OS alone
 * identified "Chrome on Windows", so a stolen password used from any such machine went unnoticed.
 *
 * Listens to admin_user_authenticate_after (global), so admin token requests over REST/SOAP/GraphQL alert too.
 * API clients keep no cookie: there a device is the client's IP and user agent.
 */
class StoreLoginDataInformation implements ObserverInterface
{
    /** Stores › Configuration › Advanced › Admin › Security › New Device Sign-in Email. */
    public const XML_PATH_EMAIL_TEMPLATE = 'admin/security/new_browser_login_template';
    // Per user, so two admins sharing a browser don't overwrite each other's token.
    public const COOKIE_PREFIX = 'tonyapps_admin_device_';
    private const COOKIE_LIFETIME = 2 * 365 * 86400;

    public function __construct(
        private readonly \Browser $userAgent,
        private readonly BrowserResource $browserResource,
        private readonly RemoteAddress $remoteAddress,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly AdminConfig $adminConfig,
        private readonly RequestInterface $request,
        private readonly TransportBuilder $transportBuilder,
        private readonly LoggerInterface $logger,
        private readonly State $appState,
        private readonly BackendUrl $backendUrl,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var User $user */
        $user = $observer->getUser();
        if (!$observer->getResult() || !$user) {
            return;
        }
        try {
            $userId = (int)$user->getId();
            $cookie = self::COOKIE_PREFIX . $userId;
            $ip = (string)$this->remoteAddress->getRemoteAddress();
            $api = $this->isApiRequest();
            $token = $api
                ? 'api:' . $ip . '|' . $this->userAgent->getUserAgent()
                : (string)$this->cookieManager->getCookie($cookie);
            if ($token !== '' && $this->browserResource->isKnown($userId, hash('sha256', $token), $ip)) {
                return;
            }

            if (!$api) {
                $token = bin2hex(random_bytes(32));
            }
            $browser = (string)$this->userAgent->getBrowser();
            $platform = (string)$this->userAgent->getPlatform();
            $this->browserResource->remember($userId, hash('sha256', $token), $browser, $platform, $ip);
            if (!$api) {
                $this->cookieManager->setPublicCookie(
                    $cookie,
                    $token,
                    $this->cookieMetadataFactory->createPublicCookieMetadata()
                        ->setDuration(self::COOKIE_LIFETIME)
                        ->setPath($this->adminConfig->getCookiePath())
                        ->setHttpOnly(true)
                        ->setSecure($this->request->isSecure())
                        ->setSameSite('Lax')
                );
            }
            $this->notify($user, $browser, $platform, $ip, $api);
        } catch (\Throwable $e) {
            // Never let this block an admin login.
            $this->logger->error('New browser login check failed: ' . $e->getMessage());
        }
    }

    private function isApiRequest(): bool
    {
        try {
            return in_array(
                $this->appState->getAreaCode(),
                [Area::AREA_WEBAPI_REST, Area::AREA_WEBAPI_SOAP, Area::AREA_GRAPHQL],
                true
            );
        } catch (\Throwable) {
            return false;
        }
    }

    private function notify(User $admin, string $browser, string $platform, string $ip, bool $api): void
    {
        $this->transportBuilder
            ->setTemplateIdentifier((string)$this->scopeConfig->getValue(self::XML_PATH_EMAIL_TEMPLATE))
            ->setTemplateOptions(['area' => 'adminhtml', 'store' => 0])
            ->setFromByScope('general')
            ->setTemplateVars([
                'firstname' => (string)$admin->getFirstName(),
                'browser' => $browser,
                'platform' => $platform,
                'ip' => $ip !== '' ? $ip : (string)__('unknown'),
                'time' => gmdate('Y-m-d H:i') . ' UTC',
                'username' => (string)$admin->getUserName(),
                'channel' => (string)($api ? __('Web API (admin token)') : __('Admin panel')),
                // No secret key: an email link with one would only land on the dashboard.
                'accountUrl' => $this->backendUrl->getUrl('adminhtml/system_account/index', ['_nosecret' => true, '_nosid' => true]),
                // Which store's admin, for people who run several.
                'adminHost' => (string)parse_url($this->backendUrl->getBaseUrl(), PHP_URL_HOST),
            ])
            ->addTo($admin->getEmail(), $admin->getName())
            ->getTransport()
            ->sendMessage();
    }
}
