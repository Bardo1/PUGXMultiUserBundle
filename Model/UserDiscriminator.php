<?php

namespace PUGX\MultiUserBundle\Model;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Form\FormFactoryInterface;
use FOS\UserBundle\Model\UserInterface;

/**
 * Description of UserDiscriminator
 * 
 * @author leonardo proietti (leonardo.proietti@gmail.com)
 * @author eux (eugenio@netmeans.net)
 */
class UserDiscriminator
{
    const SESSION_NAME = 'pugx_user.user_discriminator.class'; 
    
    /**
     *
     * @var SessionInterface 
     */
    protected $session;
    
    /**
     *
     * @var FormFactoryInterface 
     */
    protected $formFactory;
    
    /**
     *
     * @var array 
     */
    protected $entities = array();
    
    /**
     *
     * @var array 
     */
    protected $registrationFormTypes = array();  
    
    /**
     *
     * @var array 
     */
    protected $profileFormTypes = array();  
    
    /**
     *
     * @var array 
     */
    protected $userFactories = array();
    
    /**
     *
     * @var Symfony\Component\Form\Form 
     */
    protected $registrationForm = null;
    
    /**
     *
     * @var Symfony\Component\Form\Form 
     */
    protected $profileForm = null;
    
    /**
     *
     * @var array 
     */
    protected $registrationTemplates = array();
    
    /**
     *
     * @var string 
     */
    protected $class = null;
    
    /**
     *
     * @var array 
     */
    protected $registrationValidationGroups = array();
    
    /**
     *
     * @var array 
     */
    protected $profileValidationGroups = array();

    /**
     *
     * @param SessionInterface $session
     * @param FormFactoryInterface $formFactory
     * @param array $parameters 
     */
    public function __construct(SessionInterface $session, FormFactoryInterface $formFactory, array $parameters)
    {
        $this->session = $session;
        $this->formFactory = $formFactory;
        
        $this->buildConfig($parameters);
    }
    
    /**
     *
     * @return array 
     */
    public function getClasses()
    {        
        return $this->entities;
    }
        
    /**
     *
     * @param string $class 
     */
    public function setClass($class, $persist = false)
    {
        if (!in_array($class, $this->entities)) {
            throw new \LogicException(sprintf('Impossible to set the class discriminator, because the class "%s" is not present in the entities list', $class));
        }
        
        if ($persist) {
            $this->session->set(static::SESSION_NAME, $class);
        }
        
        $this->class = $class;
    }
    
    /**
     *
     * @return string 
     */
    public function getClass()
    {       
        if (!is_null($this->class)) {
            return $this->class;
        }
        
        $storedClass = $this->session->get(static::SESSION_NAME, null);

        if ($storedClass) {
            $this->class = $storedClass;
        }
        
        if (is_null($this->class)) {
            $this->class = $this->entities[0];
        }
        
        return $this->class;
    }
    
    /**
     *
     * @return type 
     */
    public function createUser()
    {        
        $class   = $this->getClass();
        $factory = $this->userFactories[$class];
        $user    = $factory::build($class);
        
        return $user;
    }
    
    /**
     * 
     * @param string $type
     * @return \Symfony\Component\Form\Form
     */
    public function getForm($type)
    {
        $method = 'get' . ucfirst($type) . 'Form';
        return $this->$method();
    }
    
    /**
     * 
     * @param array $types
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function getFormType($types)
    {
        $class = $this->getClass();
        $className = $types[$class];
        
        if (!class_exists($className)) {
            throw new \InvalidArgumentException(sprintf('UserDiscriminator, error getting form type : "%s" not found', $className));
        }

        $type = new $className($class);
        
        return $type;
    }
    
    /**
     * 
     * @param type $form
     * @param type $types
     * @param type $validationGroups
     * @return type
     */
    protected function buildForm($types, $validationGroups)
    {
        $type = $this->getFormType($types);
            
        $form = $this->formFactory->createNamed(
                $type->getName(), 
                $type, 
                null, 
                array('validation_groups' => $validationGroups[$this->getClass()]));
        
        return $form;
    }

    /**
     *
     * @return \Symfony\Component\Form\Form 
     */
    public function getRegistrationForm()
    {        
        if (is_null($this->registrationForm)) {
            $this->registrationForm = $this->buildForm($this->registrationFormTypes, $this->registrationValidationGroups);
        }
        
        return $this->registrationForm;
    }
    
    /**
     *
     * @return \Symfony\Component\Form\Form 
     */
    public function getProfileForm()
    {
        if (is_null($this->profileForm)) {
            $this->profileForm = $this->buildForm($this->profileFormTypes, $this->profileValidationGroups);
        }
        
        return $this->profileForm;
    }
    
    /**
     * 
     * @return string
     */
    public function getRegistrationTemplate()
    {
        return $this->registrationTemplates[$this->getClass()]; 
    }

    /**
     *
     * @param array $entities
     * @param array $registrationForms
     * @param array $profileForms 
     */
    protected function buildConfig(array $users)
    {
        foreach ($users as $user) {
            
            $class = $user['entity']['class'];
            
            if (!class_exists($class)) {
                throw new \InvalidArgumentException(sprintf('UserDiscriminator, configuration error : "%s" not found', $class));
            }
            
            $this->entities[] = $class;
            $this->registrationFormTypes[$class] = $user['registration']['form']['type'];
            $this->registrationValidationGroups[$class] = $user['registration']['form']['validation_groups'];
            $this->registrationTemplates[$class] = $user['registration']['template'];
            $this->profileFormTypes[$class] = $user['profile']['form']['type'];
            $this->profileValidationGroups[$class] = $user['profile']['form']['validation_groups'];
            $this->userFactories[$class] = $user['entity']['factory'];
        }
    }
}
