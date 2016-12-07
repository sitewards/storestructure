<?php

namespace Sitewards\StoreStructure\Store;

use Magento\Catalog\Model\Category;
use Magento\Store\Model\Group;
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory as StoreCollectionFactory;
use Magento\Store\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Store\Model\ResourceModel\Website\CollectionFactory as WebsiteCollectionFactory;

class Cleaner
{
    /** @var CategoryCollectionFactory */
    protected $categoryCollectionFactory;

    /** @var WebsiteCollectionFactory */
    protected $websiteCollectionFactory;

    /** @var GroupCollectionFactory */
    protected $storeCollectionFactory;

    /** @var  StoreCollectionFactory */
    protected $storeViewCollectionFactory;

    /** @var bool */
    protected $dryRun = false;

    /** @var string[] */
    protected $whiteListedWebsiteCodes;

    /** @var string[] */
    protected $whiteListedRootCategoryNames;

    /** @var string[] */
    protected $whiteListedStoreNames;

    /** @var string[] */
    protected $whiteListedStoreViewCodes;

    public function __construct(
        WebsiteCollectionFactory $websiteCollectionFactory,
        GroupCollectionFactory $storeCollectionFactory,
        StoreCollectionFactory $storeViewCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory
    )
    {
        $this->websiteCollectionFactory   = $websiteCollectionFactory;
        $this->storeCollectionFactory     = $storeCollectionFactory;
        $this->storeViewCollectionFactory = $storeViewCollectionFactory;
        $this->categoryCollectionFactory  = $categoryCollectionFactory;
    }

    /**
     * @param bool $dryRun
     */
    public function reset($dryRun = false)
    {
        $this->dryRun = $dryRun;

        $this->whiteListedWebsiteCodes      = array('');
        $this->whiteListedRootCategoryNames = array('');
        $this->whiteListedStoreNames        = array('');
        $this->whiteListedStoreViewCodes    = array('');
    }

    public function whiteListWebsite($code)
    {
        array_push($this->whiteListedWebsiteCodes, $code);
    }

    public function whiteListRootCategory($name)
    {
        array_push($this->whiteListedRootCategoryNames, $name);
    }

    public function whiteListStore($name)
    {
        array_push($this->whiteListedStoreNames, $name);
    }

    public function whiteListStoreView($code)
    {
        array_push($this->whiteListedStoreViewCodes, $code);
    }

    /**
     * @throws \Exception
     */
    public function cleanup()
    {
        echo 'Cleanup' . PHP_EOL;

        $this->cleanupStoreViews();
        $this->cleanupStores();
        $this->cleanupRootCategories();
        $this->cleanupWebsites();
    }

    protected function cleanupWebsites()
    {
        echo '  websites' . PHP_EOL;

        $websiteCollection = $this->websiteCollectionFactory->create();
        $websiteCollection->addFieldToFilter('code', ['nin' => $this->whiteListedWebsiteCodes]);

        /** @var Website $website */
        foreach ($websiteCollection as $website) {
            echo sprintf(
                '    id: %s, code: "%s", name: "%s"',
                $website->getId(),
                $website->getCode(),
                $website->getName()
            ) . PHP_EOL;

            if (!$this->dryRun) {
                $website->delete();
            }
        }
    }

    protected function cleanupRootCategories()
    {
        echo '  root-categories' . PHP_EOL;

        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addFieldToFilter('name', ['nin' => $this->whiteListedRootCategoryNames]);
        $categoryCollection->addFieldToFilter('level', 1);

        /** @var Category $category */
        foreach ($categoryCollection as $category) {
            echo sprintf(
                '    id: %s, name: "%s"',
                $category->getId(),
                $category->getName()
            ) . PHP_EOL;

            if (!$this->dryRun) {
                try {
                    $category->delete();
                } catch (\Magento\Framework\Exception\LocalizedException $exception) {
                    echo sptrintf(
                        '      can\'t delete category "%s"(id: %s)',
                        $category->getName(),
                        $category->getId()
                    ) . PHP_EOL;
                }
            }
        }
    }

    protected function cleanupStores()
    {
        echo '  stores' . PHP_EOL;

        $storeCollection = $this->storeCollectionFactory->create();
        $storeCollection->addFieldToFilter('name', ['nin' => $this->whiteListedStoreNames]);

        /** @var Group $store */
        foreach ($storeCollection as $store) {
            echo sprintf(
                '    id: %s, name: "%s"',
                $store->getId(),
                $store->getName()
            ) . PHP_EOL;

            if (!$this->dryRun) {
                $store->delete();
            }
        }
    }

    protected function cleanupStoreViews()
    {
        echo '  store-views:' . PHP_EOL;

        $storeViewCollection = $this->storeViewCollectionFactory->create();
        $storeViewCollection->addFieldToFilter('code', ['nin' => $this->whiteListedStoreViewCodes]);

        /** @var Store $storeView */
        foreach ($storeViewCollection as $storeView) {
            echo sprintf(
                '    id: %s, code: "%s", name: "%s"',
                $storeView->getId(),
                $storeView->getCode(),
                $storeView->getName()
            ) . PHP_EOL;

            if (!$this->dryRun) {
                $storeView->delete();
            }
        }
    }
}