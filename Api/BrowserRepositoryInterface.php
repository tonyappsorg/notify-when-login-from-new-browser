<?php
namespace Tony\NotifyOnNewBrowserLogin\Api;

interface BrowserRepositoryInterface
{
    public function save(\Tony\NotifyOnNewBrowserLogin\Api\Data\BrowserInterface $browserData);
    public function getByUserId($userId);
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);
}
