<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile ElasticSuite to newer
 * versions in the future.
 *
 *
 * @category  Smile
 * @package   Smile\ElasticsuiteCatalog
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2018 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */

namespace Smile\ElasticsuiteVirtualCategory\Model\KodbrucketPreview;


use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Catalog\Helper\Image as ImageHelper;

/**
 * Product sorter item model.
 *
 * @category Smile
 * @package  Smile\ElasticsuiteCatalog
 * @author   Aurelien FOUCRET <aurelien.foucret@smile.fr>
 */
class ItemData
{
    /**
     * @var ImageHelper
     */
    private $imageHelper;

    /**
     * Constructor.
     *
     * @param ImageHelper $imageHelper Image helper.
     */
    public function __construct(ImageHelper $imageHelper)
    {
        $this->imageHelper = $imageHelper;
    }

    /**
     * Item data.
     *
     * @param ProductInterface $product Product.
     *
     * @return array
     */
    public function getData(ProductInterface $product)
    {
        $productItemData = [
            'id'          => $product->getId(),
            'sku'         => $product->getSku(),
            'name'        => $product->getName(),
            'price'       => $this->getProductPrice($product),
            'image'       => $this->getImageUrl($product),
            'score'       => 0,
            'is_in_stock' => $this->isInStockProduct($product),
        ];

        return $productItemData;
    }

    /**
     * Returns current product sale price.
     *
     * @param ProductInterface $product Product.
     *
     * @return float
     */
    private function getProductPrice(ProductInterface $product)
    {
        return $product->getPrice();
    }

    /**
     * Returns current product stock status.
     *
     * @param ProductInterface $product Product.
     *
     * @return bool
     */
    private function isInStockProduct(ProductInterface $product)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\CatalogInventory\Model\Stock\Item $stockItem */
        $stockItem = $objectManager->create(\Magento\CatalogInventory\Model\Stock\Item::class);
        $stockItem->load($product->getId(), 'product_id');
        return 0 != $stockItem->getQty();
    }

    /**
     * Get resized image URL.
     *
     * @param ProductInterface $product Product.
     *
     * @return string
     */
    private function getImageUrl(ProductInterface $product)
    {
        $this->imageHelper->init($product, 'smile_elasticsuite_product_sorter_image');
        return $this->imageHelper->getUrl();
    }
}
