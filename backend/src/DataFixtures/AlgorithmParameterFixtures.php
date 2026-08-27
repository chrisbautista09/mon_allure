<?php

namespace App\DataFixtures;

use App\Entity\AlgorithmParameter;
use App\Repository\AlgorithmParameterRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AlgorithmParameterFixtures extends Fixture
{
    /**
     * @var array<string, array{value: float, description: string}>
     */
    public const DEFAULTS = [
        AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT => [
            'value' => 10.0,
            'description' => 'Progression maximale de la charge entre deux semaines, en pourcentage.',
        ],
        AlgorithmParameter::KEY_RECOVERY_WEEK_FREQUENCY => [
            'value' => 4.0,
            'description' => 'Fréquence des semaines de récupération.',
        ],
        AlgorithmParameter::KEY_MAX_SESSIONS_DISCOVERY => [
            'value' => 2.0,
            'description' => 'Nombre maximal de séances hebdomadaires du pôle Discovery.',
        ],
        AlgorithmParameter::KEY_MAX_SESSIONS_INTERMEDIATE => [
            'value' => 3.0,
            'description' => 'Nombre maximal de séances hebdomadaires du pôle Intermediate.',
        ],
        AlgorithmParameter::KEY_MAX_SESSIONS_PERFORMANCE => [
            'value' => 5.0,
            'description' => 'Nombre maximal de séances hebdomadaires du pôle Performance.',
        ],
        AlgorithmParameter::KEY_LONG_RUN_RATIO_MAX => [
            'value' => 0.30,
            'description' => 'Part maximale de la sortie longue dans la charge hebdomadaire.',
        ],
        AlgorithmParameter::KEY_COEF_ENDURANCE => [
            'value' => 0.70,
            'description' => 'Coefficient VMA de référence pour une séance d’endurance.',
        ],
        AlgorithmParameter::KEY_COEF_ACTIVE => [
            'value' => 0.80,
            'description' => 'Coefficient VMA de référence pour une séance active.',
        ],
        AlgorithmParameter::KEY_COEF_THRESHOLD => [
            'value' => 0.90,
            'description' => 'Coefficient VMA de référence pour une séance au seuil.',
        ],
        AlgorithmParameter::KEY_COEF_VMA => [
            'value' => 1.0,
            'description' => 'Coefficient VMA de référence pour une séance VMA.',
        ],
        AlgorithmParameter::KEY_DEFAULT_PLAN_MIN_WEEKS => [
            'value' => 8.0,
            'description' => 'Durée minimale par défaut d’un plan, en semaines.',
        ],
        AlgorithmParameter::KEY_DEFAULT_PLAN_MAX_WEEKS => [
            'value' => 18.0,
            'description' => 'Durée maximale par défaut d’un plan, en semaines.',
        ],
        AlgorithmParameter::KEY_MISSED_SESSION_TOLERANCE => [
            'value' => 2.0,
            'description' => 'Nombre de séances manquées toléré avant recalibrage.',
        ],
        AlgorithmParameter::KEY_SUCCESS_VALIDATION_RATE => [
            'value' => 80.0,
            'description' => 'Taux minimal de réussite validant une progression, en pourcentage.',
        ],
    ];

    public function __construct(private readonly AlgorithmParameterRepository $repository)
    {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::DEFAULTS as $key => $configuration) {
            if ($this->repository->findOneBy(['parameterKey' => $key]) instanceof AlgorithmParameter) {
                continue;
            }

            $manager->persist((new AlgorithmParameter())
                ->setParameterKey($key)
                ->setParameterValue($configuration['value'])
                ->setDescription($configuration['description']));
        }

        $manager->flush();
    }
}
