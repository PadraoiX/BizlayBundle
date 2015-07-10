<?php

namespace SanSIS\BizlayBundle\Entity\Exception;

class ValidationException extends \Exception
{
    protected $message = 'Bizlay - Entidade - Erros na validação dos dados inseridos na entidade';

    private $errors = array();

    public function __construct($errors = array(), $message = "", $code = 0, Exception $previous = null)
    {
        $this->setErrors($errors);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function setErrors($errors)
    {
        $this->errors = $errors;
        return $this;
    }
}
