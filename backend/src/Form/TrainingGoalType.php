<?php

namespace App\Form;

use App\Dto\TrainingPlanDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TrainingGoalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('targetType', ChoiceType::class, [
                'label' => 'Type d’objectif',
                'placeholder' => 'Choisir un objectif',
                'choices' => [
                    'Distance' => 'distance',
                    'Durée' => 'time',
                ],
            ])
            ->add('targetValue', NumberType::class, [
                'label' => 'Valeur cible',
                'html5' => true,
                'scale' => 2,
                'attr' => ['min' => '0.01', 'step' => '0.01', 'inputmode' => 'decimal'],
            ])
            ->add('targetUnit', ChoiceType::class, [
                'label' => 'Unité',
                'placeholder' => 'Choisir une unité',
                'choices' => [
                    'Kilomètres (km)' => 'km',
                    'Mètres (m)' => 'm',
                    'Minutes (min)' => 'min',
                    'Secondes (s)' => 's',
                ],
            ])
            ->add('terrainType', ChoiceType::class, [
                'label' => 'Terrain',
                'placeholder' => 'Choisir un terrain',
                'choices' => [
                    'Route' => 'road',
                    'Chemin' => 'path',
                    'Trail' => 'trail',
                ],
            ])
            ->add('elevationTargetDPlus', IntegerType::class, [
                'label' => 'Dénivelé positif visé (m)',
                'required' => false,
                'attr' => ['min' => 0, 'inputmode' => 'numeric'],
                'help' => 'Facultatif, laissez vide si votre objectif ne comporte pas de dénivelé.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TrainingPlanDTO::class,
        ]);
    }
}
