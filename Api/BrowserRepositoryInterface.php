<?php
namespace Tonyapps\NotifyOnNewBrowserLogin\Api;

interface BrowserRepositoryInterface
{
    public function save(\Tonyapps\NotifyOnNewBrowserLogin\Api\Data\BrowserInterface $browserData);
    public function getByUserId($userId);
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);
    public function find(\Tonyapps\NotifyOnNewBrowserLogin\Api\Data\BrowserInterface $browserData);
}
