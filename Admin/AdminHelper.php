<?php
/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\AdminBundle\Admin;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormView;
use Sonata\AdminBundle\Exception\NoValueException;
use Sonata\AdminBundle\Util\FormViewIterator;
use Sonata\AdminBundle\Util\FormBuilderIterator;

class AdminHelper
{
    protected $pool;

    /**
     * @param Pool $pool
     */
    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    /**
     * @throws \RuntimeException
     *
     * @param \Symfony\Component\Form\FormBuilder $formBuilder
     * @param string                              $elementId
     *
     * @return \Symfony\Component\Form\FormBuilder
     */
    public function getChildFormBuilder(FormBuilder $formBuilder, $elementId)
    {
        foreach (new FormBuilderIterator($formBuilder) as $name => $formBuilder) {
            if ($name == $elementId) {
                return $formBuilder;
            }
        }

        return null;
    }

    /**
     * @param \Symfony\Component\Form\FormView $formView
     * @param string                           $elementId
     *
     * @return null|\Symfony\Component\Form\FormView
     */
    public function getChildFormView(FormView $formView, $elementId)
    {
        foreach (new \RecursiveIteratorIterator(new FormViewIterator($formView), \RecursiveIteratorIterator::SELF_FIRST) as $name => $formView) {
            if ($name === $elementId) {
                return $formView;
            }
        }

        return null;
    }

    /**
     * @deprecated
     *
     * @param string $code
     *
     * @return \Sonata\AdminBundle\Admin\AdminInterface
     */
    public function getAdmin($code)
    {
        return $this->pool->getInstance($code);
    }

    /**
     * Note:
     *   This code is ugly, but there is no better way of doing it.
     *   For now the append form element action used to add a new row works
     *   only for direct FieldDescription (not nested one)
     *
     * @throws \RuntimeException
     *
     * @param \Sonata\AdminBundle\Admin\AdminInterface $admin
     * @param object                                   $subject
     * @param string                                   $elementId
     *
     * @return array
     */
    public function appendFormFieldElement(AdminInterface $admin, $subject, $elementId)
    {
        // retrieve the subject
        $formBuilder = $admin->getFormBuilder();

        $form = $formBuilder->getForm();
        $form->setData($subject);
        $form->bindRequest($admin->getRequest());

        // get the field element
        $childFormBuilder = $this->getChildFormBuilder($formBuilder, $elementId);

        // retrieve the FieldDescription
        $fieldDescription = $admin->getFormFieldDescription($childFormBuilder->getName());

        try {
            $value = $fieldDescription->getValue($form->getData());
        } catch (NoValueException $e) {
            $value = null;
        }

        // retrieve the posted data
        $data = $admin->getRequest()->get($formBuilder->getName());

        if (!isset($data[$childFormBuilder->getName()])) {
            $data[$childFormBuilder->getName()] = array();
        }

        $objectCount = count($value);
        $postCount   = count($data[$childFormBuilder->getName()]);

        $fields = array_keys($fieldDescription->getAssociationAdmin()->getFormFieldDescriptions());

        // add new elements to the subject
        while ($objectCount < $postCount) {
            // append a new instance into the object
            $this->addNewInstance($form->getData(), $fieldDescription);
            $objectCount++;
        }

        // dru1:
        //  
        // this modification is for a bidirectional assignment (one-to-many and many-to-one)
        // when we add a new entity to the one-to-many collection, this entity should  
        // be assigned to the "parent entity", which is the entity holding the persistent collection.
        //
        // e.g. Entity "Market" and "Stock". The relationship (1:n) is mapped using the entity methods:
        // Market::getStocks()                   owning side
        // Stock::setMarket($market);            inverse side
        //
        // Whenever a new Stock is created from the MarketAdmin Class, the method setMarket() should be called. 
        // This will improve the usability, because the user doesn't have to take care of the owning side,
        // when he is adding a new Stock to a Market.
        // 

        // get a new instance
        $instance = $fieldDescription->getAssociationAdmin()->getNewInstance();
        // set the reference to the owning side on the inverse side 
        // this will be done by the admin class itself. (finding the right method to call)
        $fieldDescription->getAssociationAdmin()->setParentReference($admin, $instance);
        // add the entity to the collection (one-to-many)
        $collection = call_user_func(array($form->getData(),'get'.ucfirst($childFormBuilder->getName())));
        $collection->add($instance);
        // end of fix
        
        $finalForm = $admin->getFormBuilder()->getForm();
        $finalForm->setData($subject);

        // bind the data
        $finalForm->setData($form->getData());

        return array($fieldDescription, $finalForm);
    }

    /**
     * Add a new instance to the related FieldDescriptionInterface value
     *
     * @param object                                              $object
     * @param \Sonata\AdminBundle\Admin\FieldDescriptionInterface $fieldDescription
     *
     * @return void
     */
    public function addNewInstance($object, FieldDescriptionInterface $fieldDescription)
    {
        $instance = $fieldDescription->getAssociationAdmin()->getNewInstance();
        $mapping  = $fieldDescription->getAssociationMapping();

        $method = sprintf('add%s', $this->camelize($mapping['fieldName']));

        if (!method_exists($object, $method)) {
            throw new \RuntimeException(sprintf('Please add a method %s in the %s class!', $method, get_class($object)));
        }

        $object->$method($instance);
    }

    /**
     * Camelize a string
     *
     * @static
     *
     * @param string $property
     *
     * @return string
     */
    public function camelize($property)
    {
        return preg_replace(array('/(^|_| )+(.)/e', '/\.(.)/e'), array("strtoupper('\\2')", "'_'.strtoupper('\\1')"), $property);
    }
}
