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
class GitRepositoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GitRepository::class);
    }

    /**
     * @param $see_as_user
     * @return GitRepository[] Returns an array of all own GitRepository objects and all those where the user collaborate
     */
    public function findByAllAsUser($see_as_user)
    {
        return $this->createQueryBuilder('r')
            ->setParameter('id', $see_as_user)
            ->where(':id MEMBER OF r.collaborators')
            ->orWhere('r.user = :id')
            ->orWhere('r.private = 0')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param $value
     * @return GitRepository[] Returns an array of all own GitRepository objects and all those where the user collaborate
     */
    public function findByUserAsUser($see_as_user, $repos_of)
    {
        return $this->createQueryBuilder('r')
            ->setParameter('id', $see_as_user)
            ->setParameter('idof', $repos_of)
            ->where(':id MEMBER OF r.collaborators')
            ->orWhere('r.private = 0')
            ->andWhere('r.user = :idof')
            ->getQuery()
            ->getResult();
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
