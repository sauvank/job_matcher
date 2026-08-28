<?php

declare(strict_types=1);

namespace App\Form;

use App\Security\DTO\AccountAlertSettingsData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AccountAlertSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('alertEmailEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Recevoir l’alerte email quotidienne pour les nouvelles offres',
            ])
            ->add('alertScoreThreshold', IntegerType::class, [
                'required' => true,
                'label' => 'Seuil minimum de compatibilité (%)',
                'attr' => [
                    'min' => 10,
                    'max' => 100,
                    'step' => 5,
                ],
                'help' => 'Seules les offres atteignant au moins ce score de compatibilité (IA ou critères) vous seront envoyées. Par défaut : 70%.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AccountAlertSettingsData::class]);
    }
}
