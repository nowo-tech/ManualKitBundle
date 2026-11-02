<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demo manual search box (GET `?q=`): read-only, so no CSRF token; empty block prefix keeps the query flat.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @extends AbstractType<null>
 */
final class ManualSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('q', SearchType::class, [
                'label' => 'demo.search.label',
                'required' => false,
                'data' => $options['query'],
                'attr' => ['placeholder' => 'demo.search.placeholder', 'data-testid' => 'manual-search-q'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'demo.search.submit',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['method' => 'GET', 'csrf_protection' => false, 'query' => '']);
        $resolver->setAllowedTypes('query', 'string');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
