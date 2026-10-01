<?php
namespace Tonyapps\NotifyOnNewBrowserLogin\Model\ResourceModel\Browser;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tonyapps\NotifyOnNewBrowserLogin\Model\Browser;

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
        $this->_init(Browser::class, \Tonyapps\NotifyOnNewBrowserLogin\Model\ResourceModel\Browser::class);
    }
}
