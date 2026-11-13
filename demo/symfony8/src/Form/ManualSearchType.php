<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * REQ-DEMO-015: a FormKit form (profile `demo`). Demo manual search box (GET `?q=`): read-only, so no CSRF token;
 * empty block prefix keeps the query flat.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @extends AbstractType<null>
 */
#[FormKitConfig('demo')]
final class ManualSearchType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, 'q', SearchType::class, [
            'label' => 'demo.search.label',
            'required' => false,
            'data' => $options['query'],
            'help' => false,
            'attr' => ['placeholder' => 'demo.search.placeholder', 'data-testid' => 'manual-search-q'],
        ]);
        $this->addWithDefaults($builder, 'submit', SubmitType::class, [
            'label' => 'demo.search.submit',
            'attr' => ['class' => 'nowo-ui-btn nowo-ui-btn--primary'],
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
