<?php

namespace App\Repository;

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /** @return list<Comment> */
    public function findLatestForAdministration(int $limit = 100): array
    {
        return $this->createQueryBuilder('comment')
            ->addSelect('user')
            ->innerJoin('comment.user', 'user')
            ->orderBy('comment.createdAt', 'DESC')
            ->addOrderBy('comment.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
