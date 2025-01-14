<?php

/*
 * This file was created by developers working at BitBag
 * Do you need more information about us and what we do? Visit our https://bitbag.io website!
 * We are hiring developers from all over the world. Join us and start your new, exciting adventure and become part of us: https://bitbag.io/career
*/

declare(strict_types=1);

namespace BitBag\SyliusVueStorefront2Plugin\CommandHandler\Checkout;

use BitBag\SyliusVueStorefront2Plugin\Command\Checkout\ShippingAddressOrder;
use BitBag\SyliusVueStorefront2Plugin\Resolver\OrderAddressStateResolverInterface;
use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\CoreBundle\Resolver\CustomerResolverInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Webmozart\Assert\Assert;

/** @experimental */
final class ShippingAddressOrderHandler implements MessageHandlerInterface
{
    public const EVENT_NAME = 'bitbag.sylius_vue_storefront2.set_order_shipping_address.complete';

    private OrderRepositoryInterface $orderRepository;

    private ObjectManager $manager;

    private CustomerResolverInterface $customerResolver;

    private OrderAddressStateResolverInterface $addressStateResolver;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ObjectManager $manager,
        CustomerResolverInterface $customerResolver,
        OrderAddressStateResolverInterface $addressStateResolver,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->orderRepository = $orderRepository;
        $this->manager = $manager;
        $this->customerResolver = $customerResolver;
        $this->addressStateResolver = $addressStateResolver;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function __invoke(ShippingAddressOrder $command): OrderInterface
    {
        $tokenValue = $command->orderTokenValue;
        Assert::notNull($tokenValue);

        /** @var OrderInterface|null $order */
        $order = $this->orderRepository->findCartByTokenValue($tokenValue);
        Assert::notNull($order, sprintf('Order with %s token has not been found.', $tokenValue));

        $this->applyCustomer($order, $command);
        $this->applyShippingAddress($command, $order);
        $this->addressStateResolver->resolve($order);
        $this->manager->persist($order);

        /** @psalm-suppress TooManyArguments */
        $this->eventDispatcher->dispatch(new GenericEvent($order, [$command]), self::EVENT_NAME);

        return $order;
    }

    private function applyCustomer(OrderInterface $order, ShippingAddressOrder $command): void
    {
        if (null === $order->getCustomer() && null !== $command->email) {
            $order->setCustomer($this->customerResolver->resolve($command->email));
        }
    }

    private function applyShippingAddress(ShippingAddressOrder $command, OrderInterface $order): void
    {
        if ($command->shippingAddress !== null && $command->shippingAddress instanceof AddressInterface) {
            /** @psalm-suppress ArgumentTypeCoercion */
            $order->setShippingAddress($command->shippingAddress);
        }
    }
}
