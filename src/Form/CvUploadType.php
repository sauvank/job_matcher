<?php

declare(strict_types=1);

namespace App\Form;

use App\Candidate\Application\DTO\CvUploadData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CvUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Votre CV (PDF ou DOCX)',
                'help' => '10 Mo maximum. Le fichier est conservé hors du dossier public.',
            ])
            ->add('submit', SubmitType::class, ['label' => 'Déposer et analyser']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CvUploadData::class]);
    }
}
