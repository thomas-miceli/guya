<?php

namespace App\Repository;

use App\Entity\GitRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method GitRepositoryRepository|null find($id, $lockMode = null, $lockVersion = null)
 * @method GitRepositoryRepository|null findOneBy(array $criteria, array $orderBy = null)
 * @method GitRepositoryRepository[]    findAll()
 * @method GitRepositoryRepository[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GitRepositoryRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, GitRepository::class);
    }

    // /**
    //  * @return Repository[] Returns an array of Repository objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Repository
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
