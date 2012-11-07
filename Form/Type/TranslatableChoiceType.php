<?php

/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Sonata\AdminBundle\Form\Type;

use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\Extension\Core\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\Extension\Core\DataTransformer\ArrayToBooleanChoicesTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ScalarToBooleanChoicesTransformer;
use Symfony\Component\Form\Extension\Core\EventListener\FixRadioInputListener;
use Symfony\Component\Form\Extension\Core\DataTransformer\ArrayToChoicesTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ScalarToChoiceTransformer;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\AbstractType;

class TranslatableChoiceType extends AbstractType
{
    protected $translator;

    /**
     * @param \Symfony\Component\Translation\TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param array $options
     *
     * @return array
     */
    public function getDefaultOptions(array $options)
    {
        $multiple = isset($options['multiple']) && $options['multiple'];
        $expanded = isset($options['expanded']) && $options['expanded'];

        return array(
            'multiple'          => false,
            'expanded'          => false,
            'choice_list'       => null,
            'choices'           => array(),
            'preferred_choices' => array(),
            'catalogue'         => 'messages',
            'empty_data'        => $multiple || $expanded ? array() : '',
            'empty_value'       => $multiple || $expanded || !isset($options['empty_value']) ? null : '',
            'error_bubbling'    => false,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        // translate options before building form
        foreach ($options['choices'] as $name => $value) {
            $options['choices'][$name] = $this->translator->trans($value, array(), $options['catalogue']);
        }

        // translate empty value
        if (!empty($options['empty_value'])) {
            $options['empty_value'] = $this->translator->trans($options['empty_value'], array(), $options['catalogue']);
        }
        
        // from the choice list itself
        
        if ($options['choice_list'] && !$options['choice_list'] instanceof ChoiceListInterface) {
            throw new FormException('The "choice_list" must be an instance of "Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface".');
        }

        if (!$options['choice_list']) {
            $options['choice_list'] = new ArrayChoiceList($options['choices']);
        }

        if ($options['expanded']) {
            // Load choices already if expanded
            $options['choices'] = $options['choice_list']->getChoices();

            foreach ($options['choices'] as $choice => $value) {
                if ($options['multiple']) {
                    $builder->add((string) $choice, 'checkbox', array(
                        'value'     => $choice,
                        'label'     => $value,
                        // The user can check 0 or more checkboxes. If required
                        // is true, he is required to check all of them.
                        'required'  => false,
                    ));
                } else {
                    $builder->add((string) $choice, 'radio', array(
                        'value' => $choice,
                        'label' => $value,
                    ));
                }
            }
        }

        // empty value
        if ($options['multiple'] || $options['expanded']) {
            // never use and empty value for these cases
            $emptyValue = null;
        } elseif (false === $options['empty_value']) {
            // an empty value should be added but the user decided otherwise
            $emptyValue = null;
        } elseif (null === $options['empty_value']) {
            // user did not made a decision, so we put a blank empty value
            $emptyValue = $options['required'] ? null : '';
        } else {
            // empty value has been set explicitly
            $emptyValue = $options['empty_value'];
        }

        $builder
            ->setAttribute('choice_list', $options['choice_list'])
            ->setAttribute('preferred_choices', $options['preferred_choices'])
            ->setAttribute('multiple', $options['multiple'])
            ->setAttribute('expanded', $options['expanded'])
            ->setAttribute('required', $options['required'])
            ->setAttribute('empty_value', $emptyValue)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(array $options)
    {
        return 'choice';
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'sonata_type_translatable_choice';
    }
}
