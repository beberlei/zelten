<?php

namespace Zelten\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class BookmarkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('url', 'text', array('attr' => array('class' => 'url')))
            ->add('title', 'text', array('required' => false))
            ->add('description', 'textarea', array('required' => false))
            ->add('image', 'hidden', array('required' => false, 'attr' => array('class' => 'image')))
            //->add('siteName', 'text', array('required' => false))
            ->add('privacy', 'choice', array('choices' => array('private' => 'Private', 'public' => 'public')))
            ->add('backup', 'checkbox', array('required' => false))
        ;
    }

    public function getName()
    {
        return 'bookmark';
    }

    public function getDefaultOptions(array $options)
    {
        return array('csrf_protection' => false);
    }
}

