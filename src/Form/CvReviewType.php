<?php

declare(strict_types=1);

namespace App\Form;

use App\Candidate\Application\DTO\AnalyzedSkill;
use App\Candidate\Application\DTO\CvAnalysisResult;
use App\Candidate\Application\DTO\CvReviewData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CvReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var CvAnalysisResult $analysis */
        $analysis = $options['analysis'];
        $skillNames = array_map(static fn (AnalyzedSkill $skill): string => $skill->name, $analysis->skills);

        $builder
            ->add('title', TextType::class, ['label' => 'Titre professionnel'])
            ->add('location', TextType::class, ['label' => 'Localisation'])
            ->add('yearsOfExperience', IntegerType::class, ['label' => 'Années d\'expérience', 'required' => false])
            ->add('selectedSkills', ChoiceType::class, [
                'label' => 'Compétences à conserver',
                'choices' => array_combine($skillNames, $skillNames),
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('apply', SubmitType::class, ['label' => 'Valider et appliquer au profil']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CvReviewData::class]);
        $resolver->setRequired('analysis');
        $resolver->setAllowedTypes('analysis', CvAnalysisResult::class);
    }
}
