<?php
declare(strict_types=1);

namespace Tonyapps\NotifyOnNewBrowserLogin\Model\Observer;

use Magento\Backend\Model\Session\AdminConfig;
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
 */
class StoreLoginDataInformation implements ObserverInterface
{
    public const EMAIL_TEMPLATE_ID = 'tonyapps_notify_on_new_browser_login_template';
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
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var User $user */
        $user = $observer->getUser();
        try {
            $userId = (int)$user->getId();
            $cookie = self::COOKIE_PREFIX . $userId;
            $ip = (string)$this->remoteAddress->getRemoteAddress();
            $token = (string)$this->cookieManager->getCookie($cookie);
            if ($token !== '' && $this->browserResource->isKnown($userId, hash('sha256', $token), $ip)) {
                return;
            }

            $token = bin2hex(random_bytes(32));
            $browser = (string)$this->userAgent->getBrowser();
            $platform = (string)$this->userAgent->getPlatform();
            $this->browserResource->remember($userId, hash('sha256', $token), $browser, $platform, $ip);
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
            $this->notify($user, $browser, $platform, $ip);
        } catch (\Throwable $e) {
            // Never let this block an admin login.
            $this->logger->error('New browser login check failed: ' . $e->getMessage());
        }
    }

    private function notify(User $admin, string $browser, string $platform, string $ip): void
    {
        $this->transportBuilder
            ->setTemplateIdentifier(self::EMAIL_TEMPLATE_ID)
            ->setTemplateOptions(['area' => 'adminhtml', 'store' => 0])
            ->setFromByScope('general')
            ->setTemplateVars([
                'firstname' => (string)$admin->getFirstName(),
                'browser' => $browser,
                'platform' => $platform,
                'ip' => $ip !== '' ? $ip : (string)__('unknown'),
                'time' => gmdate('Y-m-d H:i') . ' UTC',
            ])
            ->addTo($admin->getEmail(), $admin->getName())
            ->getTransport()
            ->sendMessage();
    }
}
