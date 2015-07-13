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
     * Retorn a query genérica de pesquisa para primeiro nível de entidade (Grid de Crud)
     */
    public function getSearchQuery(&$searchData)
    {
        $query = $this->createQueryBuilder('g')->getQuery();

        $orderby = $searchData['orderby'];
        unset($searchData['orderby']);

        $reflx = new \ReflectionClass($this->getEntityName());
        $reader = new IndexedReader(new AnnotationReader());
        $and = ' where ';

        if (isset($searchData['searchAll']) && trim($searchData['searchAll'])) {
            $props = $reflx->getProperties();

            foreach ($props as $prop) {
                $attr = $prop->getName();
                $annons = $reader->getPropertyAnnotations($prop);
                if (isset($annons['Doctrine\ORM\Mapping\Column'])) {
                    $pt = $annons['Doctrine\ORM\Mapping\Column']->type;
                    if ($pt == 'string') {
                        $query->setDQL($query->getDQL() . $and . 'lower(g.' . $attr . ') like lower(:' . $attr . ') ');
                        $query->setParameter($attr, '%' . str_replace(' ', '%', trim($searchData['searchAll'])) . '%');
                        $and = ' or ';
                    }
                }
            }

            $and = ' and ';

        } else
        if ($searchData) {

            foreach ($searchData as $field => $criteria) {
                if (trim($searchData[$field]) != "" && method_exists($this->getEntityName(), 'set' . ucfirst($field))) {
                    $prop = $reflx->getProperty($field);
                    $annons = $reader->getPropertyAnnotations($prop);
                    if (isset($annons['Doctrine\ORM\Mapping\Column'])) {
                        $pt = $annons['Doctrine\ORM\Mapping\Column']->type;
                        if ($pt == 'string') {
                            $query->setDQL($query->getDQL() . $and . 'lower(g.' . $field . ') like lower(:' . $field . ') ');
                            $query->setParameter($field, '%' . str_replace(' ', '%', trim($criteria)) . '%');
                            $and = ' and ';
                        }
                    }
                } else {
                    unset($searchData[$field]);
                }
            }
        }

        $boolAttr = $reflx->hasProperty('isActive') ? 'isActive' : ($reflx->hasProperty('flActive') ? 'flActive' : false);

        if ($boolAttr) {
            $query->setDQL($query->getDQL() . $and . ' g.' . $boolAttr . ' = :boolFlag');
            $query->setParameter('boolFlag', true);
        }

        if ($reflx->hasProperty('statusTuple')) {
            $query->setDQL($query->getDQL() . $and . ' g.statusTuple <> 0 ');
        }

        if (isset($orderby) && $orderby) {
            $query->setDQL($query->getDQL() . ' order by ' . $orderby);
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
                $getMethod = 'get' . ucfirst($prop->getName());
                $uniqueParam = $entity->$getMethod();
                //verificar e adicionar o erro
                $qb = $this->createQueryBuilder('u');
                $qb->select('u.id')
                   ->andWhere(
                       $qb->expr()->eq('u.' . $prop->getName(), ':param')
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
                    $this->addError('verificação', 'Já existe o valor informado para ' . $prop->getName() . '.', 'Repository', get_class($this), $prop->getName());
                }
            }
        }
    }
}
