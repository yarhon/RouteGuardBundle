<?php

/*
 *
 * (c) Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yarhon\RouteGuardBundle\Security\Test;

/**
 * @author Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 */
class SensioExtraTest extends AbstractSymfonySecurityTest
{
    /**
     * @var array
     */
    private $metadata = [];

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return self
     */
    public function setMetadata($name, $value)
    {
        $this->metadata[$name] = $value;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getMetadata($name)
    {
        return isset($this->metadata[$name]) ? $this->metadata[$name] : null;
    }

    public function serialize()
    {
        return serialize([$this->attributes, $this->subject, $this->metadata]);
    }

    public function unserialize($data)
    {
        list($attributes, $subject, $metadata) = unserialize($data);
        $this->attributes = $attributes;
        $this->subject = $subject;
        $this->metadata = $metadata;
    }
}
