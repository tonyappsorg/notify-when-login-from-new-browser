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

    /**
     * @param int $id
     * @return $this
     */
    public function setUserId(int $id)
    {
        $this->setData('user_id', $id);

        return $this;
    }

    /**
     * @return int
     */
    public function getUserId() : int
    {
        return $this->getData('user_id');
    }

    /**
     * @param string $browser
     * @return $this
     */
    public function setBrowser(string $browser)
    {
        $this->setData('browser', $browser);

        return $this;
    }

    /**
     * @return string
     */
    public function getBrowser() : string
    {
        return $this->getData('browser');
    }

    /**
     * @param string $platform_name
     * @return $this
     */
    public function setPlatform(string $platform_name)
    {
        $this->setData('platform', $platform_name);

        return $this;
    }

    /**
     * @return string
     */
    public function getPlatform() : string
    {
        return $this->getData('platform');
    }

    /**
     * @param string $ip
     * @return $this
     */
    public function setIp(string $ip)
    {
        $this->setData('ip', $ip);

        return $this;
    }

    /**
     * @return string
     */
    public function getIp() : string
    {
        return $this->getData('ip');
    }
}
