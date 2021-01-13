<?php
/**
 * Model for browser
 * 
 * @package Tony\NotifyOnNewBrowserLogin
 * @author  Wojciech Kaminski <email@email.com>
 */
namespace Tony\NotifyOnNewBrowserLogin\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb;


/**
 * Browser class
 */
class Browser extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('admin_user_browser', 'entity_id');
    }
}
