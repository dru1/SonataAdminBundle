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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormBuilder;

use Sonata\AdminBundle\Form\DataTransformer\ArrayToModelTransformer;

class AdminType extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        $admin = $this->getAdmin($options);
        if ($options['delete'] && $admin->isGranted('DELETE') ) {
            $builder->add('_delete', 'checkbox', array('required' => false, 'property_path' => false));
        }

        // dru1:
        // we need to have a specific field description based on every subject in a one-to-many collection.
        // this will allow dynamic form types in the Admin class
        //
        $admin->setSubject($builder->getData());
        
        // dru1: (maybe redundant functionality)
        // we also want to know which admin is on the "owning side".
        if (method_exists($admin, "setAssociatedAdmin")) {
            $admin->setAssociatedAdmin($this->getFieldDescription($options)->getAdmin());
        }

        $admin->defineFormBuilder($builder);

        $builder->prependClientTransformer(new ArrayToModelTransformer($admin->getModelManager(), $admin->getClass()));
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultOptions(array $options)
    {
        return array(
            'delete' => true,
        );
    }

    /**
     * @param array $options
     *
     * @return \Sonata\AdminBundle\Admin\FieldDescriptionInterface
     * @throws \RuntimeException
     */
    public function getFieldDescription(array $options)
    {
        if (!isset($options['sonata_field_description'])) {
            throw new \RuntimeException('Please provide a valid `sonata_field_description` option');
        }

        return $options['sonata_field_description'];
    }

    /**
     * @param array $options
     *
     * @return \Sonata\AdminBundle\Admin\AdminInterface
     */
    public function getAdmin(array $options)
    {
        return $this->getFieldDescription($options)->getAssociationAdmin();
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'sonata_type_admin';
    }
}
