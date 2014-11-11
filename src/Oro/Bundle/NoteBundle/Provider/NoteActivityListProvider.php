<?php

namespace Oro\Bundle\NoteBundle\Provider;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ActivityListBundle\Model\ActivityListProviderInterface;
use Oro\Bundle\EntityConfigBundle\Config\Id\ConfigIdInterface;
use Oro\Bundle\NoteBundle\Entity\Note;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;

class NoteActivityListProvider implements ActivityListProviderInterface
{
    const ACTIVITY_CLASS = 'Oro\Bundle\NoteBundle\Entity\Note';

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    public function __construct(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function isApplicableTarget(ConfigIdInterface $configId, ConfigManager $configManager)
    {
        $provider = $configManager->getProvider('activity');
        return $provider->hasConfigById($configId) && $provider->getConfigById($configId)->has('activities');
    }

    /**
     * {@inheritdoc}
     */
    public function getRoutes()
    {
        return [
            'itemEdit'   => 'oro_note_update',
            'itemDelete' => 'oro_api_delete_call'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getActivityClass()
    {
        return self::ACTIVITY_CLASS;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubject($entity)
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getData($entity)
    {
        /** @var Note $entity */
        return [
            'message'=> $entity->getMessage()
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplate()
    {
        return 'OroNoteBundle:Note:js/activityItemTemplate.js.twig';
    }

    /**
     * {@inheritdoc
     */
    public function getActivityId($entity)
    {
        return $this->doctrineHelper->getSingleEntityIdentifier($entity);
    }

    /**
     * {@inheritdoc
     */
    public function isApplicable($entity)
    {
        if (is_object($entity)) {
            $entity = $this->doctrineHelper->getEntityClass($entity);
        }

        return $entity == self::ACTIVITY_CLASS;
    }

    /**
     * {@inheritdoc}
     */
    public function getTargetEntities($entity)
    {
        return array();
    }
}
