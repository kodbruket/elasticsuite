<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile ElasticSuite to newer
 * versions in the future.
 *
 *
 * @category  Smile
 * @package   Smile\ElasticsuiteVirtualCategory
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2018 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */

namespace Smile\ElasticsuiteVirtualCategory\Model;

use Magento\Catalog\Model\Product\Visibility;
use Smile\ElasticsuiteCore\Search\Request\Query\QueryFactory;
use Smile\ElasticsuiteCore\Search\Request\QueryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Smile\ElasticsuiteCatalog\Model\ResourceModel\Product\Fulltext\CollectionFactory as FulltextCollectionFactory;
use Smile\ElasticsuiteVirtualCategory\Model\KodbrucketPreview\ItemData as ItemDataFactory;
use Smile\ElasticsuiteCatalog\Model\ProductSorter\AbstractPreview;

/**
 * Virtual category preview model.
 *
 * @category Smile
 * @package  Smile\ElasticsuiteVirtualCategory
 * @author   Aurelien FOUCRET <aurelien.foucret@smile.fr>
 */
class KodbrucketPreview
{
    /**
     * @var CategoryInterface
     */
    private $category;

    /**
     * @var QueryFactory
     */
    private $queryFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    private $magentoProductCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var int
     */
    private $storeId;

    /**
     * @var int
     */
    private $size;

    /**
     * @var ItemDataFactory
     */
    private $itemFactory;

    /**
     * Constructor.
     *
     * @param CategoryInterface         $category                 Category to preview.
     * @param FulltextCollectionFactory $productCollectionFactory Fulltext product collection factory.
     * @param ItemDataFactory           $previewItemFactory       Preview item factory.
     * @param QueryFactory              $queryFactory             QueryInterface factory.
     * @param int                       $size                     Preview size.
     */
    public function __construct(
        CategoryInterface $category,
        FulltextCollectionFactory $productCollectionFactory,
        ItemDataFactory $previewItemFactory,
        QueryFactory $queryFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $magentoProductCollectionFactory,
        $size = 10
    ) {
        $this->category     = $category;
        $this->queryFactory = $queryFactory;
        $this->magentoProductCollectionFactory = $magentoProductCollectionFactory;
        $this->collectionFactory = $magentoProductCollectionFactory;
        $this->itemFactory = $previewItemFactory;
        $this->size = $size;
    }

    /**
     * Convert an array of products to an array of preview items.
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product[] $products Product list.
     *
     * @return array
     */
    private function preparePreviewItems($products = [])
    {
        $items = [];
        foreach ($products as $product) {
            $items[$product->getId()] = $this->itemFactory->getData($product);
        }

        return array_values($items);
    }

    /**
     * Return a collection with with products that match the current preview.
     *
     * @return array
     */
    private function getUnsortedProductData()
    {
        $productCollection = $this->getProductCollection()->setPageSize($this->size);

        return ['products' => $productCollection->getItems(), 'size' => $productCollection->getSize()];
    }

    /**
     * Preview base product collection.
     *
     * @return \Smile\ElasticsuiteCatalog\Model\ResourceModel\Product\Fulltext\Collection
     */
    private function getProductCollection()
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection */
        $productCollection = $this->collectionFactory->create();
        $productCollection->setStoreId($this->storeId);
        $productCollection->addAttributeToSelect('name');
        return $this->prepareProductCollection($productCollection);
    }

    /**
     * Return a collection with all products manually sorted loaded.
     *
     * @return \Magento\Catalog\Api\Data\ProductInterface[]
     */
    protected function getSortedProducts()
    {
        $products   = [];
        $productIds = $this->getSortedProductIds();

        if ($productIds && count($productIds)) {
            $productCollection = $this->getProductCollection()->setPageSize(count($productIds));
            $productCollection->addIdFilter($productIds);
            $products = $productCollection->getItems();
        }

        $sortedProducts = [];

        foreach ($this->getSortedProductIds() as $productId) {
            if (isset($products[$productId])) {
                $sortedProducts[$productId] = $products[$productId];
            }
        }

        return $sortedProducts;
    }

    /**
     * {@inheritDoc}
     */
    public function getData()
    {
        $data = $this->getUnsortedProductData();

        $sortedProducts = $this->getSortedProducts();
        $data['products'] = $this->preparePreviewItems(array_merge($sortedProducts, $data['products']));

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    protected function prepareProductCollection( $collection)
    {
        $queryFilter = $this->getQueryFilter();
        if ($queryFilter !== null) {
            /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
            $collection = $this->magentoProductCollectionFactory->create();
            $collection->addAttributeToSelect(['name','price','image' , 'small_image', 'smile_elasticsuite_product_sorter_image']);
            $collection->addIdFilter($queryFilter['should']);
        }

        return $collection;
    }

    /**
     * Return the list of sorted product ids.
     *
     * @return array
     */
    protected function getSortedProductIds()
    {
        return $this->category->getSortedProductIds();
    }

    /**
     * Return the filter applied to the query.
     *
     * @return QueryInterface|array
     */
    private function getQueryFilter()
    {
        $query = null;

        $this->category->setIsActive(true);

        if (($this->category->getIsVirtualCategory() || $this->category->getId()) && is_object($this->category->getVirtualRule())) {
            $query = $this->category->getVirtualRule()->getCategorySearchQuery($this->category);
        }

        if ((bool) $this->category->getIsVirtualCategory() === false) {
            $queryParams = [];

            if ($query !== null) {
                $queryParams['should'][] = $query;
            }
            $added = array_flip($this->category->getAddedProductIds());
            foreach ($this->category->getDeletedProductIds() as $deletedProductId) {
                if (isset($added[$deletedProductId])) {
                    unset($added[$deletedProductId]);
                }
            }
            $idFilters = [
                'should'  => array_flip($added)
            ];

            return $idFilters;
        }

        return $query;
    }
}
