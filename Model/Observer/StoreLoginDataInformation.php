<?php
namespace Tonyapps\NotifyOnNewBrowserLogin\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\User\Model\User;
use Psr\Log\LoggerInterface;
use Tonyapps\NotifyOnNewBrowserLogin\Model\BrowserFactory;
use Tonyapps\NotifyOnNewBrowserLogin\Model\ResourceModel\BrowserRepository;

class StoreLoginDataInformation implements ObserverInterface
{
    const EMAIL_TEMPLATE_ID = 'tonyapps_notify_on_new_browser_login_template';
    private $browserFactory;
    private $browserRepository;
    private $remoteAddress;
    private $transportBuilder;
    private $userAgent;
    private $logger;

    /**
     * StoreLoginDataInformation constructor.
     * @param \Browser $userAgent
     * @param BrowserFactory $browserFactory
     * @param BrowserRepository $browserRepository
     * @param RemoteAddress $remoteAddress
     * @param TransportBuilder $transportBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        \Browser $userAgent,
        BrowserFactory $browserFactory,
        BrowserRepository $browserRepository,
        RemoteAddress $remoteAddress,
        TransportBuilder $transportBuilder,
        LoggerInterface $logger
    ) {
        $this->userAgent = $userAgent;
        $this->browserFactory = $browserFactory;
        $this->remoteAddress = $remoteAddress;
        $this->browserRepository = $browserRepository;
        $this->transportBuilder = $transportBuilder;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var User $user */
        $user = $observer->getUser();
        $browser = $this->browserFactory->create();

        $browser->setUserId($user->getId());
        $browser->setIp((string)$this->remoteAddress->getRemoteAddress());
        $browser->setBrowser($this->userAgent->getBrowser());
        $browser->setPlatform($this->userAgent->getPlatform());

        $collection = $this->browserRepository->find($browser);

        if ($collection->getSize() == 0) {
            $this->browserRepository->save($browser);
            $this->notify($user, $browser);
        }
    }

    /**
     * @param User $admin
     * @param \Tonyapps\NotifyOnNewBrowserLogin\Api\Data\BrowserInterface $browser
     * @return $this
     */
    private function notify(User $admin, \Tonyapps\NotifyOnNewBrowserLogin\Api\Data\BrowserInterface $browser)
    {
        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::EMAIL_TEMPLATE_ID)
                ->setTemplateOptions(['area' => 'adminhtml', 'store' => 'admin'])
                ->setFrom('general')
                ->setTemplateVars(
                    [
                        'subject' => 'New sign-in to your admin account',
                        'user' => $admin,
                        'browser' => $browser->getBrowser(),
                    ]
                )
                ->addTo($admin->getEmail(), $admin->getName())
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            // never let a mail failure block admin login
            $this->logger->error('Failed to send new browser login notification: ' . $e->getMessage());
        }

        return $this;
    }
}
