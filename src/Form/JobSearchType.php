<?php

declare(strict_types=1);

namespace App\Form;

use App\Job\DTO\JobSearchData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class JobSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Intitulé ou mots-clés',
                'attr' => ['placeholder' => 'Ex. Développeur PHP backend'],
            ])
            ->add('add', SubmitType::class, ['label' => 'Ajouter et rechercher']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JobSearchData::class]);
    }
}
