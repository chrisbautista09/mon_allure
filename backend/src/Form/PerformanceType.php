<?php

namespace App\Form;

use App\Entity\Performance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class PerformanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('durationSec', IntegerType::class, [
                'label' => 'Temps réalisé (secondes)',
                'attr' => ['min' => 1, 'inputmode' => 'numeric'],
                'help' => 'Indiquez la durée totale de la séance en secondes.',
            ])
            ->add('distanceKm', NumberType::class, [
                'label' => 'Distance réalisée (km)',
                'html5' => true,
                'scale' => 2,
                'attr' => ['min' => '0.01', 'step' => '0.01', 'inputmode' => 'decimal'],
            ])
            ->add('elevationDPlus', IntegerType::class, [
                'label' => 'Dénivelé positif réalisé (m)',
                'required' => false,
                'attr' => ['min' => 0, 'inputmode' => 'numeric'],
            ])
            ->add('terrainType', ChoiceType::class, [
                'label' => 'Terrain pratiqué',
                'mapped' => false,
                'placeholder' => 'Choisir un terrain',
                'choices' => [
                    'Route' => 'road',
                    'Trail' => 'trail',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Choisissez le terrain pratiqué.'),
                    new Assert\Choice(
                        choices: ['road', 'trail'],
                        message: 'Le terrain sélectionné est invalide.',
                    ),
                ],
            ])
            ->add('avgHr', IntegerType::class, [
                'label' => 'Fréquence cardiaque moyenne (bpm)',
                'required' => false,
                'attr' => ['min' => 30, 'max' => 230, 'inputmode' => 'numeric'],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Commentaire sur la séance',
                'required' => false,
                'attr' => ['maxlength' => 1000, 'rows' => 5],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Performance::class,
            'csrf_protection' => true,
        ]);
    }
}
