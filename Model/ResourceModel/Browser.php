<?php
declare(strict_types=1);

namespace Tonyapps\NotifyOnNewBrowserLogin\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Stdlib\DateTime;

/**
 * Devices each admin user has signed in from, identified by the hash of a random cookie token.
 */
class Browser extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('admin_user_browser', 'entity_id');
    }

    /** Whether this user has signed in from this device before; records the current IP and time if so. */
    public function isKnown(int $userId, string $tokenHash, string $ip): bool
    {
        $connection = $this->getConnection();
        $id = $connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), 'entity_id')
                ->where('user_id = ?', $userId)
                ->where('token_hash = ?', $tokenHash)
        );
        if (!$id) {
            return false;
        }
        $connection->update(
            $this->getMainTable(),
            ['ip' => $ip, 'last_seen_at' => gmdate(DateTime::DATETIME_PHP_FORMAT)],
            ['entity_id = ?' => (int)$id]
        );

        return true;
    }

    public function remember(int $userId, string $tokenHash, string $browser, string $platform, string $ip): void
    {
        $this->getConnection()->insert($this->getMainTable(), [
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'browser' => $browser,
            'platform' => $platform,
            'ip' => $ip,
            'last_seen_at' => gmdate(DateTime::DATETIME_PHP_FORMAT),
        ]);
    }
}
