<?php
namespace Tony\NotifyOnNewBrowserLogin\Model\ResourceModel\Browser;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tony\NotifyOnNewBrowserLogin\Model\Browser;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Browser::class, \Tony\NotifyOnNewBrowserLogin\Model\ResourceModel\Browser::class);
    }
}
