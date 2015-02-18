<?php
/**
 * This file is part of CSBill package.
 *
 * (c) 2013-2014 Pierre du Plessis <info@customscripts.co.za>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace CSBill\PaymentBundle\Repository;

use CSBill\ClientBundle\Entity\Client;
use CSBill\InvoiceBundle\Entity\Invoice;
use CSBill\PaymentBundle\Entity\Status;
use Doctrine\ORM\EntityRepository;

class PaymentRepository extends EntityRepository
{
    /**
     * Gets the total income that was received
     *
     * @return float
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getTotalIncome()
    {
        $qb = $this->createQueryBuilder('p');

        $qb->select('SUM(p.totalAmount)')
            ->join('p.status', 's')
            ->where('s.name = :status')
            ->setParameter('status', Status::STATUS_CAPTURED);

        $query = $qb->getQuery();

        $result = $query->getSingleResult();

        return $result[1];
    }

    /**
     * Returns an array of all the payments for an invoice
     *
     * @param Invoice $invoice
     * @param string  $orderField
     * @param string  $sort
     *
     * @return array
     */
    public function getPaymentsForInvoice(Invoice $invoice, $orderField = null, $sort = 'DESC')
    {
        $queryBuilder = $this->getPaymentQueryBuilder($orderField, $sort);

        $queryBuilder
            ->where('p.invoice = :invoice')
            ->setParameter('invoice', $invoice);

        $query = $queryBuilder->getQuery();

        return $query->getArrayResult();
    }

    /**
     * Returns an array of all the payments for a client
     *
     * @param Client $client
     * @param string $orderField
     * @param string $sort
     *
     * @return array
     */
    public function getPaymentsForClient(Client $client, $orderField = null, $sort = 'DESC')
    {
        $queryBuilder = $this->getPaymentQueryBuilder($orderField, $sort);

        $queryBuilder
            ->where('p.client = :client')
            ->setParameter('client', $client);

        $query = $queryBuilder->getQuery();

        return $query->getArrayResult();
    }

    /**
     * @param string $orderField
     * @param string $sort
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function getPaymentQueryBuilder($orderField = null, $sort = 'DESC')
    {
        if (null === $orderField) {
            $orderField = 'p.created';
        }

        $queryBuilder = $this->createQueryBuilder('p');

        $queryBuilder->select(
            'p.id',
            'p.totalAmount',
            'p.currencyCode',
            'p.created',
            'p.completed',
            'i.id as invoice',
            'm.name as method',
            's.name as status',
            's.label as status_label',
            'p.message'
        )
            ->join('p.method', 'm')
            ->join('p.status', 's')
            ->join('p.invoice', 'i')
            ->orderBy($orderField, $sort);

        return $queryBuilder;
    }

    /**
     * Gets the most recent created payments
     *
     * @param int $limit
     *
     * @return array
     */
    public function getRecentPayments($limit = 5)
    {
        $qb = $this->getPaymentQueryBuilder();

        $qb->addSelect(
            'c.name as client',
            'c.id as client_id'
        )
            ->join('p.client', 'c')
            ->setMaxResults($limit);

        $query = $qb->getQuery();

        return $query->getArrayResult();
    }

    /**
     * @param \DateTime $timestamp
     *
     * @return array
     */
    public function getPaymentsList(\DateTime $timestamp = null)
    {
        $queryBuilder = $this->createQueryBuilder('p');

        $queryBuilder->select('p.totalAmount', 'p.created')
            ->join('p.method', 'm')
            ->join('p.status', 's')
            ->where('p.created >= :date')
            ->setParameter('date', $timestamp)
            ->groupBy('p.created')
            ->orderBy('p.created', 'ASC');

        $query = $queryBuilder->getQuery();

        $payments = $this->formatDate($query);

        $results = array();

        foreach ($payments as $date => $amount) {
            $results[] = array(strtotime($date) * 1000, $amount);
        }

        return $results;
    }

    /**
     * @return array
     */
    public function getPaymentsByMonth()
    {
        $queryBuilder = $this->createQueryBuilder('p');

        $queryBuilder->select(
            'p.totalAmount',
            'p.created'
        )
            ->join('p.method', 'm')
            ->join('p.status', 's')
            ->where('p.created >= :date')
            ->setParameter('date', new \DateTime('-1 Year'))
            ->groupBy('p.created')
            ->orderBy('p.created', 'ASC');

        $query = $queryBuilder->getQuery();

        $payments = $this->formatDate($query, 'F Y');

        return $payments;
    }

    /**
     * @param \Doctrine\ORM\Query $query
     * @param string              $dateFormat
     *
     * @return array
     */
    private function formatDate($query, $dateFormat = 'Y-m-d')
    {
        $payments = array();

        foreach ($query->getArrayResult() as $result) {
            /** @var \DateTime $created */
            $created = $result['created'];
            $date = $created->format($dateFormat);
            if (!isset($payments[$date])) {
                $payments[$date] = 0;
            }
            $payments[$date] += $result['totalAmount'];
        }

        return $payments;
    }
}
