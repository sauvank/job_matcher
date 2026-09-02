<?php

declare(strict_types=1);

namespace App\Form;

use App\Candidate\Application\DTO\CandidateProfileDetailsData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CandidateProfileDetailsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Intitulé de poste ciblé',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : Développeur Symfony, Lead Developer, Architecte Cloud...',
                    'autocomplete' => 'organization-title',
                ],
                'help' => 'Métier ou spécialité recherchée pour vos opportunités.',
            ])
            ->add('location', TextType::class, [
                'label' => 'Zone géographique / Localisation',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : Lyon, Paris, Île-de-France, Bordeaux, France entière...',
                    'autocomplete' => 'address-level2',
                ],
                'help' => 'Ville, département, région ou zone de mobilité ciblée.',
            ])
            ->add('yearsOfExperience', IntegerType::class, [
                'label' => 'Années d’expérience globale',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : 5',
                    'min' => 0,
                    'max' => 80,
                ],
                'help' => 'Nombre total d’années d’expérience professionnelle.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CandidateProfileDetailsData::class,
        ]);
    }
}
