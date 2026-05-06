<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    //custom method to get stats with orders items count
    public function getFilteredStats(?int $pharmacyId = null, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->select('
                p.id AS pharmacy_id,
                o.status AS status,
                COUNT(o.id) AS total_orders,
                SUM(o.total_amount) AS total_revenue
            ')
            ->leftJoin('o.pharmacy', 'p');

        if ($pharmacyId !== null) {
            $qb->andWhere('p.id = :pharmacyId')
            ->setParameter('pharmacyId', $pharmacyId);
        }

        if ($startDate !== null) {
            $qb->andWhere('o.created_date >= :startDate')
            ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('o.created_date <= :endDate')
            ->setParameter('endDate', $endDate);
        }

        if ($status !== null) {
            $qb->andWhere('o.status = :status')
            ->setParameter('status', $status);
        }

        $qb->groupBy('pharmacy_id, status')
        ->orderBy('pharmacy_id', 'ASC');

        return $qb->getQuery()->getResult();
    }


    //custom method to get filtered stats with orders items count
    public function getFilteredStats2(?int $pharmacyId = null, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->select('
                p.id AS pharmacy_id,
                o.status AS status,
                COUNT(o.id) AS total_orders,
                SUM(o.total_amount) AS total_revenue,
                COUNT(oi.id) AS total_items
            ')
            ->leftJoin('o.pharmacy', 'p')
            ->leftJoin('o.orderItems', 'oi');

        if ($pharmacyId !== null) {
            $qb->andWhere('p.id = :pharmacyId')
            ->setParameter('pharmacyId', $pharmacyId);
        }

        if ($startDate !== null) {
            $qb->andWhere('o.created_date >= :startDate')
            ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('o.created_date <= :endDate')
            ->setParameter('endDate', $endDate);
        }

        if ($status !== null) {
            $qb->andWhere('o.status = :status')
            ->setParameter('status', $status);
        }

        $qb->groupBy('pharmacy_id, status')
        ->orderBy('pharmacy_id', 'ASC');

        return $qb->getQuery()->getResult();
    }


    //    /**
    //     * @return Order[] Returns an array of Order objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Order
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
