<?php

namespace SanSIS\BizlayBundle\Entity;

use \Doctrine\Common\Annotations\AnnotationReader;
use \Doctrine\Common\Annotations\IndexedReader;
use \Doctrine\Common\Collections\ArrayCollection;
use \Doctrine\ORM\Mapping as ORM;
use \Doctrine\ORM\PersistentCollection;
use \JMS\Serializer\Annotation as Serializer;
use \JMS\Serializer\SerializerBuilder;
use \SanSIS\BizlayBundle\Entity\Exception\ValidationException;

/**
 * Class AbstractEntity
 * @package SanSIS\BizlayBundle\Entity
 * @Serializer\ExclusionPolicy("none")
 */
abstract class AbstractEntity
{
    /**
     * @var array
     * @Serializer\Exclude
     */
    protected static $__toArray = array();

    /**
     * @var array
     * @Serializer\Exclude
     */
    protected static $__converted = array();

    /**
     * @var array
     * @Serializer\Exclude
     */
    protected static $__processed = array();

    /**
     * Array com erros no processamento da Service
     *
     * @var array
     * @Serializer\Exclude
     */
    protected static $__errors = array();

    /**
     * Objeto que contém a instancia atual
     *
     * @Serializer\Exclude
     */
    protected $__parent = null;

    /**
     * [$__serializer description]
     * @var null
     */
    protected static $__serializer = null;

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
                try {
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
                                if ($subObj instanceof AbstractEntity) {
                                    $subObj = new $class();
                                    $subObj->setParent($this);
                                    $this->$method($subObj->buildFullEmptyEntity());
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {}
            }
        }

        return $this->toArray();
    }

    private function __getSerializer()
    {
        if (!self::$__serializer) {
            self::$__serializer = SerializerBuilder::create()->build();
        }
        return self::$__serializer;
    }

    private function __getEntityAsArray($entity)
    {
        return
        json_decode(
            $this->__getSerializer()->serialize($entity, 'json')
        );
    }

    /**
     * @return array
     */
    public function toArray($arrayParentClass = null)
    {
        $data = array();
        if (!in_array($this, self::$__toArray, true)) {
            self::$__toArray[] = $this;

            $ref = new \ReflectionClass($this);
            $methods = get_class_methods($this);

            foreach ($methods as $method) {
                if ('get' === substr($method, 0, 3) && $method != "getErrors") {

                    $value = $this->$method();
                    if (\is_array($value) || $value instanceof ArrayCollection || $value instanceof PersistentCollection) {

                        $innerClass = null;

                        $setmethod = str_replace('get', 'set', $method);

                        /**
                         * @TODO Analisar depois o impacto desta condicional
                         */
                        if (method_exists($this, $setmethod)) {

                            $params = $ref->getMethod($setmethod)->getParameters();
                            $strDoc = $ref->getMethod($setmethod)->getDocComment();

                            //Evita o retorno de loop infinito quando entidades possuem uma intersecção manytomany
                            if (isset($params[0]) && $params[0]->getClass()) {
                                if (strstr($strDoc, '@innerEntity')) {
                                    $begin = str_replace("\r", '', substr($strDoc, strpos($strDoc, '@innerEntity ') + 13));
                                    $innerClass = substr($begin, 0, strpos($begin, "\n"));
                                }
                            }
                        }

                        /**
                         * @TODO - Filtrar innerEntity para não ter referência circular
                         */
                        $subvalues = array();
                        foreach ($value as $key => $subvalue) {
                            if ($subvalue instanceof AbstractEntity && $this->__parent !== $subvalue) {
                                $subvalue->setParent($this);
                                $subvalues[$key] = $subvalue->toArray($innerClass);
                            } else if ($value instanceof \DateTime) {
                                $subvalue = $subvalue;
                            } else if (is_object($subvalue) && $this->__parent !== $subvalue) {
                                /*@TODO - verificar tipo de objeto*/
                                if (method_exists($subvalue, 'toString')) {
                                    $subvalue = $subvalue->toString();
                                } else if (method_exists($subvalue, '__toString')) {
                                    $subvalue = $subvalue->__toString();
                                } else {
                                    $subvalue = $this->__getEntityAsArray($subvalue);
                                }
                            } else if ($this->__parent !== $subvalue) {
                                $subvalues[$key] = $subvalue;
                            }
                        }
                        $value = $subvalues;
                    }
                    if ($value instanceof AbstractEntity && $this->__parent !== $value) {
//                        echo $arrayParentClass.'<br>';
//                        echo str_replace('Proxies\__CG__','',get_class($value)).'<br>';
                        if ($arrayParentClass == get_class($value)) {
                            continue;
                        }
                        $value->setParent($this);
                        $value = $value->toArray();
                    } else if ($value instanceof \DateTime) {
                        $value = $value;
                    } else if (is_object($value) && $this->__parent !== $value) {
                        /*@TODO - verificar tipo de objeto*/
                        if (method_exists($value, 'toString')) {
                            $value = $value->toString();
                        } else if (method_exists($value, '__toString')) {
                            $value = $value->__toString();
                        } else {
                            $value = $this->__getEntityAsArray($value);
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
            $val = $this->$method();
            switch ($type) {
                case in_array($type, array('smallint', 'integer', 'bigint')):
                    is_int($val) ? true : $this->addError('Tipo', 'O atributo não é um inteiro: ' . get_class($this) . '::' . $prop . ' => ' . $val . ' ( nullable : ' . (int) $nullable . ')');
                    break;
                case in_array($type, array('decimal', 'float')):
                    is_float($val) ? true : $this->addError('Tipo', 'O atributo não é um float: ' . get_class($this) . '::' . $prop . ' => ' . $val . ' ( nullable : ' . (int) $nullable . ')');
                    break;
                case in_array($type, array('boolean')):
                    is_bool($val) ? true : $this->addError('Tipo', 'O atributo não é um boolean: ' . get_class($this) . '::' . $prop . ' => ' . $val . ' ( nullable : ' . (int) $nullable . ')');
                    break;
                case in_array($type, array('date', 'datetime', 'datetimetx', 'time')):
                    ($val instanceof \DateTime) ? true : $this->addError('Tipo', 'O atributo não é um date/datetime/time: ' . get_class($this) . '::' . $prop . ' => ' . $val->format('Y-m-d H:i:s') . ' ( nullable : ' . (int) $nullable . ')');
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
            $val = $this->$method();
            (strlen($val) > $length) ?
            $this->addError(
                'Nulo',
                'Atributo com comprimento superior ao permitido: ' . $prop .
                ', comprimento: ' . strlen($val) .
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
            $val = $this->$method();
            (is_null($val) || empty($val)) ?
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
