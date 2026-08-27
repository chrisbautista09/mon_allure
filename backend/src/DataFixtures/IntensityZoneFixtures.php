<?php

namespace App\DataFixtures;

use App\Entity\IntensityZone;
use App\Repository\IntensityZoneRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class IntensityZoneFixtures extends Fixture
{
    /**
     * @var array<string, array{vmaMin: float, vmaMax: float, fcmMin: float, fcmMax: float}>
     */
    public const DEFAULTS = [
        'Z1' => ['vmaMin' => 0.50, 'vmaMax' => 0.65, 'fcmMin' => 50.0, 'fcmMax' => 60.0],
        'Z2' => ['vmaMin' => 0.65, 'vmaMax' => 0.75, 'fcmMin' => 60.0, 'fcmMax' => 70.0],
        'Z3' => ['vmaMin' => 0.75, 'vmaMax' => 0.85, 'fcmMin' => 70.0, 'fcmMax' => 80.0],
        'Z4' => ['vmaMin' => 0.85, 'vmaMax' => 0.95, 'fcmMin' => 80.0, 'fcmMax' => 90.0],
        'Z5' => ['vmaMin' => 0.95, 'vmaMax' => 1.05, 'fcmMin' => 90.0, 'fcmMax' => 100.0],
    ];

    public function __construct(private readonly IntensityZoneRepository $repository)
    {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::DEFAULTS as $name => $values) {
            if ($this->repository->findOneBy(['name' => $name]) instanceof IntensityZone) {
                continue;
            }

            $manager->persist((new IntensityZone())
                ->setName($name)
                ->setVmaCoefMin($values['vmaMin'])
                ->setVmaCoefMax($values['vmaMax'])
                ->setFcmPercentMin($values['fcmMin'])
                ->setFcmPercentMax($values['fcmMax']));
        }

        $manager->flush();
    }
}
