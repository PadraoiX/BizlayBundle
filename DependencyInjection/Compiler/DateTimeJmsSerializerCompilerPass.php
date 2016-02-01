<?php

namespace SanSIS\BizlayBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DateTimeJmsSerializerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        // A configuração padrão do JMSSerializerBundle é representar datas
        // como DateTime::ISO8601, que não inclui separador entre horas e
        // minutos no timezone:
        //     2007-05-15T04:21:50-0300
        // Este formato não é compreendido nativamente pelo IE ou pelo Safari.
        // O formato RFC3339 é semelhante, mas separando horas e minutos com
        // ":", sendo compreendido por todos os browsers:
        //     2007-05-15T04:21:50-03:00
        $datetimeHandlerDef = $container->getDefinition("jms_serializer.datetime_handler");
        $datetimeHandlerDef->replaceArgument(0, \DateTime::RFC3339);
    }
}
