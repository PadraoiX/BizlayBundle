<?php
namespace SanSIS\BizlayBundle\Doctrine\Filter;

use Doctrine\ORM\Query\Filter\SQLFilter;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class ActiveTuple
 *
 * Define nomes de campos para serem excluídos em pesquisas. Para habilitar, mapeie no seu config.yml
 *
 *    orm:
 *      default_entity_manager: default
 *        entity_managers:
 *          default:
 *            connection: default
 *              filters:
 *                active_tuple:
 *                  class:   \SanSIS\BizlayBundle\Doctrine\Filter\ActiveTuple
 *                  enabled: true
 *
 *
 * @package SanSIS\BizlayBundle\Doctrine\Filter
 */
class ActiveTuple extends SQLFilter
{
    static $pairsStatusValue = array(
        'fl_active'     => '<> false',
        'is_active'     => '<> false',
        'status_tuple'  => '<> 0',
        'flActive'      => '<> false',
        'isActive'      => '<> false',
        'statusTuple'   => '<> 0',

    );

    /**
     * Gets the SQL query part to add to a query.
     *
     * @return string The constraint SQL if there is available, empty string otherwise
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
    {
        $exists = false;

        // Check if the entity implements the SoftDelete interface
        foreach($targetEntity->fieldMappings as $k => $field) {
            $colName = $field['columnName'];

            if (isset(self::$pairsStatusValue[$colName])) {
                $exists = true;
                $attr = $colName;
                break;
            }
        }

        if (!$exists){
            return "";
        }

        return $targetTableAlias.'.'.$attr.' '.self::$pairsStatusValue[$attr];
    }
}
