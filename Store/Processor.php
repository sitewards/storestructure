<?php

namespace Sitewards\StoreStructure\Store;

use Magento\Catalog\Model\Category;
use Symfony\Component\Yaml\Yaml;

use Magento\Store\Model\Store;
use Magento\Store\Model\Group;
use Magento\Store\Model\Website;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\GroupFactory;
use Magento\Store\Model\WebsiteFactory;
use Magento\Framework\Registry;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Store\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;


class Processor
{
    /** @var Yaml */
    protected $yamlParser;

    /** @var array */
    protected $storeStructure;

    /** @var CategoryCollectionFactory */
    protected $categoryCollectionFactory;

    /** @var bool */
    protected $cleanup = false;

    /** @var bool */
    protected $dryRun = false;

    /** @var Cleaner */
    protected $cleaner;

    /** @var CategoryFactory */
    protected $categoryFactory;

    /** @var WebsiteFactory */
    protected $websiteFactory;

    /** @var GroupFactory */
    protected $storeFactory;

    /** @var StoreFactory */
    protected $storeViewFactory;

    /** @var Registry */
    protected $registry;

    /** @var GroupCollectionFactory */
    protected $storeCollectionFactory;

    public function __construct(
        Yaml $yamlParser,
        CategoryCollectionFactory $categoryCollectionFactory,
        WebsiteFactory $websiteFactory,
        GroupFactory $storeFactory,
        StoreFactory $storeViewFactory,
        CategoryFactory $categoryFactory,
        Cleaner $cleaner,
        Registry $registry,
        GroupCollectionFactory $storeCollectionFactory
    )
    {
        $this->yamlParser                = $yamlParser;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->websiteFactory            = $websiteFactory;
        $this->storeFactory              = $storeFactory;
        $this->storeViewFactory          = $storeViewFactory;
        $this->categoryFactory           = $categoryFactory;
        $this->cleaner                   = $cleaner;
        $this->registry                  = $registry;
        $this->storeCollectionFactory    = $storeCollectionFactory;
    }

    /**
     * @param string $configFile
     * @param bool   $cleanup
     * @param bool   $dryRun
     */
    public function loadConfiguration($configFile, $cleanup = false, $dryRun = false)
    {
        $config               = file_get_contents($configFile);
        $this->storeStructure = $this->yamlParser->parse($config);
        $this->cleanup        = $cleanup;
        $this->dryRun         = $dryRun;

        $this->cleaner->reset($dryRun);

        if ($this->registry->registry('isSecureArea') === null) {
            $this->registry->register('isSecureArea', true);
        }
    }

    /**
     * @throws \Exception
     */
    public function buildStoreStructure()
    {
        if (empty($this->storeStructure['store-structure'])
                || empty($this->storeStructure['store-structure']['websites'])
                || !is_array($this->storeStructure['store-structure']['websites'])) {
            echo 'store structure not found in the input file' . PHP_EOL;
        }
        $websitesArray     = $this->storeStructure['store-structure']['websites'];
        $websiteOrderIndex = 0;
        foreach ($websitesArray as $websiteEntry) {
            $websiteOrderIndex++;
            $websiteId = $this->createWebsite($websiteEntry['name'], $websiteEntry['code'], $websiteOrderIndex);

            if (empty($websiteEntry['stores']) || !is_array($websiteEntry['stores'])) {
                continue;
            }
            $storesArray = $websiteEntry['stores'];
            foreach ($storesArray as $storeEntry) {
                $storeId = $this->createStore($storeEntry['name'], $storeEntry['root-category'], $websiteId);

                if (empty($storeEntry['store-views']) || !is_array($storeEntry['store-views'])) {
                    continue;
                }
                $storeViewsArray     = $storeEntry['store-views'];
                $storeViewOrderIndex = 0;
                foreach ($storeViewsArray as $storeViewEntry) {
                    $storeViewOrderIndex++;
                    $this->createStoreView(
                        $storeViewEntry['name'],
                        $storeViewEntry['code'],
                        $websiteId,
                        $storeId,
                        $storeViewOrderIndex
                    );
                }
            }
        }

        if ($this->cleanup) {
            $this->cleaner->cleanup();
        }
    }

