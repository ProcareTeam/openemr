<?php

/**
 * AuthEvent class
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    Hardik Khatri
 */

namespace OpenEMR\Events\Generic;

use Symfony\Contracts\EventDispatcher\Event;

class AuthEvent extends Event
{
    public const EVENT_EXPIRETIME_NAME = "generic.auth.expiretime";

    /**
     * @var array
     */
    private $expire_in;
    private $request;

    /**
     * AuthEvent constructor.
     * @param string $request
     */
    public function __construct($request)
    {
        $this->request = $request;
        $this->expire_in = '';
    }

    public function getRequest() {
        return $this->request;
    }

    /**
     * @return array
     */
    public function getExpireIn(): string
    {
        return $this->expire_in;
    }

    public function setExpireIn(string $expire_time): AuthEvent
    {
        $this->expire_in = $expire_time;
        return $this;
    }
}
