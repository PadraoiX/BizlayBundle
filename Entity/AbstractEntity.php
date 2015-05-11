<?php

namespace SanSIS\BizlayBundle\Entity;

use \Doctrine\Common\Annotations\AnnotationReader;
use \Doctrine\Common\Annotations\IndexedReader;
use \Doctrine\Common\Collections\ArrayCollection;
use \Doctrine\ORM\Mapping as ORM;
use \Doctrine\ORM\PersistentCollection;
use \SanSIS\BizlayBundle\Entity\Exception\ValidationException;

/**
 * Class AbstractEntity
 * @package SanSIS\BizlayBundle\Entity
 * @ORM\Entity(repositoryClass="SanSIS\BizlayBundle\Repository\AbstractRepository")
 * @ORM\HasLifecycleCallbacks()
 */
abstract class AbstractEntity
{
    /**
     * @var array
     */
    protected static $__toArray = array();
    protected static $__converted = array();
    protected static $__processed = array();
    // protected static $__cryptDelimiter = '<-==->';

    /**
     * Array com erros no processamento da Service
     *
     * @var array
     */
    protected static $__errors = array();

    /**
     * Objeto que contém a instancia atual
     *
     * @var array
     */
    protected $__parent = null;

    public function setParent($parent)
    {
        $this->__parent = $parent;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function fromArray(array $data)
    {
        foreach ($data as $key => $value) {
            $method = 'set' . $key;
            if (method_exists($this, $method)) {
                $this->{$method}($value);
            }
        }
        return $this;
    }

    public function buildFullEmptyEntity()
    {
        $ref = new \ReflectionClass($this);

        $methods = get_class_methods($this);

        foreach ($methods as $method) {
            if ('set' === substr($method, 0, 3) && $method != "setParent") {
                $attr = lcfirst(substr($method, 3));
                $params = $ref->getMethod($method)->getParameters();
                $strDoc = $ref->getMethod($method)->getDocComment();
                $strAttr = $ref->getProperty($attr)->getDocComment();
                $class = '';

                if (isset($params[0]) && $params[0]->getClass()) {
                    if (strstr($strDoc, '@innerEntity')) {
                        $begin = str_replace("\r", '', substr($strDoc, strpos($strDoc, '@innerEntity ') + 13));
                        $class = substr($begin, 0, strpos($begin, "\n"));
                        $method = str_replace('set', 'add', $method);
                    } else {
                        $bpos = strpos($strDoc, '@param ') + 7;
                        $epos = strpos($strDoc, ' $') - $bpos;
                        $class = substr($strDoc, $bpos, $epos);
                    }
                    if ($class != get_class($this->__parent)) {
                        if (!in_array($class, self::$__processed)) {
                            self::$__processed[] = $class;
                            $subObj = new $class();
                            $subObj->setParent($this);
                            $this->$method($subObj->buildFullEmptyEntity());
                        }
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $data = array();
        if (!in_array($this, self::$__toArray, true)) {
            self::$__toArray[] = $this;
            $methods = get_class_methods($this);
            foreach ($methods as $method) {
                if ('get' === substr($method, 0, 3) && $method != "getErrors") {
                    $value = $this->$method();
                    if (\is_array($value) || $value instanceof ArrayCollection || $value instanceof PersistentCollection) {
                        $subvalues = array();
                        foreach ($value as $key => $subvalue) {
                            if ($subvalue instanceof AbstractEntity && $this->__parent != $subvalue) {
                                $subvalue->setParent($this);
                                $subvalues[$key] = $subvalue->toArray();
                            } else if ($value instanceof \DateTime) {
                                $subvalue = $subvalue->format('Y-m-d h:m:i');
                            } else if (is_object($subvalue) && $this->__parent != $subvalue) {
                                $subvalues[$key] = $subvalue->toString();
                            } else if ($this->__parent != $subvalue) {
                                $subvalues[$key] = $subvalue;
                            }
                        }
                        $value = $subvalues;
                    }
                    if ($value instanceof AbstractEntity && $this->__parent != $value) {
                        $value->setParent($this);
                        $value = $value->toArray();
                    } else if ($value instanceof \DateTime) {
                        $value = $value->format('Y-m-d h:m:i');
                    } else if (is_object($value) && $this->__parent != $value) {
                        /*@TODO - verificar tipo de objeto*/
                        if (method_exists($value, 'toString')) {
                            $value = $value->toString();
                        }

                        if (method_exists($value, '__toString')) {
                            $value = $value->__toString();
                        }

                    }
                    if (!$this->__parent || ($this->__parent && (($value instanceof AbstractEntity && $this->__parent != $value) || !($value instanceof AbstractEntity)))) {
                        $data[lcfirst(substr($method, 3))] = $value;
                    }
                }
            }
            self::$__converted[spl_object_hash($this)] = $data;
        } else {
            if (isset(self::$__converted[spl_object_hash($this)])) {
                $data = self::$__converted[spl_object_hash($this)];
            }
        }
        return $data;
    }

    /**
     * Verifica se foram registrados erros no validate ou verify da Service
     *
     * @return bool
     */
    public function hasErrors()
    {
        return (bool) count(self::$__errors);
    }

    /**
     * Retorna os erros
     */
    public function getErrors()
    {
        return self::$__errors;
    }

    /**
     * Adiciona uma mensagem ao bus de erros da service
     *
     * @param $type - pode ser validação, verificação ou sistema. Outros tipos podem ser criados conforme necessário
     * @param $message - Mensagem do erro específico.
     * @param null $level - Em que nível foi encotrado o erro (Dooctrine, Entidade, Service, ou outra Service, por exemplo)
     * @param null $source - Objeto que causou o erro
     * @param null $attr - atributo que causou o erro
     */
    public function addError($type, $message, $level = null, $source = null, $attr = null)
    {
        $i = count(self::$__errors);
        self::$__errors[$i] = array();
        self::$__errors[$i]['type'] = $type;
        self::$__errors[$i]['message'] = $message;
        self::$__errors[$i]['level'] = $level;
        self::$__errors[$i]['source'] = $source;
        self::$__errors[$i]['attr'] = $attr;
    }

    /**
     * Função genérica que lerá as annotations da classe e verificará
     * coisas como tipo do dado, comprimento, qtd de itens na collection, etc
     *
     * @ORM\PreFlush()
     */
    public function isValid()
    {
        $reflx = new \ReflectionClass($this);
        $reader = new IndexedReader(new AnnotationReader());
        $props = $reflx->getProperties();
        //$annotations = $reader->getClassAnnotation($reflx);

        foreach ($props as $prop) {
            $annons = $reader->getPropertyAnnotations($prop);
            //var_dump($annons);
            $attr = $prop->getName();
            $method = 'get' . ucfirst($attr);
            if (
                !strstr($attr, '__') &&
                $attr != 'lazyPropertiesDefaults' &&
                $attr != 'id' &&
                $attr != '__toArray' &&
                $attr != '__converted' &&
                $attr != '__errors'
                &&
                (
                    is_object($this->__parent) &&
                    $this->$method() !== $this->__parent
                )
            ) {
                if ((isset($annons['Doctrine\ORM\Mapping\ManyToOne']) || isset($annons['Doctrine\ORM\Mapping\OneToOne'])) && is_object($this->$method())) {
                    $this->$method()->setParent($this);
                    $this->$method()->isValid($this);
                } else if (isset($annons['Doctrine\ORM\Mapping\ManyToMany']) || isset($annons['Doctrine\ORM\Mapping\OneToMany'])) {
                    foreach ($this->$method() as $obj) {
                        if (is_object($obj)) {
                            $obj->setParent($this);
                            $obj->isValid($this);
                        }
                    }
                } else if (isset($annons['Doctrine\ORM\Mapping\Column'])) {
                    $this->checkType($attr, $annons['Doctrine\ORM\Mapping\Column']->type, $annons['Doctrine\ORM\Mapping\Column']->nullable);
                    $this->checkMaxSize($attr, $annons['Doctrine\ORM\Mapping\Column']->length);
                    $this->checkNullable($attr, $annons['Doctrine\ORM\Mapping\Column']->nullable);
                }
            }
        }

        if ($this->__parent != $this && $this->hasErrors()) {
            throw new ValidationException($this->getErrors());
        }

        if ($this->hasErrors()) {
            die('asdf');
            throw new ValidationException($this->getErrors());
        }
    }

    /**
     * Verifica o tipo do atributo
     */
    public function checkType($prop, $type, $nullable)
    {
        if (!$nullable) {
            $method = 'get' . ucfirst($prop);
            switch ($type) {
                case in_array($type, array('smallint', 'integer', 'bigint')):
                    is_int($this->$method()) ? true : $this->addError('Tipo', 'O atributo não é um inteiro: ' . get_class($this) . '::' . $prop . ' => ' . $this->$method() . ' ( nullable : ' . (int) $nullable . ')');
                    break;
                case in_array($type, array('decimal', 'float')):
                    is_float($this->$method()) ? true : $this->addError('Tipo', 'O atributo não é um float: ' . get_class($this) . '::' . $prop . ' => ' . $this->$method() . ' ( nullable : ' . (int) $nullable . ')');
                    break;
                case in_array($type, array('boolean')):
                    is_bool($this->$method()) ? true : $this->addError('Tipo', 'O atributo não é um boolean: ' . get_class($this) . '::' . $prop . ' => ' . $this->$method() . ' ( nullable : ' . (int) $nullable . ')');
                    break;
                case in_array($type, array('date', 'datetime', 'datetimetx', 'time')):
                    ($this->$method() instanceof \DateTime) ? true : $this->addError('Tipo', 'O atributo não é um date/datetime/time: ' . get_class($this) . '::' . $prop . ' => ' . $this->$method()->format('Y-m-d H:i:s') . ' ( nullable : ' . (int) $nullable . ')');
                    break;
            }
        }
    }

    /**
     * Verifica o comprimento máximo permitido para o campo
     */
    public function checkMaxSize($prop, $length)
    {
        $method = 'get' . ucfirst($prop);
        if ($length) {
            (strlen($this->$method()) > $length) ?
            $this->addError(
                'Nulo',
                'Atributo com comprimento superior ao permitido: ' . $prop .
                ', comprimento: ' . strlen($this->$method()) .
                ', máximo: ' . $length,
                'Doctrine',
                get_class($this),
                $prop
            )
            : true;
        }
    }

    /**
     * Verifica se o campo está nulo/vazio ou não
     */
    public function checkNullable($prop, $nullable)
    {
        $method = 'get' . ucfirst($prop);
        if (!$nullable) {
            (is_null($this->$method()) || empty($this->$method())) ?
            $this->addError(
                'Nulo ou Vazio',
                'O atributo na entidade ' . get_class($this) .
                ' não pode ser nulo ou vazio: ' . $prop,
                'Doctrine',
                get_class($this),
                $prop
            )
            : true;
        }
    }
}
