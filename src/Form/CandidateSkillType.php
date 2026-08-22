<?php

declare(strict_types=1);

namespace App\Form;

use App\Candidate\Application\DTO\CandidateSkillData;
use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CandidateSkillType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Compétence',
                'attr' => ['placeholder' => 'Ex. Laravel', 'autocomplete' => 'off'],
            ])
            ->add('level', ChoiceType::class, [
                'label' => 'Niveau',
                'choices' => $this->levelChoices(),
                'choice_value' => static fn (SkillLevel $level): string => $level->value,
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => $this->categoryChoices(),
                'choice_value' => static fn (SkillCategory $category): string => $category->value,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CandidateSkillData::class]);
    }

    /** @return array<string, SkillLevel> */
    private function levelChoices(): array
    {
        $choices = [];
        foreach (SkillLevel::cases() as $level) {
            $choices[$level->label()] = $level;
        }

        return $choices;
    }

    /** @return array<string, SkillCategory> */
    private function categoryChoices(): array
    {
        $choices = [];
        foreach (SkillCategory::cases() as $category) {
            $choices[$category->label()] = $category;
        }

        return $choices;
    }
}
