<?php

declare(strict_types=1);

namespace App\Form;

use App\Job\DTO\ManualJobOfferData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ManualJobOfferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', UrlType::class, [
                'label' => 'URL de l’annonce',
                'attr' => ['placeholder' => 'https://fr.indeed.com/viewjob?jk=...'],
            ])
            ->add('title', TextType::class, [
                'label' => 'Intitulé du poste',
                'attr' => ['placeholder' => 'Ex. Développeur Symfony'],
            ])
            ->add('company', TextType::class, [
                'label' => 'Entreprise',
                'required' => false,
            ])
            ->add('location', TextType::class, [
                'label' => 'Localisation',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Contenu complet de l’annonce',
                'attr' => [
                    'rows' => 14,
                    'placeholder' => 'Collez ici la description, les missions, le profil recherché et les conditions du poste.',
                ],
            ])
            ->add('import', SubmitType::class, ['label' => 'Importer et analyser']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ManualJobOfferData::class]);
    }
}
