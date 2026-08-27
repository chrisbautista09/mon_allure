<?php

namespace App\Repository;

use App\Entity\AlgorithmParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlgorithmParameter>
 */
class AlgorithmParameterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlgorithmParameter::class);
    }

    /**
     * Retourne l’unique configuration courante, indexée par clé.
     *
     * @return array<string, AlgorithmParameter>
     */
    public function findCurrent(): array
    {
        /** @var array<string, AlgorithmParameter> $parameters */
        $parameters = $this->createQueryBuilder('parameter', 'parameter.parameterKey')
            ->andWhere('parameter.parameterKey IN (:supportedKeys)')
            ->setParameter('supportedKeys', AlgorithmParameter::SUPPORTED_KEYS)
            ->getQuery()
            ->getResult();

        $orderedConfiguration = [];
        foreach (AlgorithmParameter::SUPPORTED_KEYS as $key) {
            if (isset($parameters[$key])) {
                $orderedConfiguration[$key] = $parameters[$key];
            }
        }

        return $orderedConfiguration;
    }

    /**
     * Met à jour plusieurs paramètres en une transaction et un seul flush.
     *
     * @param array<string, float|int> $values
     */
    public function updateParameters(array $values): void
    {
        if ($values === []) {
            return;
        }

        $unknownKeys = array_diff(array_keys($values), AlgorithmParameter::SUPPORTED_KEYS);
        if ($unknownKeys !== []) {
            throw new \InvalidArgumentException(sprintf(
                'Paramètre algorithmique inconnu : %s.',
                implode(', ', $unknownKeys),
            ));
        }

        $this->getEntityManager()->wrapInTransaction(function () use ($values): void {
            $parameters = $this->findCurrent();

            foreach ($values as $key => $value) {
                if (!isset($parameters[$key])) {
                    throw new \LogicException(sprintf('Le paramètre algorithmique "%s" est manquant.', $key));
                }

                $parameters[$key]->updateValue((float) $value);
            }

            $this->getEntityManager()->flush();
        });
    }
}
