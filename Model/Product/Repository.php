<?php
/**
 * Copyright (c) 2017, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2017 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Product;

use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableProduct;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Nosto\Tagging\Helper\Data;

/**
 * Repository wrapper class for fetching products
 *
 * @package Nosto\Tagging\Model\Product
 */
class Repository
{
    private $parentProductIdCache = [];

    private $nostoDataHelper;
    private $productRepository;
    private $searchCriteriaBuilder;
    private $configurableProduct;
    private $filterGroupBuilder;
    private $filterBuilder;

    /**
     * Constructor to instantiating the reindex command. This constructor uses proxy classes for
     * two of the Nosto objects to prevent introspection of constructor parameters when the DI
     * compile command is run.
     * Not using the proxy classes will lead to a "Area code not set" exception being thrown in the
     * compile phase.
     *
     * @param ProductRepository\Proxy $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Data $nostoDataHelper
     * @param ConfigurableProduct $configurableProduct
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     */
    public function __construct(
        ProductRepository\Proxy $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Data $nostoDataHelper,
        ConfigurableProduct $configurableProduct,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder
    ) {
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->nostoDataHelper = $nostoDataHelper;
        $this->configurableProduct = $configurableProduct;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->filterBuilder = $filterBuilder;
    }

    /**
     * Gets products by product ids
     *
     * @param array $ids
     * @return ProductSearchResultsInterface
     */
    public function getByIds(array $ids)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $ids, 'in')
            ->create();
        $products = $this->productRepository->getList($searchCriteria);

        return $products;
    }

    /**
     * Gets products that have scheduled pricing active
     *
     * @return ProductSearchResultsInterface
     */
    public function getWithActivePricingSchedule()
    {
        $today = DateTime::gmtDate();
        $filterEndDateGreater = $this->filterBuilder
            ->setField('special_to_date')
            ->setValue($today->format('Y-m-d ' . '00:00:00'))
            ->setConditionType('gt')
            ->create();
        $filterEndDateNotSet = $this->filterBuilder
            ->setField('special_to_date')
            ->setValue('null')
            ->setConditionType('eq')
            ->create();

        $filterGroup = $this->filterGroupBuilder->setFilters([$filterEndDateGreater, $filterEndDateNotSet])->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->setFilterGroups([$filterGroup])
            ->addFilter('special_from_date', $today->format('Y-m-d') . ' 00:00:00', 'gte')
            ->create();
        $products = $this->productRepository->getList($searchCriteria);

        return $products;
    }

    /**
     * Gets the parent products for simple product
     *
     * @param Product $product
     * @return string[]|null
     * @suppress PhanTypeMismatchReturn
     */
    public function resolveParentProductIds(Product $product)
    {
        if ($this->getParentIdsFromCache($product)) {
            return $this->getParentIdsFromCache($product);
        }
        $parentProductIds = null;
        if ($product->getTypeId() === Type::TYPE_SIMPLE) {
            $parentProductIds = $this->configurableProduct->getParentIdsByChild(
                $product->getId()
            );
            $this->saveParentIdsToCache($product, $parentProductIds);
        }

        return $parentProductIds;
    }

    /**
     * Get parent ids from cache. Return null if the cache is not available
     *
     * @param Product $product
     * @return string[]|null
     */
    private function getParentIdsFromCache(Product $product)
    {
        if (isset($this->parentProductIdCache[$product->getId()])) {
            return $this->parentProductIdCache[$product->getId()];
        }

        return null;
    }

    /**
     * Saves the parents product ids to internal cache to avoid redundant
     * database queries
     *
     * @param Product $product
     * @param string[] $parentProductIds
     */
    private function saveParentIdsToCache(Product $product, $parentProductIds)
    {
        $this->parentProductIdCache[$product->getId()] = $parentProductIds;
    }
}
