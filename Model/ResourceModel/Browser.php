<?php
/**
 * Model for browser
 * 
 * @package Tonyapps\NotifyOnNewBrowserLogin
 * @author  Wojciech Kaminski <email@email.com>
 */
namespace Tonyapps\NotifyOnNewBrowserLogin\Model\ResourceModel;

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
