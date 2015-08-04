<?php
namespace SanSIS\BizlayBundle\Repository;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\IndexedReader;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use SanSIS\BizlayBundle\Entity\AbstractEntity;
use \SanSIS\BizlayBundle\Service\ServiceDto;

/**
 * Class AbstractEntityRepository
 * @package SanSIS\BizlayBundle\EntityRepository
 */
abstract class AbstractRepository extends EntityRepository
{
    /**
     * Array com erros no processamento da Service
     *
     * @var array
     */
    protected static $__errors = array();

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
     * Checa se o banco é PostgreSQL
     */
    public function checkPgSql()
    {
        $params = $this->getEntityManager()->getConnection()->getParams();
        return (bool) strstr($params['driver'], 'pgsql');
    }

    /**
     * Define a sintaxe do orderBy ignorando toda e qualquer limitação de banco de dados.
     * Criado especificamente para o orderBy Natural Sort no PostgreSQL (não existe ainda
     * SET LC_COLLATE nessa plataforma).
     */
    public function getGeneralOrderBy(&$searchData, &$orderBy, &$sortOrder)
    {
        $metadata = $this->getEntityManager()->getClassMetadata($this->getEntityName());
        $getIdent = $metadata->getIdentifier();
        $identifier = isset($getIdent[0]) ? $getIdent[0] : 'id';

        /**
         * @TODO - Criar método para fazer pesquisa natural quando for Postgres
         * ORDER BY regexp_replace(name, '^([^[:digit:]]*).*$', '\1'),
         *          regexp_replace(name, '^.*?([[:digit:]]*)$', '\1')::bigint;
         */
        $sortOrder = ($searchData['sortOrder']) ? $searchData['sortOrder'] : 'desc';
        $orderBy = ($searchData['orderBy']) ? $searchData['orderBy'] : 'g.'.$identifier;

        unset($searchData['sortOrder']);
        unset($searchData['orderBy']);
    }

    /**
     * Retorn a query genérica de pesquisa para primeiro nível de entidade (Grid de Crud)
     */
    public function getSearchQuery(&$searchData)
    {
        $qb = $this->createQueryBuilder('g');
        $query = $qb->getQuery();
        $origDQL = $query->getDQL();

        $this->getGeneralOrderBy($searchData, $orderBy, $sortOrder);

        $reflx = new \ReflectionClass($this->getEntityName());
        $reader = new IndexedReader(new AnnotationReader());

        $and = ' ';

        if (isset($searchData['searchAll']) && trim($searchData['searchAll'])) {
            $props = $reflx->getProperties();

            if (count($props)) {
                $query->setDQL($query->getDQL() . ' where ( ');
                foreach ($props as $prop) {
                    $attr = $prop->getName();
                    $annons = $reader->getPropertyAnnotations($prop);
                    if (isset($annons['Doctrine\ORM\Mapping\Column'])) {
                        $pt = $annons['Doctrine\ORM\Mapping\Column']->type;
                        if ($pt == 'string' || $pt == 'text') {
                            $query->setDQL($query->getDQL() . $and . $this->ci('g.' . $attr) . ' like '.$this->ci(':' . $attr));
                            $query->setParameter($attr, '%' . str_replace(' ', '%', trim($searchData['searchAll'])) . '%');
                            $and = ' or ';
                        }
                    }
                }

                $query->setDQL($query->getDQL() . ' ) ');

                $and = ' and ';
            } else {
                $and = ' where ';
            }

        } else
        if (count($searchData)) {
            $count = false;
            $dql = ' where ( ';
            $arrNum = array('integer', 'int', 'smallint', 'bigint', 'float', 'decimal');
            $arrDtTm = array('date','datetime','time');
            foreach ($searchData as $field => $criteria) {
                if (trim($searchData[$field]) != "" && method_exists($this->getEntityName(), 'set' . ucfirst($field))) {
                    $prop = $reflx->getProperty($field);
                    $annons = $reader->getPropertyAnnotations($prop);
                    if (isset($annons['Doctrine\ORM\Mapping\Column'])) {
                        $pt = $annons['Doctrine\ORM\Mapping\Column']->type;

                        if ($pt == 'string' || $pt == 'text') {
                            $dql .= $and . $this->ci('g.' . $field) . ' like '.$this->ci(':' . $field);
                            $query->setParameter($field, '%' . str_replace(' ', '%', trim($criteria)) . '%');
                            $and = ' and ';
                        }
                        if ($pt == 'boolean') {
                            $dql .= $and . 'g.' . $field . ' = :' . $field . ' ';
                            $query->setParameter($field, (bool) trim($criteria));
                            $and = ' and ';
                        }
                        if (in_array($pt, $arrDtTm)) {

                            $dql .= $and . 'g.' . $field . ' = :' . $field . ' ';
                            //@TODO - Melhorar isto depois, pelamordedeus

                            if (strstr($criteria, 'T')) {
                                $criteria = explode('T', $criteria);
                                if (strstr($criteria[1], '.')) {
                                    $time = explode('.', $criteria[1]);
                                } else {
                                    $time = explode('-', $criteria[1]);
                                }
                                $criteria = $criteria[0] . ' ' . $time[0];
                            }

                            if (strstr($criteria, '/')) {
                                if (strstr($criteria, ':')) {
                                    $criteria = \Datetime::createFromFormat('d/m/Y H:i:s', $criteria);
                                } else {
                                    $criteria = \Datetime::createFromFormat('d/m/Y', $criteria);
                                }
                            } else {
                                if (strstr($criteria, ':')) {
                                    $criteria = \Datetime::createFromFormat('Y-m-d H:i:s', $criteria);
                                } else {
                                    $criteria = \Datetime::createFromFormat('Y-m-d', $criteria);
                                }
                            }
                            $query->setParameter($field, $criteria);
                            $and = ' and ';
                        }
                        if (in_array($pt, $arrNum))
                        {
                            $dql .= $and . 'g.' . $field . ' = :' . $field . ' ';
                            $query->setParameter($field, $criteria);
                            $and = ' and ';
                        }
                    }
                    $count = true;
                } else {
                    unset($searchData[$field]);
                }
            }
            $dql .= ' ) ';
            if ($count) {
                $query->setDQL($query->getDQL() . $dql);
            } else {
                $and = ' where ';
            }
        } else {
            $and = ' where ';
        }

        $boolAttr = $reflx->hasProperty('isActive') ? 'isActive' : ($reflx->hasProperty('flActive') ? 'flActive' : false);

        if ($boolAttr) {
            $query->setDQL($query->getDQL() . $and . ' g.' . $boolAttr . ' = :boolFlag');
            $query->setParameter('boolFlag', true);
        }

        if ($reflx->hasProperty('statusTuple')) {
            $query->setDQL($query->getDQL() . $and . ' g.statusTuple <> 0 ');
        }

        /**
         * Workaround para utilizar o QueryBuilder para parsear o orderBy e sortOrder
         */
        if (isset($orderBy) && $orderBy) {
            $sortOrder = ($sortOrder ? $sortOrder : ' asc ');
//            if ($this->checkPgSql()) {

//            } else {
                $finalDql = str_replace(
                    $origDQL,
                    $query->getDQL(),
                    $qb->orderBy($orderBy, $sortOrder)->getQuery()->getDQL()
                );
                $query->setDQL($finalDql);
//            }
        }

        return $query->setHydrationMode(Query::HYDRATE_ARRAY);
    }

