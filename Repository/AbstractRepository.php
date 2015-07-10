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

        $and = '';

        if (isset($searchData['searchAll']) && trim($searchData['searchAll'])) {
            $props = $reflx->getProperties();

            if (count($props)) {
                $query->setDQL($query->getDQL().' where ( ');
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

                $query->setDQL($query->getDQL().' ) ');

                $and = ' and ';
            } else {
                $and = ' where ';
            }

        } else
        if (count($searchData)) {
            $query->setDQL($query->getDQL().' where ( ');
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
            $query->setDQL($query->getDQL().' ) ');
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
