<?php

namespace App\Form;

use App\Entity\Profile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['autocomplete' => 'given-name'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => ['autocomplete' => 'family-name'],
            ])
            ->add('age', IntegerType::class, [
                'label' => 'Âge',
                'attr' => ['inputmode' => 'numeric'],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville d’entraînement',
                'required' => false,
                'attr' => ['autocomplete' => 'address-level2'],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => ['autocomplete' => 'postal-code'],
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays',
                'required' => false,
                'attr' => ['autocomplete' => 'country-name'],
            ])
            ->add('vma', NumberType::class, [
                'label' => 'VMA (km/h)',
                'required' => false,
                'scale' => 1,
                'html5' => true,
                'attr' => ['step' => '0.1', 'inputmode' => 'decimal'],
                'help' => 'Votre vitesse maximale aérobie, si vous la connaissez.',
            ])
            ->add('vo2max', NumberType::class, [
                'label' => 'VO₂ max (ml/min/kg)',
                'required' => false,
                'scale' => 1,
                'html5' => true,
                'attr' => ['step' => '0.1', 'inputmode' => 'decimal'],
            ])
            ->add('fcm', IntegerType::class, [
                'label' => 'Fréquence cardiaque maximale (bpm)',
                'required' => false,
                'attr' => ['inputmode' => 'numeric'],
            ])
            ->add('fcr', IntegerType::class, [
                'label' => 'Fréquence cardiaque au repos (bpm)',
                'required' => false,
                'attr' => ['inputmode' => 'numeric'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Profile::class,
        ]);
    }
}
