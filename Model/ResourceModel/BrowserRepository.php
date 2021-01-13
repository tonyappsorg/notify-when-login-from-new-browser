<?php
namespace Tony\NotifyOnNewBrowserLogin\Model\ResourceModel;

use Magento\Customer\Api\Data\GroupSearchResultsInterfaceFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Tony\NotifyOnNewBrowserLogin\Api\BrowserRepositoryInterface;
use Tony\NotifyOnNewBrowserLogin\Api\Data\BrowserInterface;
use Tony\NotifyOnNewBrowserLogin\Model\BrowserFactory;

class BrowserRepository implements BrowserRepositoryInterface
{
    private $browserFactory;
    private $filterBuilder;
    private $filterGroup;
    private $criteria;
    private $searchResultsFactory;
    private $collectionProcessor;

    public function __construct(
        BrowserFactory $browserFactory,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Framework\Api\Search\FilterGroup $filterGroup,
        \Magento\Framework\Api\SearchCriteriaInterface $criteria,
        GroupSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor = null

    ) {
        $this->browserFactory = $browserFactory;
        $this->criteria = $criteria;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroup = $filterGroup;
        $this->searchResultsFactory = $searchResultsFactory;
    }
    public function save(BrowserInterface $browserData)
    {
        $browserData->getResource()->save($browserData);
    }

    public function getByUserId($userId)
    {
        $filter = $this->filterBuilder
            ->setField('user_id')
            ->setConditionType('eq')
            ->setValue($userId)
            ->create();

        $this->filterGroup->setFilters([$filter]);
        $this->criteria->setFilterGroups([$this->filterGroup]);

        return $this->getList($this->criteria);
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        /** @var \Tony\NotifyOnNewBrowserLogin\Model\ResourceModel\Browser\Collection $collection */
        $collection = $this->browserFactory->create()->getCollection();

        return $this->collectionProcessor->process($searchCriteria, $collection);
    }
}
