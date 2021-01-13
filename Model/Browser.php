<?php
namespace Tony\NotifyOnNewBrowserLogin\Model;

use Magento\Framework\Model\AbstractExtensibleModel;
use Tony\NotifyOnNewBrowserLogin\Api\Data\BrowserInterface;

class Browser extends AbstractExtensibleModel implements BrowserInterface
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Browser::class);
    }

    public function setUserId(int $id)
    {
        $this->setData('user_id', $id);

        return $this;
    }

    public function setUser($user)
    {
        $this->setUserId($user->getId());

        $this->setUser($user);

        return $this;
    }

    public function getUserId() : int
    {
        return $this->getData('user_id');
    }

    public function setBrowser($id)
    {
        $this->setData('browser', $id);

        return $this;
    }
    public function getBrowser($id) : string
    {
        return $this->getData('browser');
    }
    public function setIp($id)
    {
        $this->setData('ip', $id);

        return $this;
    }
    public function getIp($id) : string
    {
        return $this->getData('ip');
    }
}