    /**
     * Pega todos os dados de uma pesquisa, sem paginação.
     * Utilizado para exportação de resultados da pesquisa.
     *
     * @param  [type] &$searchData [description]
     * @return [type]              [description]
     */
    public function getAllSearchData(&$searchData)
    {
        $query = $this->getSearchQuery($searchData);
        return $query->getScalarResult();
    }

    public function ci($prepareString, $pt = 'string')
    {
        if ($pt == 'text' || $pt == 'string') {
            //        if ($this->checkPgSql()) {
//            return 'lower(to_ascii('.$prepareString.'))';
//        }
            return 'lower(' . $prepareString . ')';
        }
        else {
            return $prepareString;
        }
    }

    /**
     * Verifica atributos unique em registros ativos
     *
     * @param  ServiceDto $dto [description]
     * @return [type]          [description]
     */
    public function checkUnique(ServiceDto $dto, AbstractEntity $entity)
    {
        $reflx = new \ReflectionClass($this->getEntityName());
        $reader = new IndexedReader(new AnnotationReader());
        $props = $reflx->getProperties();

        foreach ($props as $prop) {
            $annons = $reader->getPropertyAnnotations($prop);
            if ($prop->getName() != 'id' && isset($annons['Doctrine\ORM\Mapping\Column']->unique) && $annons['Doctrine\ORM\Mapping\Column']->unique) {
                $pt = $annons['Doctrine\ORM\Mapping\Column']->type;
                $getMethod = 'get' . ucfirst($prop->getName());
                $uniqueParam = $entity->$getMethod();
                //verificar e adicionar o erro
                $qb = $this->createQueryBuilder('u');
                $qb->select('u.id')
                    ->andWhere(
                           $qb->expr()->eq($this->ci('u.' . $prop->getName(), $pt), $this->ci(':param', $pt))
                       )
                   ->setParameter('param', $uniqueParam);

                $id = $entity->getId();
                if (is_null($id)) {
                    $qb->andWhere(
                        $qb->expr()->isNotNull('u.id')
                    );
                } else {
                    $qb->andWhere(
                           $qb->expr()->neq('u.id', ':id')
                       )
                       ->setParameter('id', $id);
                }

                $query = $qb->getQuery();

                $boolAttr = $reflx->hasProperty('isActive') ? 'isActive' : ($reflx->hasProperty('flActive') ? 'flActive' : false);

                if ($boolAttr) {
                    $query->setDQL($query->getDQL() . ' and u.' . $boolAttr . ' = :boolFlag');
                    $query->setParameter('boolFlag', true);
                }

                if ($reflx->hasProperty('statusTuple')) {
                    $query->setDQL($query->getDQL() . ' and u.statusTuple <> 0 ');
                }

                if (count($query->getScalarResult())) {
                    $this->addError('verificação', 'Já existe o valor informado para ' . $prop->getName() . '.', 'Repository', get_class($entity), $prop->getName());
                }
            }
        }
    }
}
