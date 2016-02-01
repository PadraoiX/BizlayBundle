<?php

namespace SanSIS\BizlayBundle\EventListener;

use \JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use \JMS\Serializer\EventDispatcher\PreSerializeEvent;

class PreSerializeIgnoreExcluded implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            array('event' => 'serializer.pre_serialize', 'method' => 'onPreSerialize'),
        );
    }

    public function onPreSerialize(PreSerializeEvent $event)
    {
        die ('hello');
    }
}
