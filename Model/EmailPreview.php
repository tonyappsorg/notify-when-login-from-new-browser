<?php
declare(strict_types=1);

namespace Tonyapps\NotifyOnNewBrowserLogin\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\User\Model\UserFactory;
use Tonyapps\NotifyOnNewBrowserLogin\Model\Observer\StoreLoginDataInformation;
use Tonyapps\NotifyOnNewBrowserLogin\Model\ResourceModel\Browser as BrowserResource;

/**
 * Real-data preview of the new-device sign-in email for Tonyapps_EmailDesigner (registered in etc/di.xml,
 * duck-typed): a recorded device, with the variables built by the observer that sends the email. Read-only; it
 * never calls the resource's isKnown()/remember(), which write.
 *
 * The table keeps the device's last IP, not the first, and not whether it was the admin panel or the web API, so
 * the preview shows the last IP, the "Admin panel" channel, and the time the device was first seen.
 */
class EmailPreview
{
    private const TEMPLATE = 'admin_security_new_browser_login_template';

    public function __construct(
        private readonly BrowserResource $browsers,
        private readonly UserFactory $userFactory,
        private readonly StoreLoginDataInformation $sender
    ) {
    }

    /** @return array<string, string> device ID => label, newest first */
    public function getRecords(string $templateId, int $limit): array
    {
        $this->check($templateId);
        $connection = $this->browsers->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['b' => $this->browsers->getMainTable()], ['entity_id', 'browser', 'platform', 'ip', 'created_at'])
                // Inner join: a deleted admin user has no email to preview.
                ->join(['u' => $this->browsers->getTable('admin_user')], 'u.user_id = b.user_id', ['username'])
                ->order('b.entity_id DESC')
                ->limit($limit)
        );

        $records = [];
        foreach ($rows as $row) {
            $records[(string)$row['entity_id']] = implode(' · ', array_filter([
                (string)$row['username'],
                trim($row['browser'] . ' / ' . $row['platform'], ' /'),
                (string)$row['ip'],
                substr((string)$row['created_at'], 0, 16),
            ]));
        }
        return $records;
    }

    /** @return array<string, string> */
    public function getVariables(string $templateId, string $recordId): array
    {
        $this->check($templateId);
        $connection = $this->browsers->getConnection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->browsers->getMainTable())->where('entity_id = ?', (int)$recordId)
        );
        $user = $row ? $this->userFactory->create()->load((int)$row['user_id']) : null;
        if (!$user || !$user->getId()) {
            throw new LocalizedException(__('The sign-in could not be found.'));
        }
        return $this->sender->getVariables(
            $user,
            (string)$row['browser'],
            (string)$row['platform'],
            (string)$row['ip'],
            false,
            // Magento stores timestamps in UTC.
            (int)strtotime($row['created_at'] . ' UTC')
        );
    }

    /** The observer sends the alert from the admin store (0), like every adminhtml email. */
    public function getStoreId(string $templateId, string $recordId): ?int
    {
        $this->check($templateId);
        return 0;
    }

    private function check(string $templateId): void
    {
        if ($templateId !== self::TEMPLATE) {
            throw new LocalizedException(__('No real-data preview for this email.'));
        }
    }
}
