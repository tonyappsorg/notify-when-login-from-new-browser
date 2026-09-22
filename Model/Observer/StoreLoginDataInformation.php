<?php
namespace Tony\NotifyOnNewBrowserLogin\Model\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\User\Model\User;
use Psr\Log\LoggerInterface;
use Tony\NotifyOnNewBrowserLogin\Model\BrowserFactory;
use Tony\NotifyOnNewBrowserLogin\Model\ResourceModel\BrowserRepository;

class StoreLoginDataInformation implements ObserverInterface
{
    const EMAIL_TEMPLATE_ID = 'tony_notify_on_new_browser_login_template';
    private $browserFactory;
    private $browserRepository;
    private $request;
    private $transportBuilder;
    private $userAgent;
    private $logger;

    /**
     * StoreLoginDataInformation constructor.
     * @param \Browser $userAgent
     * @param BrowserFactory $browserFactory
     * @param BrowserRepository $browserRepository
     * @param RequestInterface $request
     * @param TransportBuilder $transportBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        \Browser $userAgent,
        BrowserFactory $browserFactory,
        BrowserRepository $browserRepository,
        RequestInterface $request,
        TransportBuilder $transportBuilder,
        LoggerInterface $logger
    ) {
        $this->userAgent = $userAgent;
        $this->browserFactory = $browserFactory;
        $this->request = $request;
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
        $browser->setIp($this->request->getServer("REMOTE_ADDR"));
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
     * @param \Tony\NotifyOnNewBrowserLogin\Api\Data\BrowserInterface $browser
     * @return $this
     */
    private function notify(User $admin, \Tony\NotifyOnNewBrowserLogin\Api\Data\BrowserInterface $browser)
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
        } catch (LocalizedException $e) {
            $this->logger->error('Failed to send new browser login notification: ' . $e->getMessage());
        } catch (MailException $e) {
            $this->logger->error('Failed to send new browser login notification: ' . $e->getMessage());
        }

        return $this;
    }
}
