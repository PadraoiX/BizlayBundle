<?php
namespace SanSIS\BizlayBundle\Repository;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\IndexedReader;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use SanSIS\BizlayBundle\Entity\AbstractEntity;

/**
 * Class AbstractEntityRepository
 * @package SanSIS\BizlayBundle\EntityRepository
 */
abstract class AbstractRepository extends EntityRepository
{

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

        if (isset($searchData['searchAll']) && trim($searchData['searchAll'])) {
            $props = $reflx->getProperties();

            $and = ' where ';

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
        } else
        if ($searchData) {
            $and = ' where ';
            foreach ($searchData as $field => $criteria) {
                if (
                    trim($searchData[$field]) != "" &&
                    method_exists($this->getEntityName(), 'set' . ucfirst($field))
                ) {
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
}
