<?php

namespace Claroline\CoreBundle\Event\Log;

class LogGroupAddUserEvent extends LogGenericEvent
{
    const ACTION = 'group-add_user';

    public function __construct($receiverGroup, $receiver)
    {
        parent::__construct(
            self::ACTION,
            array(
                'receiverUser' => array(
                    'lastName' => $receiver->getLastName(),
                    'firstName' => $receiver->getFirstName()
                ),
                'receiverGroup' => array(
                    'name' => $receiverGroup->getName()
                )
            ),
            $receiver,
            $receiverGroup
        );
    }

    /**
     * @return array
     */
    public static function getRestriction()
    {
        return array(self::DISPLAYED_ADMIN);
    }
}