    /**
     * @param string $name
     * @param string $code
     * @param int    $sortOrder
     *
     * @return mixed
     * @throws \Exception
     */
    protected function createWebsite($name, $code, $sortOrder)
    {
        echo 'website: ' . $name;

        $this->cleaner->whiteListWebsite($code);

        /** @var Website $website */
        $website = $this->websiteFactory->create();
        $website->load($code, 'code');

        if (empty($website->getId())) {
            echo ' CREATING' . PHP_EOL;
        } else {
            echo ' UPDATING' . PHP_EOL;
        }

        $website->setCode($code);
        $website->setName($name);
        $website->setSortOrder($sortOrder);
        if (!$this->dryRun) {
            $website->save();
            return $website->getId();
        } else {
            return 0;
        }
    }

    /**
     * @param string $name
     *
     * @return int
     * @throws \Exception
     */
    protected function createRootCategory($name)
    {
        echo '    root-category: ' . $name;

        $this->cleaner->whiteListRootCategory($name);

        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addFieldToFilter('name', $name);
        $categoryCollection->addFieldToFilter('level', 1);
        $categoryCollection->setPageSize(1);

        if ($categoryCollection->getSize()) {
            echo ' FOUND' . PHP_EOL;

            /** @var Category $category */
            $category   = $categoryCollection->getFirstItem();
            $categoryId = $category->getId();
        } else {
            echo ' CREATING' . PHP_EOL;

            /** @var Category $category */
            $category = $this->categoryFactory->create();
            $category->setData([
                'name'             => $name,
                'is_active'        => true,
                'position'         => 1,
                'include_in_menu'  => true,
                'attribute_set_id' => 3, /*Magic number? */
                'parent_id'        => 1 /* we are creating root categories, right? */
            ]);

            $category->setCustomAttributes([
                'display_mode'               => 'PRODUCTS',
                'is_anchor'                  => true,
                'custom_use_parent_settings' => false,
                'custom_apply_to_products'   => false,
                'url_key'                    => mb_strtolower(preg_replace('/[^\-\_\.a-zA-Z0-9]/', '-', $name)),
                'url_path'                   => mb_strtolower(preg_replace('/[^\-\_\.a-zA-Z0-9]/', '-', $name)),
                'automatic_sorting'          => false,
            ]);

            if (!$this->dryRun) {
                $category->save();
                $categoryId = $category->getId();
            } else {
                $categoryId = 0;
            }
        }

        return $categoryId;
    }

    /**
     * @param string $name
     * @param string $rootCategoryName
     * @param int    $websiteId
     *
     * @return mixed
     * @throws \Exception
     */
    protected function createStore($name, $rootCategoryName, $websiteId)
    {
        echo '  store: ' . $name;

        $this->cleaner->whiteListStore($name);

        $storeCollection = $this->storeCollectionFactory->create();
        $storeCollection->addFieldToFilter('name', $name);
        $storeCollection->addFieldToFilter('website_id', $websiteId);
        $storeCollection->setPageSize(1);

        if ($storeCollection->getSize()) {
            echo ' UPDATING' . PHP_EOL;


            /** @var Group $store */
            $store = $storeCollection->getFirstItem();
        } else {
            echo ' CREATING' . PHP_EOL;
            /** @var Group $store */
            $store = $this->storeFactory->create();
        }

        $rootCategoryId = $this->createRootCategory($rootCategoryName);
        $store->setName($name);
        $store->setRootCategoryId($rootCategoryId);
        $store->setWebsiteId($websiteId);
        if (!$this->dryRun) {
            $store->save();
            return $store->getId();
        } else {
            return 0;
        }
    }

    /**
     * @param string $name
     * @param string $code
     * @param int    $websiteId
     * @param int    $storeId
     * @param int    $sortOrder
     * @param bool   $isActive
     *
     * @return int
     * @throws \Exception
     */
    protected function createStoreView($name, $code, $websiteId, $storeId, $sortOrder, $isActive = true)
    {
        echo '    storeview: ' . $name;

        $this->cleaner->whiteListStoreView($code);

        /** @var Store $storeView */
        $storeView = $this->storeViewFactory->create();
        $storeView->load($code, 'code');

        if (empty($storeView->getId())) {
            echo ' CREATING' . PHP_EOL;
        } else {
            echo ' UPDATING' . PHP_EOL;
        }

        $storeView->setName($name);
        $storeView->setCode($code);
        $storeView->setWebsiteId($websiteId);
        $storeView->setGroupId($storeId);
        $storeView->setSortOrder($sortOrder);
        $storeView->setIsActive($isActive);

        if (!$this->dryRun) {
            $storeView->save();
            return $storeView->getId();
        } else {
            return 0;
        }
    }
}