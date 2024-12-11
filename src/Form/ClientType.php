<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Responsable;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code Client',
            ])
            ->add('raisonsociale', TextType::class, [
                'label' => 'Raison Sociale',
            ])
            ->add('google_drive_webtask', TextType::class, [
                'label' => 'Google Drive Webtask',
            ])

            ->add('webtaskOuvertureContact', CheckboxType::class, [
                'label' => 'Webtask Ouverture Contact',
                'required' => false,
            ])

            // Utilisation d'EntityType pour le responsable
            ->add('responsable', EntityType::class, [
                'class' => Responsable::class,
                'choice_label' => function (Responsable $responsable) {
                    return $responsable->getPrenom() . ' ' . $responsable->getNom();
                },
                'label' => 'Responsable',
                'placeholder' => 'Choisissez un responsable',
                'required' => false,
                'query_builder' => function ($repo) {
                    return $repo->createQueryBuilder('r')
                        ->orderBy('r.nom', 'ASC'); // Trier par nom du responsable
                },
            ])
            ->add('pilote', TextType::class, [
                'label' => 'Pilote',
                'required' => false,
            ])
            // Logo avec FileType
            ->add('logo', FileType::class, [
                'label' => 'Logo',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}
