<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Form\Extension;

use Altmenorg\SyliusGoogleShoppingPlugin\Model\GoogleProductCategoryAwareInterface;
use Sylius\Bundle\TaxonomyBundle\Form\Type\TaxonType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Length;

/**
 * "Google product category" field of the taxon form (admin), added only when the taxon model implements
 * GoogleProductCategoryAwareInterface. Rendered in the "General" section by the Twig hook of the plugin.
 */
final class TaxonTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [TaxonType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $taxon = $event->getData();
            if (!$taxon instanceof GoogleProductCategoryAwareInterface && $taxon !== null) {
                return;
            }
            $dataClass = $event->getForm()->getConfig()->getDataClass();
            if ($taxon === null && ($dataClass === null || !is_subclass_of($dataClass, GoogleProductCategoryAwareInterface::class))) {
                return;
            }
            $event->getForm()->add('googleProductCategory', TextType::class, [
                'required' => false,
                'label' => 'altmenorg_sylius_google_shopping.form.taxon.google_product_category',
                'help' => 'altmenorg_sylius_google_shopping.form.taxon.google_product_category_help',
                'constraints' => [new Length(max: 255, groups: ['sylius', 'Default'])],
            ]);
        });
    }
}
