<?php
namespace Tony\NotifyOnNewBrowserLogin\Model\ResourceModel;

use Magento\Customer\Api\Data\GroupSearchResultsInterfaceFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
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

    /**
     * BrowserRepository constructor.
     * @param BrowserFactory $browserFactory
     * @param FilterBuilder $filterBuilder
     * @param FilterGroup $filterGroup
     * @param SearchCriteriaInterface $criteria
     * @param GroupSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface|null $collectionProcessor
     */
    public function __construct(
        BrowserFactory $browserFactory,
        FilterBuilder $filterBuilder,
        FilterGroup $filterGroup,
        SearchCriteriaInterface $criteria,
        GroupSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->browserFactory = $browserFactory;
        $this->criteria = $criteria;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroup = $filterGroup;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @param BrowserInterface $browserData
     */
    public function save(BrowserInterface $browserData)
    {
        $browserData->getResource()->save($browserData);
    }

    /**
     * @param $userId
     */
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

    /**
     * @param SearchCriteriaInterface $searchCriteria
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        /** @var \Tony\NotifyOnNewBrowserLogin\Model\ResourceModel\Browser\Collection $collection */
        $collection = $this->browserFactory->create()->getCollection();

        $this->collectionProcessor->process($searchCriteria, $collection);

        return $collection;
    }

    /**
     * @param BrowserInterface $browserData
     */
    public function find(\Tony\NotifyOnNewBrowserLogin\Api\Data\BrowserInterface $browserData)
    {
        $filterUser = $this->filterBuilder
            ->setField('user_id')
            ->setConditionType('eq')
            ->setValue($browserData->getUserId())
            ->create();

        $filterBrowser = $this->filterBuilder
            ->setField('browser')
            ->setConditionType('eq')
            ->setValue($browserData->getBrowser())
            ->create();

        $filterPlatform = $this->filterBuilder
            ->setField('platform')
            ->setConditionType('eq')
            ->setValue($browserData->getPlatform())
            ->create();

        $this->filterGroup->setFilters([$filterUser, $filterBrowser, $filterPlatform]);
        $this->criteria->setFilterGroups([$this->filterGroup]);

        return $this->getList($this->criteria);
    }
}
