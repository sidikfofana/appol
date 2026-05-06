<?php
namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;
use App\Entity\RefreshToken;

class RefreshTokenRepository extends ServiceEntityRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findInvalid($datetime = null): array
    {
        $datetime = $datetime ?? new \DateTime();

        return $this->createQueryBuilder('rt')
            ->where('rt.valid < :now')
            ->setParameter('now', $datetime)
            ->getQuery()
            ->getResult();
    }
}
