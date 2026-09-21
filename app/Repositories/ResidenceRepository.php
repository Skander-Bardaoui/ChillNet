<?php

namespace App\Repositories;

use Doctrine\ORM\EntityRepository;

class ResidenceRepository extends EntityRepository
{
    /** @return \App\Entities\Residence[] */
    public function findByQuartier(int $quartierId): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.quartier', 'q')
            ->addSelect('q')
            ->where('q.id = :quartierId')
            ->setParameter('quartierId', $quartierId)
            ->orderBy('r.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return \App\Entities\Residence[] */
    public function allWithQuartier(): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.quartier', 'q')
            ->addSelect('q')
            ->orderBy('q.nom', 'ASC')
            ->addOrderBy('r.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
