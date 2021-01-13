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

    public function __construct(
        BrowserFactory $browserFactory,
        BrowserRepository $browserRepository,
        RequestInterface $request,
        TransportBuilder $transportBuilder
    ) {
        $this->browserFactory = $browserFactory;
        $this->request = $request;
        $this->browserRepository = $browserRepository;
        $this->transportBuilder = $transportBuilder;
    }
    public function execute(Observer $observer)
    {
        /** @var User $user */
        $user = $observer->getUser();
        /** @var \Tony\NotifyOnNewBrowserLogin\Model\Browser $browser */
        $browser = $this->browserFactory->create();

        $browser->setUserId($user->getId());
        $browser->setIp($this->request->getServer("REMOTE_ADDR"));
        $browser->setBrowser($this->request->getServer("HTTP_USER_AGENT"));

//        $collection = $this->browserRepository->getByUserId($user->getId());

//        foreach ($collection as $entry) {
//        }

//        $this->browserRepository->save($browser);
        $this->notify($user);
    }

    private function notify(User $admin)
    {
        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::EMAIL_TEMPLATE_ID)
                ->setTemplateOptions(['area' => 'adminhtml', 'store' => 'admin'])
                ->setFrom('general')
                ->setTemplateVars(
                    [
                        'subject' => '12322222'
                    ]
                )
                ->addTo($admin->getEmail(), $admin->getName())
                ->getTransport();

            $transport->sendMessage();
        } catch (LocalizedException $e) {
        } catch (MailException $e) {
        }

        return $this;
    }
}
