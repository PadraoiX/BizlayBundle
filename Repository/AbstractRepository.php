<?php

namespace SanSIS\BizlayBundle\Repository;

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

        if (isset($searchData['searchAll']) && trim($searchData['searchAll'])) {
            $entityName = $this->getEntityName();
            $entity = new $entityName();
            $ref = new \ReflectionClass($entity);
            $methods = get_class_methods($entityName);
            $attrs = array();

            //Processa os dados que vem do serializeArray da jQuery para uma hash table PHP
            foreach ($methods as $method) {
                if (strstr($method, 'set') && $method != 'setParent') {
                    $params = $ref->getMethod($method)->getParameters();
                    if (!$params[0]->getClass()) {
                        $attrs[] = lcfirst(str_replace('set', '', $method));
                    }
                }
            }

            $and = ' where ';
            foreach ($attrs as $attr) {
                $query->setDQL($query->getDQL() . $and . 'g.' . $attr . ' like :' . $attr . ' ');
                $query->setParameter($attr, '%' . str_replace(' ', '%', trim($searchData['searchAll'])) . '%');
                $and = ' or ';
            }
        } else if ($searchData) {
            $and = ' where ';
            foreach ($searchData as $field => $criteria) {
                if (trim($searchData[$field]) != "") {
                    $query->setDQL($query->getDQL() . $and . 'g.' . $field . ' like :' . $field . ' ');
                    $query->setParameter($field, '%' . str_replace(' ', '%', trim($criteria)) . '%');
                    $and = ' and ';
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
