<?php

declare(strict_types=1);

namespace App\Form;

use App\Candidate\Application\DTO\CandidateProfileDetailsData;
use App\Candidate\Enum\ContractType;
use App\Candidate\Enum\RemotePolicy;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
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
            ->add('preferredRemotePolicy', EnumType::class, [
                'class' => RemotePolicy::class,
                'label' => 'Politique de télétravail souhaitée',
                'choice_label' => static fn (RemotePolicy $policy): string => match ($policy) {
                    RemotePolicy::UNKNOWN => 'Indifférent / Tous les modes',
                    RemotePolicy::REMOTE => '100% Full Remote (Télétravail complet)',
                    RemotePolicy::HYBRID => 'Hybride (Télétravail partiel)',
                    RemotePolicy::ON_SITE => 'Sur site uniquement',
                    RemotePolicy::FLEXIBLE => 'Flexible',
                },
                'required' => false,
                'help' => 'Mode d’organisation du travail préféré.',
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
            ])
            ->add('minimumSalary', IntegerType::class, [
                'label' => 'Salaire annuel brut minimum (€ / an)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : 50000',
                    'min' => 0,
                    'step' => 1000,
                ],
                'help' => 'Rémunération annuelle minimale souhaitée (CDI / CDD).',
            ])
            ->add('minimumDailyRate', IntegerType::class, [
                'label' => 'TJM minimum (€ / jour)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : 550',
                    'min' => 0,
                    'step' => 25,
                ],
                'help' => 'Taux Journalier Moyen minimum souhaité pour les missions Freelance.',
            ])
            ->add('preferredContractTypes', ChoiceType::class, [
                'label' => 'Type(s) de contrat recherché(s)',
                'choices' => ContractType::choices(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'Cochez les types de contrat qui vous correspondent (CDI, Freelance, CDD, Alternance, Stage).',
            ])
            ->add('excludedCompaniesText', TextType::class, [
                'label' => 'Entreprises ou ESN à exclure (Blacklist)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : Capgemini, Alten, Sopra Steria, AncienneBoite...',
                ],
                'help' => 'Séparez les noms d’entreprises par des virgules. Les offres de ces entreprises seront masquées.',
            ])
            ->add('excludedKeywordsText', TextType::class, [
                'label' => 'Mots-clés / Technologies repoussoirs à exclure',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : WordPress, Prestashop, Cobol, Windev, Legacy...',
                ],
                'help' => 'Séparez les termes par des virgules. Les offres contenant ces mots-clés seront masquées.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CandidateProfileDetailsData::class,
        ]);
    }
}
