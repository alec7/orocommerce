<?php

namespace Oro\Bundle\PaymentBundle\Provider;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\CustomerBundle\Security\CustomerUserProvider;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\PaymentBundle\Event\TransactionCompleteEvent;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides functionality to work with payment transactions such as creating for current runtime, saving and fetching.
 */
class PaymentTransactionProvider
{
    use LoggerAwareTrait;

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /** @var CustomerUserProvider */
    protected $customerUserProvider;

    /** @var string */
    protected $paymentTransactionClass;

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /**
     * @param DoctrineHelper $doctrineHelper
     * @param CustomerUserProvider $customerUserProvider
     * @param EventDispatcherInterface $dispatcher
     * @param string $paymentTransactionClass
     */
    public function __construct(
        DoctrineHelper $doctrineHelper,
        CustomerUserProvider $customerUserProvider,
        EventDispatcherInterface $dispatcher,
        $paymentTransactionClass
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->customerUserProvider = $customerUserProvider;
        $this->paymentTransactionClass = $paymentTransactionClass;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param object $object
     * @param array $criteria
     * @param array $orderBy
     * @return PaymentTransaction|null
     */
    public function getPaymentTransaction($object, array $criteria = [], array $orderBy = [])
    {
        $paymentTransactions = $this->getPaymentTransactions($object, $criteria, $orderBy, 1);

        return reset($paymentTransactions);
    }

    /**
     * @param object $object
     * @param array $criteria
     * @param array $orderBy
     * @param int $limit
     * @param int $offset
     * @return PaymentTransaction[]
     */
    public function getPaymentTransactions(
        $object,
        array $criteria = [],
        array $orderBy = [],
        $limit = null,
        $offset = null
    ) {
        $className = $this->doctrineHelper->getEntityClass($object);
        $identifier = $this->doctrineHelper->getSingleEntityIdentifier($object);

        return $this->doctrineHelper->getEntityRepository($this->paymentTransactionClass)->findBy(
            array_merge(
                $criteria,
                [
                    'entityClass' => $className,
                    'entityIdentifier' => $identifier,
                ]
            ),
            array_merge(['id' => Criteria::DESC], $orderBy),
            $limit,
            $offset
        );
    }

    /**
     * @param object $object
     * @return array
     */
    public function getPaymentMethods($object)
    {
        $className = $this->doctrineHelper->getEntityClass($object);
        $identifier = $this->doctrineHelper->getSingleEntityIdentifier($object);
        /** @var PaymentTransactionRepository $repository */
        $repository = $this->doctrineHelper->getEntityRepository(PaymentTransaction::class);
        $methods = $repository->getPaymentMethods($className, [$identifier]);

        return isset($methods[$identifier]) ? $methods[$identifier] : [];
    }

    /**
     * @param object $object
     * @param float $amount
     * @param string $currency
     * @param string|null $paymentMethod
     * @return null|PaymentTransaction
     */
    public function getActiveAuthorizePaymentTransaction($object, $amount, $currency, $paymentMethod = null)
    {
        $criteria = [
            'active' => true,
            'successful' => true,
            'amount' => (string)round($amount, 2),
            'currency' => $currency,
            'action' => PaymentMethodInterface::AUTHORIZE,
        ];

        if ($paymentMethod) {
            $criteria['paymentMethod'] = (string)$paymentMethod;
        }

        return $this->getPaymentTransaction($object, $criteria);
    }

    /**
     * @param string $paymentMethod
     * @return PaymentTransaction|null
     */
    public function getActiveValidatePaymentTransaction($paymentMethod)
    {
        $customerUser = $this->customerUserProvider->getLoggedUser(true);
        if (!$customerUser) {
            return null;
        }

        return $this->doctrineHelper->getEntityRepository($this->paymentTransactionClass)->findOneBy(
            [
                'active' => true,
                'successful' => true,
                'action' => PaymentMethodInterface::VALIDATE,
                'paymentMethod' => (string)$paymentMethod,
                'frontendOwner' => $customerUser,
            ],
            ['id' => Criteria::DESC]
        );
    }

    /**
     * @param string $paymentMethod
     * @param string $type
     * @param object $object
     * @return PaymentTransaction
     */
    public function createPaymentTransaction($paymentMethod, $type, $object)
    {
        $className = $this->doctrineHelper->getEntityClass($object);
        $identifier = $this->doctrineHelper->getSingleEntityIdentifier($object);

        $paymentTransaction = $this->createEmptyPaymentTransaction()
            ->setPaymentMethod($paymentMethod)
            ->setAction($type)
            ->setEntityClass($className)
            ->setEntityIdentifier($identifier)
            ->setFrontendOwner($this->customerUserProvider->getLoggedUser(true));

        return $paymentTransaction;
    }

    /**
     * @param string $action
     * @param PaymentTransaction $parentPaymentTransaction
     *
     * @return PaymentTransaction
     */
    public function createPaymentTransactionByParentTransaction($action, PaymentTransaction $parentPaymentTransaction)
    {
        $paymentTransaction = $this->createEmptyPaymentTransaction()
            ->setAction($action)
            ->setPaymentMethod($parentPaymentTransaction->getPaymentMethod())
            ->setEntityClass($parentPaymentTransaction->getEntityClass())
            ->setEntityIdentifier($parentPaymentTransaction->getEntityIdentifier())
            ->setAmount($parentPaymentTransaction->getAmount())
            ->setCurrency($parentPaymentTransaction->getCurrency())
            ->setFrontendOwner($this->customerUserProvider->getLoggedUser(true))
            ->setSourcePaymentTransaction($parentPaymentTransaction);

        return $paymentTransaction;
    }

    /**
     * @param PaymentTransaction $paymentTransaction
     */
    public function savePaymentTransaction(PaymentTransaction $paymentTransaction)
    {
        $em = $this->doctrineHelper->getEntityManager($paymentTransaction);
        try {
            $em->transactional(
                function (EntityManagerInterface $em) use ($paymentTransaction) {
                    if (!$paymentTransaction->getId()) {
                        $em->persist($paymentTransaction);
                    }
                }
            );

            $event = new TransactionCompleteEvent($paymentTransaction);
            $this->dispatcher->dispatch(TransactionCompleteEvent::NAME, $event);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error($e->getMessage(), $e->getTrace());
            }
        } catch (\Error $e) {
            if ($this->logger) {
                $this->logger->error($e->getMessage(), $e->getTrace());
            }
        }
    }

    /**
     * @return PaymentTransaction
     */
    private function createEmptyPaymentTransaction()
    {
        return new $this->paymentTransactionClass();
    }
}
