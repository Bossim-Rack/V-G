<?php

namespace App\Repository;

use App\Entity\Horraires;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Horraires>
 *
 * @method Horraires|null find($id, $lockMode = null, $lockVersion = null)
 * @method Horraires|null findOneBy(array $criteria, array $orderBy = null)
 * @method Horraires[]    findAll()
 * @method Horraires[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HorrairesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Horraires::class);
    }

//    /**
//     * @return Horraires[] Returns an array of Horraires objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('h')
//            ->andWhere('h.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('h.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Horraires
//    {
//        return $this->createQueryBuilder('h')
//            ->andWhere('h.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
