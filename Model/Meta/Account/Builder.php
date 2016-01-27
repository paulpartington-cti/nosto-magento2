<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Nosto
 * @package   Nosto_Tagging
 * @author    Nosto Solutions Ltd <magento@nosto.com>
 * @copyright Copyright (c) 2013-2016 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Nosto\Tagging\Model\Meta\Account;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Currency;
use Nosto\Tagging\Helper\Data;
use Psr\Log\LoggerInterface;

class Builder
{
    /**
     * @param Factory           $factory
     * @param Data              $dataHelper
     * @param Currency          $currencyHelper
     * @param Owner\Builder     $accountOwnerMetaBuilder
     * @param Billing\Builder   $accountBillingMetaBuilder
     * @param ResolverInterface $localeResolver
     * @param LoggerInterface   $logger
     */
    public function __construct(
        Factory $factory,
        Data $dataHelper,
        Currency $currencyHelper,
        \Nosto\Tagging\Model\Meta\Account\Owner\Builder $accountOwnerMetaBuilder,
        \Nosto\Tagging\Model\Meta\Account\Billing\Builder $accountBillingMetaBuilder,
        ResolverInterface $localeResolver,
        LoggerInterface $logger
    ) {
        $this->_factory = $factory;
        $this->_dataHelper = $dataHelper;
        $this->_currencyHelper = $currencyHelper;
        $this->_accountOwnerMetaBuilder = $accountOwnerMetaBuilder;
        $this->_accountBillingMetaBuilder = $accountBillingMetaBuilder;
        $this->_localeResolver = $localeResolver;
        $this->_logger = $logger;
    }

    /**
     * @param Store $store
     * @return \Nosto\Tagging\Model\Meta\Account
     */
    public function build(Store $store)
    {
        $metaData = $this->_factory->create();

        try {
            $metaData->setTitle(
                implode(
                    ' - ',
                    [
                        $store->getWebsite()->getName(),
                        $store->getGroup()->getName(),
                        $store->getName()
                    ]
                )
            );
            $metaData->setName(substr(sha1(rand()), 0, 8));
            $metaData->setFrontPageUrl(
                \NostoHttpRequest::replaceQueryParamInUrl(
                    '___store',
                    $store->getCode(),
                    $store->getBaseUrl(UrlInterface::URL_TYPE_WEB)
                )
            );

            $metaData->setCurrency(
                new \NostoCurrencyCode($store->getBaseCurrencyCode())
            );
            $lang = substr($store->getConfig('general/locale/code'), 0, 2);
            $metaData->setLanguage(new \NostoLanguageCode($lang));
            $lang = substr($this->_localeResolver->getLocale(), 0, 2);
            $metaData->setOwnerLanguage(new \NostoLanguageCode($lang));

            $owner = $this->_accountOwnerMetaBuilder->build();
            $metaData->setOwner($owner);

            $billing = $this->_accountBillingMetaBuilder->build($store);
            $metaData->setBilling($billing);

            $currencyCodes = $store->getAvailableCurrencyCodes(true);
            if (is_array($currencyCodes) && count($currencyCodes) > 0) {
                $currencies = [];
                foreach ($currencyCodes as $currencyCode) {
                    $currencies[$currencyCode] = $this->_currencyHelper->getCurrencyObject(
                        $store->getConfig('general/locale/code'),
                        $currencyCode
                    );
                }
                $metaData->setCurrencies($currencies);
                if (count($currencyCodes) > 1) {
                    $metaData->setDefaultPriceVariationId(
                        $store->getBaseCurrencyCode()
                    );
                    $metaData->setUseCurrencyExchangeRates(
                        $this->_dataHelper->isMultiCurrencyMethodExchangeRate(
                            $store
                        )
                    );
                }
            }

        } catch (\NostoException $e) {
            $this->_logger->error($e, ['exception' => $e]);
        }

        return $metaData;
    }
}
