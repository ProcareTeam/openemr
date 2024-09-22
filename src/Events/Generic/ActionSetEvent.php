<?php


namespace OpenEMR\Events\Generic;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event object for know what type of form has been set
 *
 * @package OpenEMR\Events
 * @subpackage Generic
 *
 */
class ActionSetEvent extends Event
{
    /**
     * This event is triggered after form save
     */
    const EVENT_HANDLE = 'action.set';

    /**
     * @var
     */
    private $post;

    public $id;

    public function __construct($post)
    {
        $this->post = $post;
    }

    /**
     * @return array
     */
    public function givenActionData(): array
    {
        return $this->post;
    }

    public function setEventId($id)
    {
        $this->id = $id;
    }
}
