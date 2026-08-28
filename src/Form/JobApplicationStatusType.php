<?php

declare(strict_types=1);

namespace App\Form;

use App\Matching\DTO\JobApplicationStatusData;
use App\Matching\Enum\JobApplicationStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class JobApplicationStatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', EnumType::class, [
                'class' => JobApplicationStatus::class,
                'choice_label' => static fn (JobApplicationStatus $status): string => $status->icon().' '.$status->label(),
                'label' => 'Statut de candidature',
            ])
            ->add('reason', TextareaType::class, [
                'required' => false,
                'label' => 'Raison ou note (optionnel)',
                'attr' => [
                    'placeholder' => 'Ex : Ne correspond pas à mes critères salariaux, contact RH pris, entretien technique prévu le 12...',
                    'rows' => 3,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JobApplicationStatusData::class]);
    }
}
