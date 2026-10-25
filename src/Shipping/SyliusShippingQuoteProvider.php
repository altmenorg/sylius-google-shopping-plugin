<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Shipping;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedProduct;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedVariant;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\ShippingQuote;
use Sylius\Component\Addressing\Matcher\ZoneMatcherInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\Repository\ShippingMethodRepositoryInterface;
use Sylius\Component\Order\Factory\OrderItemUnitFactoryInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Checker\Eligibility\ShippingMethodEligibilityCheckerInterface;

/**
 * Default shipping costs: what the checkout of the shop would charge for an order of one unit of the variant,
 * shipped to the country of the feed. The order is built in memory with the Sylius factories and never saved;
 * the shipping methods, their eligibility (rules, category requirements) and their calculators are those of
 * Sylius, including the shop's own ones.
 *
 * Methods: enabled in the channel, for a zone that contains the country, minus `shipping.excluded_methods`
 * (codes: pick-up points, store pick-up…). Quotes from the cheapest, then in order of method position. A method
 * whose calculator fails for this order is left out.
 */
final class SyliusShippingQuoteProvider implements ShippingQuoteProviderInterface
{
    /** @var array<string, array{channel: ChannelInterface, methods: list<ShippingMethodInterface>}|null> */
    private array $setup = [];

    /**
     * @param list<string> $excludedMethods
     */
    public function __construct(
        private readonly ChannelRepositoryInterface $channels,
        private readonly RepositoryInterface $variants,
        private readonly ShippingMethodRepositoryInterface $methods,
        private readonly ZoneMatcherInterface $zoneMatcher,
        private readonly ShippingMethodEligibilityCheckerInterface $eligibility,
        private readonly ServiceRegistryInterface $calculators,
        private readonly FactoryInterface $orderFactory,
        private readonly FactoryInterface $orderItemFactory,
        private readonly OrderItemUnitFactoryInterface $orderItemUnitFactory,
        private readonly FactoryInterface $shipmentFactory,
        private readonly FactoryInterface $addressFactory,
        private readonly array $excludedMethods = [],
    ) {
    }

    public function quotes(FeedProduct $product, FeedVariant $variant, FeedContext $context): array
    {
        $setup = $this->setup($context);
        $entity = $this->variants->find($variant->id);
        if ($setup === null || $setup['methods'] === [] || !$entity instanceof ProductVariantInterface) {
            return [];
        }
        $shipment = $this->shipment($setup['channel'], $entity, $variant->basePrice(), $context);

        $rows = [];
        foreach ($setup['methods'] as $method) {
            try {
                if (!$this->eligibility->isEligible($shipment, $method)) {
                    continue;
                }
                /** @var CalculatorInterface $calculator */
                $calculator = $this->calculators->get((string) $method->getCalculator());
                $price = $calculator->calculate($shipment, $method->getConfiguration());
            } catch (\Throwable) {
                continue; // no setting for this channel, or a calculator that cannot price this order
            }
            $method->setCurrentLocale($context->localeCode);
            $method->setFallbackLocale($context->localeCode);
            $rows[] = [$price, (int) $method->getPosition(), (int) $method->getId(), (string) $method->getName()];
        }
        usort($rows, static fn (array $a, array $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

        return array_map(static fn (array $r) => new ShippingQuote($r[3], (int) $r[0]), $rows);
    }

    /** @return array{channel: ChannelInterface, methods: list<ShippingMethodInterface>}|null */
    private function setup(FeedContext $context): ?array
    {
        $key = $context->channelCode . '|' . $context->countryCode;
        if (array_key_exists($key, $this->setup)) {
            return $this->setup[$key];
        }
        $channel = $this->channels->findOneByCode($context->channelCode);
        if (!$channel instanceof ChannelInterface || $context->countryCode === null) {
            return $this->setup[$key] = null;
        }
        $zones = $this->zoneMatcher->matchAll($this->address($context), 'shipping');
        $methods = [];
        foreach ($zones === [] ? [] : $this->methods->findEnabledForZonesAndChannel($zones, $channel) as $method) {
            if (!in_array($method->getCode(), $this->excludedMethods, true)) {
                $methods[] = $method;
            }
        }

        return $this->setup[$key] = ['channel' => $channel, 'methods' => $methods];
    }

    private function shipment(ChannelInterface $channel, ProductVariantInterface $variant, int $unitPrice, FeedContext $context): ShipmentInterface
    {
        /** @var OrderInterface $order */
        $order = $this->orderFactory->createNew();
        $order->setChannel($channel);
        $order->setCurrencyCode($context->currencyCode);
        $order->setLocaleCode($context->localeCode);
        $order->setShippingAddress($this->address($context));

        /** @var OrderItemInterface $item */
        $item = $this->orderItemFactory->createNew();
        $item->setVariant($variant);
        $item->setUnitPrice($unitPrice);
        $this->orderItemUnitFactory->createForItem($item);
        $order->addItem($item);

        /** @var ShipmentInterface $shipment */
        $shipment = $this->shipmentFactory->createNew();
        $order->addShipment($shipment);
        foreach ($item->getUnits() as $unit) {
            $shipment->addUnit($unit);
        }

        return $shipment;
    }

    private function address(FeedContext $context): AddressInterface
    {
        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();
        $address->setCountryCode($context->countryCode);

        return $address;
    }
}
