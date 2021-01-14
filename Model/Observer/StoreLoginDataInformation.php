<?php
namespace Tony\NotifyOnNewBrowserLogin\Model\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\User\Model\User;
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

    /**
     * StoreLoginDataInformation constructor.
     * @param \Browser $userAgent
     * @param BrowserFactory $browserFactory
     * @param BrowserRepository $browserRepository
     * @param RequestInterface $request
     * @param TransportBuilder $transportBuilder
     */
    public function __construct(
        \Browser $userAgent,
        BrowserFactory $browserFactory,
        BrowserRepository $browserRepository,
        RequestInterface $request,
        TransportBuilder $transportBuilder
    ) {
        $this->userAgent = $userAgent;
        $this->browserFactory = $browserFactory;
        $this->request = $request;
        $this->browserRepository = $browserRepository;
        $this->transportBuilder = $transportBuilder;
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

//        if ($collection->getSize() == 0) {
//            $this->browserRepository->save($browser);
            $this->notify($user);
//        }
    }

    /**
     * @param User $admin
     * @return $this
     */
    private function notify(User $admin)
    {
        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::EMAIL_TEMPLATE_ID)
                ->setTemplateOptions(['area' => 'adminhtml', 'store' => 'admin'])
                ->setFrom('general')
                ->setTemplateVars(
                    [
                        'subject' => '12322222',
                        'user' => $admin
                    ]
                )
                ->addTo($admin->getEmail(), $admin->getName())
                ->getTransport();

            $transport->sendMessage();
        } catch (LocalizedException $e) {
            var_dump($e->getMessage());
            exit;
        } catch (MailException $e) {
            var_dump($e->getMessage());
            exit;
        }

        return $this;
    }
}
