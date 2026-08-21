<?php

declare(strict_types=1);

namespace App\Form;

use App\Job\DTO\JobSourceData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class JobSourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom de la source'])
            ->add('url', TextType::class, [
                'label' => 'URL de recherche HelloWork',
                'help' => 'Collez l’URL complète obtenue après avoir lancé une recherche sur HelloWork.',
            ])
            ->add('save', SubmitType::class, ['label' => 'Ajouter et synchroniser']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JobSourceData::class]);
    }
}
